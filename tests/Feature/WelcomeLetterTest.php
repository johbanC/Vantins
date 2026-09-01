<?php

namespace Tests\Feature;

use App\Filament\Resources\ApplicationResource\Pages\EditApplication;
use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class WelcomeLetterTest extends TestCase
{
    use RefreshDatabase;

    private function signed(array $attributes = []): Application
    {
        $app = Application::create(array_merge([
            'company_name' => 'Acme Freight LLC',
            'signer_name' => 'Carlos Perez',
            'status' => 'created',
        ], $attributes));

        $app->forceFill(['signature_path' => 'signatures/'.$app->token.'.png'])->save();
        $app->markStatus('signed');

        return $app->fresh();
    }

    public function test_letter_is_not_available_until_the_client_has_signed(): void
    {
        $app = Application::create(['company_name' => 'Acme', 'status' => 'created']);

        $this->assertFalse($app->canSendWelcomeLetter());
        $this->get('/applications/'.$app->token.'/welcome-letter')->assertForbidden();
        $this->assertNull($app->fresh()->welcome_letter_sent_at);
    }

    public function test_letter_is_generated_once_signed_and_stamps_the_send_date(): void
    {
        $app = $this->signed();
        $this->assertNull($app->welcome_letter_sent_at);

        Carbon::setTestNow('2026-09-01 10:00:00');
        $this->get('/applications/'.$app->token.'/welcome-letter/es')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $stamped = $app->fresh()->welcome_letter_sent_at;
        $this->assertNotNull($stamped);
        $this->assertSame('2026-09-01 10:00:00', $stamped->format('Y-m-d H:i:s'));

        // A later download keeps the original date.
        Carbon::setTestNow('2026-09-20 15:30:00');
        $this->get('/applications/'.$app->token.'/welcome-letter/en')->assertOk();
        $this->assertSame('2026-09-01 10:00:00', $app->fresh()->welcome_letter_sent_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_recipient_name_prefers_the_signer_then_representative_then_company(): void
    {
        $this->assertSame('Carlos Perez', $this->signed()->recipientName());

        $noSigner = Application::create(['company_name' => 'Acme Co', 'company_representative' => 'Rep Name', 'status' => 'created']);
        $this->assertSame('Rep Name', $noSigner->recipientName());

        $companyOnly = Application::create(['company_name' => 'Acme Co', 'status' => 'created']);
        $this->assertSame('Acme Co', $companyOnly->recipientName());
    }

    public function test_panel_action_is_visible_but_disabled_until_signed(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);

        $open = Application::create(['company_name' => 'Acme', 'status' => 'created']);
        Livewire::actingAs($staff)
            ->test(EditApplication::class, ['record' => $open->getKey()])
            ->assertActionVisible('welcomeLetter')
            ->assertActionDisabled('welcomeLetter');

        $signed = $this->signed();
        Livewire::actingAs($staff)
            ->test(EditApplication::class, ['record' => $signed->getKey()])
            ->assertActionVisible('welcomeLetter')
            ->assertActionEnabled('welcomeLetter');
    }

    public function test_welcome_letter_date_is_still_writable_on_a_locked_application(): void
    {
        $app = $this->signed();

        // markWelcomeLetterSent() must not be blocked by the "frozen" guard.
        $app->markWelcomeLetterSent();

        $this->assertNotNull($app->fresh()->welcome_letter_sent_at);
    }
}
