<?php

namespace Tests\Feature;

use App\Livewire\ProposalAccept;
use App\Models\Application;
use App\Models\Carrier;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteDocument;
use App\Models\User;
use App\Support\QuotePipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProposalAcceptTest extends TestCase
{
    use RefreshDatabase;

    private const CARRIER = 'Hidden Mutual Insurance';

    private User $advisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->advisor = User::factory()->create(['role' => 'admin', 'name' => 'Ana Admin']);
    }

    /** A priced quote already handed to the client. */
    private function sentQuote(array $attributes = []): Quote
    {
        $application = Application::createForClient(Client::factory()->create(), $this->advisor);
        $carrier = Carrier::firstOrCreate(['name' => self::CARRIER]);
        $quote = Quote::factory()->priced()->create(['application_id' => $application->id, 'carrier_id' => $carrier->id] + $attributes);
        QuotePipeline::move($quote, 'quote_sent', [], $this->advisor);

        return $quote->fresh();
    }

    private function sign($component, string $name = 'John Doe')
    {
        return $component
            ->set('signerName', $name)
            ->set('disclosureAccepted', true)
            ->set('signatureData', 'data:image/png;base64,'.base64_encode('x'))
            ->call('sign');
    }

    public function test_the_client_sees_the_proposal_without_the_carrier(): void
    {
        $quote = $this->sentQuote();

        $this->get($quote->acceptanceUrl())
            ->assertOk()
            ->assertSee($quote->application->company_name)
            ->assertSee('Auto Liability')
            ->assertSee('$13,250.00')            // total cost
            ->assertSee('$14,250.00')            // total payable
            ->assertSee('Accept and sign')
            ->assertDontSee(self::CARRIER);
    }

    public function test_the_proposal_follows_the_language_of_the_application(): void
    {
        $quote = $this->sentQuote();
        $quote->application->update(['locale' => 'es']);

        $this->get($quote->acceptanceUrl())
            ->assertSee('Propuesta formal de seguro')
            ->assertSee('Responsabilidad Civil de Auto')
            ->assertSee('Aceptar y firmar');
    }

    public function test_signing_accepts_the_quote_and_keeps_the_exact_version_signed(): void
    {
        Storage::fake(QuoteDocument::DISK);
        $quote = $this->sentQuote();

        $this->sign(Livewire::test(ProposalAccept::class, ['token' => $quote->acceptance_token]))
            ->assertSet('done', 'accepted');

        $quote->refresh();
        $this->assertSame('accepted', $quote->stage);
        $this->assertSame('John Doe', $quote->accepted_signer_name);
        $this->assertNotNull($quote->accepted_at);
        Storage::disk(QuoteDocument::DISK)->assertExists($quote->accepted_signature_path);

        $this->assertSame(13250.0, (float) $quote->accepted_snapshot['total_cost']);
        $this->assertStringNotContainsString(self::CARRIER, json_encode($quote->accepted_snapshot));

        $change = $quote->stageChanges()->first();
        $this->assertNull($change->user_id);
        $this->assertSame('accepted', $change->to_stage);

        // Reopening the link shows it as already signed, with no way to sign again.
        $this->get($quote->acceptanceUrl())->assertOk()->assertSee('already accepted')->assertDontSee('Accept and sign');
        $this->assertSame('accepted', $quote->acceptanceStatus());

        // The application's own status is a separate matter and did not move.
        $this->assertSame('created', $quote->application->fresh()->status);
    }

    public function test_signing_needs_name_acceptance_and_signature(): void
    {
        $quote = $this->sentQuote();

        Livewire::test(ProposalAccept::class, ['token' => $quote->acceptance_token])
            ->call('sign')
            ->assertHasErrors(['signerName', 'disclosureAccepted', 'signatureData']);

        $this->assertSame('quote_sent', $quote->fresh()->stage);
    }

    public function test_a_second_signature_attempt_is_refused(): void
    {
        Storage::fake(QuoteDocument::DISK);
        $quote = $this->sentQuote();
        $page = Livewire::test(ProposalAccept::class, ['token' => $quote->acceptance_token]);

        $this->sign($page);
        $first = $quote->fresh();

        $this->sign($page, 'Somebody Else')->assertStatus(410);
        $this->assertSame('John Doe', $quote->fresh()->accepted_signer_name);
        $this->assertEquals($first->accepted_at, $quote->fresh()->accepted_at);
    }

    public function test_expired_revoked_replaced_and_unknown_links_cannot_be_signed(): void
    {
        $expired = $this->sentQuote();
        $expired->forceFill(['acceptance_expires_at' => now()->subMinute()])->save();
        $this->get($expired->acceptanceUrl())->assertSee('This proposal has expired')->assertDontSee('Accept and sign');
        $this->sign(Livewire::test(ProposalAccept::class, ['token' => $expired->acceptance_token]))->assertStatus(410);

        $revoked = $this->sentQuote();
        $revoked->revokeAcceptanceLink();
        $this->get($revoked->acceptanceUrl())->assertSee('This link is no longer active');

        $replaced = $this->sentQuote();
        $url = $replaced->acceptanceUrl();
        $replaced->fresh()->revise();
        $this->get($url)->assertSee('This link is no longer active');

        $this->get('/proposal/not-a-real-token')->assertNotFound();

        foreach ([$expired, $revoked, $replaced] as $quote) {
            $this->assertNull($quote->fresh()->accepted_at);
        }
    }

    public function test_a_proposal_that_is_no_longer_the_chosen_alternative_is_unavailable(): void
    {
        $quote = $this->sentQuote();
        $quote->application->forceFill(['selected_quote_id' => null])->save();

        $this->get($quote->acceptanceUrl())->assertSee('This proposal is not available')->assertDontSee('Accept and sign');

        $quote->application->forceFill(['selected_quote_id' => $quote->id, 'status' => 'cancelled'])->save();
        $this->get($quote->acceptanceUrl())->assertSee('This proposal is not available');
    }

    public function test_the_language_is_kept_when_the_client_switches_it(): void
    {
        $quote = $this->sentQuote();

        Livewire::test(ProposalAccept::class, ['token' => $quote->acceptance_token])
            ->call('switchLocale', 'es')
            ->assertSee('Propuesta formal de seguro')
            ->assertSee('Aceptar y firmar');
    }
}
