<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::firstOrCreate(
            ['email' => 'smoke@vantins.com'],
            ['name' => 'Smoke', 'password' => bcrypt('secret'), 'role' => 'admin', 'locale' => 'es']
        );
    }

    public function test_dashboard_loads_with_stats_and_no_filament_branding(): void
    {
        $this->actingAs($this->staff())->get('/admin')
            ->assertOk()
            ->assertSee('application-stats')      // custom stats widget is on the dashboard
            ->assertDontSee('filament-info-widget'); // Filament branding widget removed
    }

    public function test_applications_list_loads(): void
    {
        $this->actingAs($this->staff())->get('/admin/applications')->assertOk();
    }

    public function test_create_page_loads(): void
    {
        $this->actingAs($this->staff())->get('/admin/applications/create')->assertOk();
    }

    public function test_edit_page_loads(): void
    {
        $app = Application::create(['company_name' => 'Smoke Co', 'email' => 'c@x.com']);
        $this->actingAs($this->staff())->get("/admin/applications/{$app->getKey()}/edit")->assertOk();
        $app->forceDelete();
    }

    public function test_create_fills_agency_from_config_and_current_user(): void
    {
        $user = $this->staff();

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\ApplicationResource\Pages\CreateApplication::class)
            ->fillForm(['company_name' => 'Acme Freight', 'email' => 'ops@acme.test'])
            ->call('create')
            ->assertHasNoFormErrors();

        $app = Application::where('company_name', 'Acme Freight')->firstOrFail();

        $this->assertSame(config('vantins.agency_name'), $app->agency_name);
        $this->assertSame(config('vantins.agency_phone'), $app->agency_phone);
        $this->assertSame($user->name, $app->contact_agent_name);
        $this->assertSame('created', $app->status);
    }

    public function test_pdf_is_available_in_both_languages(): void
    {
        $app = Application::create(['company_name' => 'Acme', 'locale' => 'es']);
        $app->coverages()->create(['coverage' => 'Liability', 'premium' => 1000]);

        foreach (['en', 'es', null] as $locale) {
            $url = '/applications/'.$app->token.'/pdf'.($locale ? "/{$locale}" : '');
            $this->get($url)
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }

        $this->get('/applications/'.$app->token.'/pdf/fr')->assertNotFound();
    }

    public function test_locale_switch_route(): void
    {
        $user = $this->staff();
        $this->actingAs($user)->get('/panel/locale/en')->assertRedirect();
        $this->assertSame('en', $user->fresh()->locale);
    }
}
