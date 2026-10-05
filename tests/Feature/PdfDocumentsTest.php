<?php

namespace Tests\Feature;

use App\Filament\Resources\QuoteResource\Pages\ViewQuote;
use App\Livewire\ApplyForm;
use App\Livewire\ProposalAccept;
use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Carrier;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use App\Support\PdfDocuments;
use App\Support\QuotePipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PdfDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private const CARRIER = 'Quiet Mutual Insurance';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(PdfDocuments::DISK);
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Ana Admin', 'locale' => 'es']);
    }

    private function application(array $attributes = []): Application
    {
        $application = Application::createForClient(Client::factory()->create(), $this->admin);
        $application->update($attributes);

        return $application;
    }

    private function sentQuote(?Application $application = null): Quote
    {
        $application ??= $this->application();
        $quote = Quote::factory()->priced()->create([
            'application_id' => $application->id,
            'carrier_id' => Carrier::firstOrCreate(['name' => self::CARRIER])->id,
        ]);
        QuotePipeline::move($quote, 'quote_sent', [], $this->admin);

        return $quote->fresh();
    }

    /** The HTML a PDF is made of, to look for text without decoding the PDF itself. */
    private function html($pdf): string
    {
        return $pdf->getDomPDF()->outputHtml();
    }

    private function signApplication(Application $application): void
    {
        Livewire::test(ApplyForm::class, ['token' => $application->token])
            ->set('signerName', 'John Doe')
            ->set('disclosureAccepted', true)
            ->set('signatureData', 'data:image/png;base64,'.base64_encode('x'))
            ->call('sign')
            ->assertSet('done', 'signed');
    }

    public function test_the_three_documents_render_and_never_show_the_carrier_by_default(): void
    {
        $quote = $this->sentQuote();
        $quote->forceFill(['stage' => 'binder', 'binder_number' => 'BND-1', 'binder_effective_date' => '2026-11-01'])->save();
        $quote->refresh();

        foreach (['en', 'es'] as $locale) {
            $proposal = $this->html(PdfDocuments::proposal($quote, $locale));
            $binder = $this->html(PdfDocuments::binder($quote, $locale));

            $this->assertStringNotContainsString(self::CARRIER, $proposal);
            $this->assertStringNotContainsString(self::CARRIER, $binder);
        }

        $this->assertStringContainsString('Formal insurance proposal', $this->html(PdfDocuments::proposal($quote, 'en')));
        $this->assertStringContainsString('Propuesta formal de seguro', $this->html(PdfDocuments::proposal($quote, 'es')));
        $this->assertStringContainsString('Estimado', $this->html(PdfDocuments::proposal($quote, 'es')));
        $this->assertStringContainsString('Confirmado', $this->html(PdfDocuments::binder($quote, 'es')));
        $this->assertStringContainsString('BND-1', $this->html(PdfDocuments::binder($quote, 'en')));
    }

    public function test_the_money_is_broken_down_and_labelled_estimated_or_confirmed(): void
    {
        $quote = $this->sentQuote();
        $html = $this->html(PdfDocuments::proposal($quote, 'en'));

        foreach (['$12,000.00', '$500.00', '$750.00', '$13,250.00', '$3,000.00', '9 &times; $1,250.00', '$14,250.00'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString('Premium', $html);
        $this->assertStringContainsString('Fees and charges', $html);
        $this->assertStringContainsString('Agency fee', $html);
        $this->assertStringContainsString('Down Payment', $html);
        $this->assertStringContainsString('Estimated', $html);
        $this->assertStringNotContainsString('Confirmed', $html);
    }

    public function test_the_carrier_appears_on_the_binder_only_when_an_admin_authorised_it(): void
    {
        $quote = $this->sentQuote();
        $quote->forceFill(['stage' => 'binder', 'binder_number' => 'BND-1', 'binder_effective_date' => '2026-11-01'])->save();

        $this->assertStringNotContainsString(self::CARRIER, $this->html(PdfDocuments::binder($quote->fresh(), 'en')));

        $quote->update(['carrier_disclosed' => true]);
        $this->assertStringContainsString(self::CARRIER, $this->html(PdfDocuments::binder($quote->fresh(), 'en')));
        $this->assertStringNotContainsString(self::CARRIER, $this->html(PdfDocuments::proposal($quote->fresh(), 'en')), 'never on the proposal');

        // Authorised but no binder yet: still hidden.
        $other = $this->sentQuote();
        $other->update(['carrier_disclosed' => true]);
        $this->assertNull($other->fresh()->carrierNameForBinder());
    }

    public function test_only_admins_can_switch_the_carrier_disclosure(): void
    {
        $quote = $this->sentQuote();

        Livewire::actingAs($this->admin)->test(ViewQuote::class, ['record' => $quote->id])
            ->assertActionVisible('carrierDisclosure')
            ->callAction('carrierDisclosure');
        $this->assertTrue($quote->fresh()->carrier_disclosed);

        $agent = User::factory()->create(['role' => 'agent']);
        $owned = Quote::factory()->priced()->create([
            'application_id' => Application::createForClient(Client::factory()->create(), $agent)->id,
        ]);
        Livewire::actingAs($agent)->test(ViewQuote::class, ['record' => $owned->id])
            ->assertActionHidden('carrierDisclosure');
    }

    public function test_the_binder_does_not_exist_before_the_binder_stage(): void
    {
        $quote = $this->sentQuote();

        $this->actingAs($this->admin)->get(route('quotes.pdf', [$quote, 'binder', 'en']))->assertNotFound();
        $this->actingAs($this->admin)->get(route('quotes.pdf', [$quote, 'proposal', 'en']))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_generation_leaves_the_request_language_alone(): void
    {
        $application = $this->application();
        app()->setLocale('es');

        PdfDocuments::application($application, 'en');

        $this->assertSame('es', app()->getLocale());
    }

    public function test_a_long_application_stays_within_a_few_pages(): void
    {
        $application = $this->application();
        foreach (range(1, 6) as $i) {
            $application->drivers()->create(['driver_name' => "Driver {$i}", 'dob' => '1985-03-10', 'cdl_number' => 'D1234567890'.$i, 'cdl_issue_date' => '2010-01-01', 'cdl_expiry_date' => '2030-01-01']);
            $application->vehicles()->create(['year' => '2021', 'make' => 'Volvo VNL 860', 'vin' => '1HGCM82633A00435'.$i, 'garaging_zip' => '33130', 'stated_value' => 90000]);
        }

        $pdf = PdfDocuments::application($application->fresh(), 'es');
        $pdf->output();

        $pages = $pdf->getDomPDF()->getCanvas()->get_page_count();
        $this->assertLessThanOrEqual(3, $pages);
    }

    public function test_signing_the_application_keeps_the_exact_pdf_and_the_client_can_download_it(): void
    {
        $application = $this->application(['company_name' => 'Acme Freight']);

        $this->get(route('applications.signed', $application->token))->assertNotFound();   // not signed yet

        $this->signApplication($application);

        $application->refresh();
        $this->assertNotNull($application->pdf_path);
        Storage::disk(PdfDocuments::DISK)->assertExists($application->pdf_path);
        $stored = Storage::disk(PdfDocuments::DISK)->get($application->pdf_path);
        $this->assertStringStartsWith('%PDF', $stored);

        $this->get(route('applications.signed', $application->token))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Whatever happens to the record afterwards, the signed copy is the one that was signed.
        $application->update(['company_name' => 'Renamed Later LLC']);
        $this->get(route('applications.signed', $application->token));
        $this->assertSame($stored, Storage::disk(PdfDocuments::DISK)->get($application->fresh()->pdf_path));

        // The client sees the way to get it.
        $this->get(route('apply.show', $application->token))->assertSee(route('applications.signed', $application->token), false);
    }

    public function test_an_application_signed_before_this_existed_gets_its_copy_the_first_time(): void
    {
        $application = $this->application();
        $application->forceFill(['status' => 'signed', 'signer_name' => 'Old Client', 'disclosure_accepted_at' => now()])->save();
        $this->assertNull($application->pdf_path);

        $this->get(route('applications.signed', $application->token))->assertOk();

        $this->assertNotNull($application->fresh()->pdf_path);
    }

    public function test_the_client_downloads_the_open_proposal_and_later_the_signed_copy_and_the_binder(): void
    {
        $quote = $this->sentQuote();
        $token = $quote->acceptance_token;

        $this->get(route('proposal.pdf', [$token, 'en']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('proposal.signed', $token))->assertNotFound();      // not signed yet
        $this->get(route('proposal.binder', $token))->assertNotFound();

        Livewire::test(ProposalAccept::class, ['token' => $token])
            ->set('signerName', 'John Doe')
            ->set('disclosureAccepted', true)
            ->set('signatureData', 'data:image/png;base64,'.base64_encode('x'))
            ->call('sign')
            ->assertSet('done', 'accepted')
            ->assertSee(route('proposal.signed', $token), false);

        $quote->refresh();
        $this->assertNotNull($quote->accepted_pdf_path);
        Storage::disk(PdfDocuments::DISK)->assertExists($quote->accepted_pdf_path);
        $this->get(route('proposal.signed', $token))->assertOk()->assertHeader('content-type', 'application/pdf');

        // The binder is offered once it exists.
        $this->get(route('proposal.binder', $token))->assertNotFound();
        $quote->forceFill(['stage' => 'binder', 'binder_number' => 'BND-1', 'binder_effective_date' => '2026-11-01'])->save();
        $this->get(route('proposal.binder', [$token, 'es']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('proposal.show', $token))->assertSee(route('proposal.binder', [$token, 'en']), false);
    }

    public function test_revoked_or_unknown_links_download_nothing(): void
    {
        $quote = $this->sentQuote();
        $token = $quote->acceptance_token;
        $quote->revokeAcceptanceLink();

        $this->get(route('proposal.pdf', [$token, 'en']))->assertNotFound();
        $this->get(route('proposal.pdf', ['not-a-token', 'en']))->assertNotFound();
    }

    public function test_staff_downloads_follow_the_quote_permissions(): void
    {
        $quote = $this->sentQuote();
        $url = route('quotes.pdf', [$quote, 'proposal', 'es']);

        $this->get($url)->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['role' => 'agent']))->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'viewer']))->get($url)->assertOk();
        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    public function test_every_download_is_audited_including_the_clients(): void
    {
        $quote = $this->sentQuote();

        $this->get(route('proposal.pdf', [$quote->acceptance_token, 'en']))->assertOk();
        $this->actingAs($this->admin)->get(route('quotes.pdf', [$quote, 'proposal', 'es']))->assertOk();

        $logs = ActivityLog::where('event', 'downloaded')->where('subject_type', 'quote')->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertNull($logs[0]->user_id, 'the client has no user');
        $this->assertSame($this->admin->id, $logs[1]->user_id);
        $this->assertSame([null, 'proposal'], $logs[0]->changes['document']);
    }

    public function test_the_verification_page_knows_quote_documents(): void
    {
        $quote = $this->sentQuote();

        $this->get(route('verify', $quote->verification_code))
            ->assertOk()
            ->assertSee('Valid document')
            ->assertSee($quote->application->company_name)
            ->assertDontSee(self::CARRIER);

        $this->get(route('verify', 'NOSUCHCODE'))->assertSee('Not a valid document');
    }

    public function test_a_new_version_gets_its_own_code_and_no_signed_pdf(): void
    {
        $quote = $this->sentQuote();
        $quote->forceFill(['accepted_pdf_path' => 'x.pdf', 'carrier_disclosed' => true])->save();

        $new = $quote->fresh()->revise();

        $this->assertNotNull($new->verification_code);
        $this->assertNotSame($quote->verification_code, $new->verification_code);
        $this->assertNull($new->accepted_pdf_path);
        $this->assertFalse($new->carrier_disclosed, 'the disclosure decision is made again for every version');
    }
}
