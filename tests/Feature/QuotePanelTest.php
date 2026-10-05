<?php

namespace Tests\Feature;

use App\Filament\Resources\ApplicationResource\Pages\EditApplication;
use App\Filament\Resources\ApplicationResource\RelationManagers\QuotesRelationManager;
use App\Filament\Resources\QuoteResource\Pages\CreateQuote;
use App\Filament\Resources\QuoteResource\Pages\ListQuotes;
use App\Filament\Resources\QuoteResource\Pages\ViewQuote;
use App\Filament\Resources\QuoteResource\RelationManagers\DocumentsRelationManager;
use App\Filament\Widgets\PipelineStats;
use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Carrier;
use App\Models\Client;
use App\Models\CoverageType;
use App\Models\Quote;
use App\Models\QuoteDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class QuotePanelTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        return User::factory()->create(['role' => $role, 'locale' => 'es']);
    }

    private function quoteFor(User $owner, array $attributes = []): Quote
    {
        $application = Application::createForClient(Client::factory()->create(), $owner);

        return Quote::factory()->priced()->create(['application_id' => $application->id, 'producer_id' => $owner->id] + $attributes);
    }

    public function test_panel_pages_load_for_each_role(): void
    {
        $owner = $this->user('agent');
        $quote = $this->quoteFor($owner);

        $this->actingAs($owner)->get('/admin/quotes')->assertOk()->assertSee($quote->application->company_name);
        $this->actingAs($owner)->get("/admin/quotes/{$quote->id}")->assertOk();
        $this->actingAs($owner)->get("/admin/quotes/{$quote->id}/edit")->assertOk();
        $this->actingAs($owner)->get("/admin/quotes/create?application={$quote->application_id}")->assertOk();

        $viewer = $this->user('viewer');
        $this->actingAs($viewer)->get("/admin/quotes/{$quote->id}")->assertOk();
        $this->actingAs($viewer)->get("/admin/quotes/{$quote->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->get('/admin/quotes/create')->assertForbidden();

        $stranger = $this->user('agent');
        $this->actingAs($stranger)->get("/admin/quotes/{$quote->id}")->assertNotFound();

        // Terms can only be typed while it is a lead.
        $quote->update(['stage' => 'quote_sent']);
        $this->actingAs($owner)->get("/admin/quotes/{$quote->id}/edit")->assertForbidden();
        $this->actingAs($owner)->get("/admin/quotes/{$quote->id}")->assertOk();

        $this->actingAs($this->user('admin'))->get('/admin/carriers')->assertOk();
    }

    public function test_the_list_only_shows_what_each_agent_may_see(): void
    {
        $ana = $this->user('agent');
        $bob = $this->user('agent');
        $anaQuote = $this->quoteFor($ana);
        $bobQuote = $this->quoteFor($bob);

        Livewire::actingAs($ana)->test(ListQuotes::class)
            ->assertCanSeeTableRecords([$anaQuote])
            ->assertCanNotSeeTableRecords([$bobQuote]);
    }

    public function test_a_quote_is_created_from_an_application_with_its_coverages_copied(): void
    {
        $ana = $this->user('agent');
        $application = Application::createForClient(Client::factory()->create(), $ana);
        $application->coverages()->create([
            'coverage_type_id' => CoverageType::where('key', 'auto_liability')->value('id'),
            'coverage' => 'Auto Liability', 'limit_amount' => '1000000',
        ]);
        $carrier = Carrier::factory()->create();

        $this->actingAs($ana);
        $page = Livewire::test(CreateQuote::class)
            ->fillForm([
                'application_id' => $application->id,
                'carrier_id' => $carrier->id,
                'qualification_note' => 'Owner operator, 3 trucks',
                'next_step' => 'Send the quote',
                'carrier_premium' => 12000,
                'fees' => 500,
                'producer_fee' => 750,
                'down_payment' => 3000,
                'number_of_payments' => 9,
                'installment_amount' => 1250,
            ]);

        // Picking the application filled the carrier's coverage lines from the application's own.
        $this->assertCount(1, $page->get('data.coverages'));

        $page->call('create')->assertHasNoFormErrors();

        $quote = Quote::where('application_id', $application->id)->firstOrFail();
        $this->assertSame('lead', $quote->stage);
        $this->assertSame(1, $quote->version);
        $this->assertSame($ana->id, $quote->producer_id);
        $this->assertSame(1, $quote->coverages()->count());
        $this->assertEquals(1000000, $quote->coverages()->first()->limit_amount);
        $this->assertSame('created', $application->fresh()->status);
    }

    public function test_nobody_creates_a_quote_for_an_application_they_cannot_change(): void
    {
        $owner = $this->user('agent');
        $application = Application::createForClient(Client::factory()->create(), $owner);

        Livewire::actingAs($this->user('agent'))->test(CreateQuote::class)
            ->fillForm(['application_id' => $application->id, 'carrier_id' => Carrier::factory()->create()->id,
                'qualification_note' => 'x', 'next_step' => 'y'])
            ->call('create')
            ->assertForbidden();

        $this->assertSame(0, Quote::count());
    }

    public function test_moving_a_quote_from_the_panel_and_the_errors_it_shows(): void
    {
        $ana = $this->user();
        $quote = $this->quoteFor($ana, ['carrier_premium' => null]);

        $page = Livewire::actingAs($ana)->test(ViewQuote::class, ['record' => $quote->id]);

        $page->callAction('move', data: ['to' => 'quote_sent'])->assertNotified();
        $this->assertSame('lead', $quote->fresh()->stage, 'incomplete quote stays where it was');

        $quote->update(['carrier_premium' => 12000]);
        $page->callAction('move', data: ['to' => 'quote_sent', 'note' => 'Sent by e-mail'])->assertHasNoActionErrors();

        $quote->refresh();
        $this->assertSame('quote_sent', $quote->stage);
        $this->assertSame('Sent by e-mail', $quote->stageChanges()->first()->note);

        $page->callAction('move', data: ['to' => 'lost', 'loss_reason' => 'price', 'closed_at' => '2026-12-01']);
        $this->assertSame('lost', $quote->fresh()->stage);
        $this->assertSame('price', $quote->fresh()->loss_reason);
    }

    public function test_revising_from_the_panel_goes_to_the_new_version(): void
    {
        $ana = $this->user();
        $quote = $this->quoteFor($ana);

        Livewire::actingAs($ana)->test(ViewQuote::class, ['record' => $quote->id])
            ->callAction('revise')
            ->assertRedirect();

        $this->assertSame(2, Quote::where('application_id', $quote->application_id)->max('version'));
        $this->assertNotNull($quote->fresh()->superseded_at);
    }

    public function test_the_link_can_be_revoked_and_renewed_from_the_panel(): void
    {
        $ana = $this->user();
        $quote = $this->quoteFor($ana);
        $page = Livewire::actingAs($ana)->test(ViewQuote::class, ['record' => $quote->id]);

        $page->assertActionHidden('revokeLink');   // nothing sent yet
        $page->callAction('move', data: ['to' => 'quote_sent']);
        $token = $quote->fresh()->acceptance_token;

        $page->assertActionVisible('revokeLink')->callAction('revokeLink');
        $this->assertSame('revoked', $quote->fresh()->acceptanceStatus());

        $page->callAction('newLink');
        $quote->refresh();
        $this->assertSame('open', $quote->acceptanceStatus());
        $this->assertNotSame($token, $quote->acceptance_token, 'the old link must not work again');
    }

    public function test_the_quotes_tab_on_an_application_lists_its_versions(): void
    {
        $ana = $this->user();
        $quote = $this->quoteFor($ana);
        $quote->revise();

        Livewire::actingAs($ana)->test(QuotesRelationManager::class, ['ownerRecord' => $quote->application, 'pageClass' => EditApplication::class])
            ->assertCanSeeTableRecords($quote->application->quotes)
            ->assertCountTableRecords(2);
    }

    public function test_documents_are_uploaded_privately_and_downloads_are_authorised_and_audited(): void
    {
        Storage::fake(QuoteDocument::DISK);
        $ana = $this->user('agent');
        $quote = $this->quoteFor($ana);

        $this->actingAs($ana);
        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $quote, 'pageClass' => ViewQuote::class])
            ->callTableAction('create', data: [
                'type' => 'receipt',
                'path' => UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf'),
            ])
            ->assertHasNoTableActionErrors();

        $document = $quote->documents()->firstOrFail();
        $this->assertSame('receipt', $document->type);
        $this->assertSame($ana->id, $document->uploaded_by);
        Storage::disk(QuoteDocument::DISK)->assertExists($document->path);

        $this->actingAs($ana)->get(route('quote-documents.download', $document))->assertOk();
        $this->assertTrue(ActivityLog::where('event', 'downloaded')->where('subject_type', 'quotedocument')->exists());

        $this->actingAs($this->user('agent'))->get(route('quote-documents.download', $document))->assertForbidden();
        auth()->logout();
        $this->get(route('quote-documents.download', $document))->assertRedirect();   // not signed in
    }

    public function test_a_viewer_cannot_attach_documents(): void
    {
        $quote = $this->quoteFor($this->user('agent'));

        Livewire::actingAs($this->user('viewer'))
            ->test(DocumentsRelationManager::class, ['ownerRecord' => $quote, 'pageClass' => ViewQuote::class])
            ->assertTableActionHidden('create');
    }

    public function test_the_pipeline_widget_counts_current_real_quotes_only(): void
    {
        $ana = $this->user();
        $this->quoteFor($ana);                                    // lead
        $this->quoteFor($ana, ['stage' => 'quote_sent']);
        $demo = $this->quoteFor($ana);
        $demo->update(['is_demo' => true]);
        $replaced = $this->quoteFor($ana);
        $replaced->update(['superseded_at' => now()]);

        $this->actingAs($ana);
        $stats = (new \ReflectionMethod(PipelineStats::class, 'getStats'));
        $values = collect($stats->invoke(new PipelineStats))->map(fn ($s) => $s->getValue())->all();

        $this->assertSame([1, 1, 0, 0, 0, 0, 0], $values);   // lead, quote_sent, ..., sold, lost
    }

    public function test_the_commercial_status_of_a_client_is_its_furthest_current_stage(): void
    {
        $ana = $this->user();
        $quote = $this->quoteFor($ana);
        $client = $quote->client;

        $this->assertSame('lead', $client->fresh()->load('quotes')->commercialStage());

        $quote->update(['stage' => 'binder']);
        $this->quoteFor($ana, ['client_id' => $client->id, 'application_id' => $quote->application_id]);
        $this->assertSame('binder', $client->fresh()->load('quotes')->commercialStage());

        $quote->update(['stage' => 'lost']);
        $quote->newQuery()->where('id', '!=', $quote->id)->update(['stage' => 'lost']);
        $this->assertSame('lost', $client->fresh()->load('quotes')->commercialStage());

        $this->assertNull(Client::factory()->create()->load('quotes')->commercialStage());
    }
}
