<?php

namespace Tests\Feature;

use App\Livewire\ApplyForm;
use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class LinkSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create(['role' => 'agent', 'name' => 'Ana Agent']);
    }

    private function application(bool $withDriver = false): Application
    {
        $application = Application::createForClient(Client::factory()->create(['company_name' => 'Acme Freight LLC']), $this->agent);

        if ($withDriver) {
            $application->drivers()->create(['driver_name' => 'SECRETDRIVER Smith', 'cdl_number' => 'D1234567890']);
        }

        return $application->refresh();
    }

    public function test_a_new_application_link_has_a_deadline_and_a_pin(): void
    {
        $application = $this->application();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $application->link_pin);
        $this->assertTrue($application->link_expires_at->between(now()->addDays(29), now()->addDays(31)));
        $this->assertSame('active', $application->linkStatus());
        $this->assertNotSame($application->link_pin, $application->getRawOriginal('link_pin'), 'the PIN is stored encrypted');
    }

    public function test_an_expired_or_revoked_link_shows_a_notice_and_none_of_the_data(): void
    {
        $application = $this->application(withDriver: true);

        $application->forceFill(['link_expires_at' => now()->subMinute()])->save();
        $this->get(route('apply.show', $application->token))
            ->assertOk()
            ->assertSee('This link has expired')
            ->assertDontSee('Acme Freight LLC')
            ->assertDontSee('SECRETDRIVER')
            ->assertDontSee('Sign and submit');

        $application->forceFill(['link_expires_at' => now()->addDay(), 'link_revoked_at' => now()])->save();
        $this->get(route('apply.show', $application->token))->assertSee('This link is no longer active')->assertDontSee('SECRETDRIVER');
    }

    public function test_staff_still_open_the_link_of_an_expired_application(): void
    {
        $application = $this->application();
        $application->forceFill(['link_expires_at' => now()->subDay()])->save();

        $this->actingAs($this->agent)->get(route('apply.show', $application->token))->assertOk()->assertSee('Applicant Information');
    }

    public function test_a_link_that_expires_while_the_client_has_the_page_open_cannot_sign(): void
    {
        $application = $this->application();
        $page = Livewire::test(ApplyForm::class, ['token' => $application->token]);

        $application->forceFill(['link_revoked_at' => now()])->save();

        $page->set('signerName', 'John')->set('disclosureAccepted', true)
            ->set('signatureData', 'data:image/png;base64,'.base64_encode('x'))
            ->call('sign')
            ->assertForbidden();

        $this->assertSame('created', $application->fresh()->status);
    }

    public function test_renewing_the_link_kills_the_old_address_and_pin(): void
    {
        $application = $this->application();
        $oldToken = $application->token;
        $oldPin = $application->link_pin;
        $application->revokeLink();

        $application->renewLink();
        $application->refresh();

        $this->assertNotSame($oldToken, $application->token);
        $this->assertNotSame($oldPin, $application->link_pin);
        $this->assertSame('active', $application->linkStatus());
        $this->get(route('apply.show', $oldToken))->assertNotFound();
        $this->get(route('apply.show', $application->token))->assertOk();
        $this->assertTrue(ActivityLog::where('event', 'link_issued')->exists());
        $this->assertTrue(ActivityLog::where('event', 'link_revoked')->exists());
    }

    public function test_driver_data_is_behind_a_pin(): void
    {
        $application = $this->application(withDriver: true);
        $url = route('apply.show', $application->token);

        // The PIN screen is all the client gets: no company, no driver, nothing in the page state.
        $this->get($url)
            ->assertOk()
            ->assertSee('Enter your PIN')
            ->assertDontSee('Acme Freight LLC')
            ->assertDontSee('SECRETDRIVER')
            ->assertDontSee('D1234567890')
            ->assertDontSee('Sign and submit');

        $page = Livewire::test(ApplyForm::class, ['token' => $application->token]);
        $page->set('pin', '000000')->call('verifyPin')->assertSee('The PIN is not correct');
        $page->assertDontSee('SECRETDRIVER');

        $page->set('pin', $application->link_pin)->call('verifyPin')
            ->assertDontSee('Enter your PIN')
            ->assertSee('Review your information')
            ->assertSee('SECRETDRIVER')
            ->assertSee('Sign and submit');
    }

    public function test_no_pin_is_asked_until_there_are_drivers(): void
    {
        $application = $this->application();

        $this->get(route('apply.show', $application->token))->assertOk()->assertDontSee('Enter your PIN')->assertSee('Sign and submit');
    }

    public function test_staff_are_never_asked_for_the_pin(): void
    {
        $application = $this->application(withDriver: true);

        $this->actingAs($this->agent)->get(route('apply.show', $application->token))->assertOk()->assertDontSee('Enter your PIN');
    }

    public function test_wrong_pins_are_limited_even_if_the_right_one_comes_next(): void
    {
        $application = $this->application(withDriver: true);
        RateLimiter::clear('apply-pin:'.$application->id.':127.0.0.1');
        $page = Livewire::test(ApplyForm::class, ['token' => $application->token]);

        foreach (range(1, config('vantins.pin_attempts')) as $ignored) {
            $page->set('pin', '111111')->call('verifyPin');
        }

        $page->set('pin', $application->link_pin)->call('verifyPin')
            ->assertSee('Too many attempts')
            ->assertDontSee('SECRETDRIVER');
    }

    public function test_the_pin_can_be_switched_off(): void
    {
        config(['vantins.link_pin' => false]);
        $application = $this->application(withDriver: true);

        $this->get(route('apply.show', $application->token))->assertDontSee('Enter your PIN')->assertSee('SECRETDRIVER');
    }

    public function test_the_pdfs_obey_the_same_rules(): void
    {
        $application = $this->application(withDriver: true);
        // The branded PDF exists only once the client has signed.
        $application->forceFill(['signature_path' => 'signatures/x.png'])->save();
        $application->markStatus('signed');
        $pdf = route('applications.pdf', $application->token);

        // Without the PIN the client is sent to the page that asks for it.
        $this->get($pdf)->assertRedirect(route('apply.show', $application->token));

        // With it, the summary opens...
        Livewire::test(ApplyForm::class, ['token' => $application->token])->set('pin', $application->link_pin)->call('verifyPin');
        $this->get($pdf)->assertOk()->assertHeader('content-type', 'application/pdf');

        // A link that is not yet signed has no PDF at all (it would show data the client has not agreed to).
        $unsigned = $this->application();
        $this->actingAs($this->agent)->get(route('applications.pdf', $unsigned->token))->assertForbidden();
        auth()->logout();

        // Staff download regardless.
        $this->actingAs($this->agent)->get($pdf)->assertOk();
    }

    public function test_the_pin_given_for_an_old_address_does_not_open_the_new_one(): void
    {
        $application = $this->application(withDriver: true);
        Livewire::test(ApplyForm::class, ['token' => $application->token])->set('pin', $application->link_pin)->call('verifyPin');

        $application->renewLink();

        $this->get(route('apply.show', $application->fresh()->token))->assertSee('Enter your PIN')->assertDontSee('SECRETDRIVER');
    }

    public function test_the_pin_never_reaches_the_audit_trail(): void
    {
        $this->actingAs($this->agent);
        $application = $this->application();
        $pin = $application->link_pin;
        $application->renewLink();

        $dump = json_encode(ActivityLog::all()->toArray());
        $this->assertStringNotContainsString($pin, $dump);
        $this->assertStringNotContainsString($application->fresh()->link_pin, $dump);
    }
}
