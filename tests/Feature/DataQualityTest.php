<?php

namespace Tests\Feature;

use App\Livewire\ApplyForm;
use App\Models\Application;
use App\Models\User;
use App\Support\DataQuality;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DataQualityTest extends TestCase
{
    use RefreshDatabase;

    private function advisor(): User
    {
        return User::factory()->create(['role' => 'agent', 'locale' => 'es']);
    }

    public function test_a_driver_born_in_2014_is_blocked(): void
    {
        $errors = DataQuality::validateRows('drivers', [['driver_name' => 'Kid', 'dob' => '2014-05-01']]);

        $this->assertArrayHasKey('drivers.0.dob', $errors);
    }

    public function test_driver_date_relationships_are_checked(): void
    {
        $errors = DataQuality::validateRows('drivers', [[
            'dob' => '1990-01-01',
            'cdl_issue_date' => '2005-01-01',   // before turning 18
            'cdl_expiry_date' => '2004-01-01',  // before the issue date
            'date_of_hire' => '1980-01-01',     // before birth
        ]]);

        $this->assertArrayHasKey('drivers.0.cdl_issue_date', $errors);
        $this->assertArrayHasKey('drivers.0.cdl_expiry_date', $errors);
        $this->assertArrayHasKey('drivers.0.date_of_hire', $errors);
    }

    public function test_a_realistic_driver_passes(): void
    {
        $errors = DataQuality::validateRows('drivers', [[
            'driver_name' => 'John Doe', 'dob' => '1985-03-10', 'cdl_number' => 'D123-4567-8901',
            'cdl_issue_date' => '2010-06-01', 'cdl_expiry_date' => now()->addYears(3)->format('Y-m-d'),
            'date_of_hire' => '2020-01-15',
        ]]);

        $this->assertSame([], $errors);
    }

    public function test_vin_rules(): void
    {
        $bad = fn (array $row) => DataQuality::validateRows('vehicles', [$row]);

        $this->assertArrayHasKey('vehicles.0.vin', $bad(['year' => '2020', 'vin' => 'SHORT']));
        $this->assertArrayHasKey('vehicles.0.vin', $bad(['year' => '2020', 'vin' => '1HGCM82633A00435O'])); // letter O
        $this->assertSame([], $bad(['year' => '2020', 'vin' => '1HGCM82633A004352']));
        $this->assertSame([], $bad(['year' => '1975', 'vin' => 'ABC1234'])); // pre-1981 serials are shorter
    }

    public function test_vin_check_digit_is_only_a_warning(): void
    {
        $this->assertTrue(DataQuality::vinCheckDigitOk('1HGCM82633A004352'));
        $this->assertFalse(DataQuality::vinCheckDigitOk('1HGCM82633A004353'));

        $row = ['year' => '2020', 'vin' => '1HGCM82633A004353'];
        $this->assertSame([], DataQuality::validateRows('vehicles', [$row]));
        $this->assertNotEmpty(DataQuality::vehicleWarnings($row));
    }

    public function test_zip_and_physical_damage_rules(): void
    {
        $errors = DataQuality::validateRows('vehicles', [[
            'garaging_zip' => '12', 'has_physical_damage' => true,
        ]]);

        $this->assertArrayHasKey('vehicles.0.garaging_zip', $errors);
        $this->assertArrayHasKey('vehicles.0.physical_damage_value', $errors);
        $this->assertArrayHasKey('vehicles.0.physical_damage_deductible', $errors);

        $this->assertSame([], DataQuality::validateRows('vehicles', [[
            'garaging_zip' => '33130', 'has_physical_damage' => true,
            'physical_damage_value' => 85000, 'physical_damage_deductible' => 2500,
        ]]));
    }

    public function test_soft_warnings_for_young_driver_and_expired_cdl(): void
    {
        $warnings = DataQuality::driverWarnings([
            'dob' => now()->subYears(19)->format('Y-m-d'),
            'cdl_number' => 'ABC12345',
            'cdl_expiry_date' => now()->subDay()->format('Y-m-d'),
            'cdl_issue_date' => now()->subYear()->format('Y-m-d'),
        ]);

        $this->assertCount(2, $warnings);
    }

    public function test_advisor_cannot_advance_with_an_impossible_driver(): void
    {
        $app = Application::create(['company_name' => 'Acme', 'locale' => 'es']);

        Livewire::actingAs($this->advisor())
            ->test(ApplyForm::class, ['token' => $app->token])
            ->set('step', 2)
            ->set('drivers', [['driver_name' => 'Kid', 'dob' => '2014-05-01']])
            ->call('next')
            ->assertHasErrors(['drivers.0.dob'])
            ->assertSet('step', 2);

        $this->assertSame(0, $app->drivers()->count());
    }

    public function test_new_fields_are_saved_and_reloaded(): void
    {
        $app = Application::create(['company_name' => 'Acme']);

        Livewire::actingAs($this->advisor())
            ->test(ApplyForm::class, ['token' => $app->token])
            ->set('step', 2)
            ->set('drivers', [['driver_name' => 'John', 'dob' => '1985-03-10', 'cdl_issue_date' => '2010-06-01', 'cdl_expiry_date' => '2030-06-01']])
            ->call('next')
            ->assertHasNoErrors()
            ->set('vehicles', [['year' => '2021', 'make' => 'Volvo', 'garaging_zip' => '33130', 'has_physical_damage' => true, 'physical_damage_value' => '90000', 'physical_damage_deductible' => '2500']])
            ->call('next')
            ->assertHasNoErrors();

        $driver = $app->drivers()->first();
        $this->assertSame('2010-06-01', $driver->cdl_issue_date->format('Y-m-d'));
        $this->assertSame('2030-06-01', $driver->cdl_expiry_date->format('Y-m-d'));

        $vehicle = $app->vehicles()->first();
        $this->assertSame('33130', $vehicle->garaging_zip);
        $this->assertTrue($vehicle->has_physical_damage);
        $this->assertEquals(2500, $vehicle->physical_damage_deductible);

        // Reopening shows plain Y-m-d strings for the date inputs.
        Livewire::actingAs($this->advisor())
            ->test(ApplyForm::class, ['token' => $app->token])
            ->assertSet('drivers.0.cdl_expiry_date', '2030-06-01');
    }

    public function test_language_is_kept_on_every_livewire_request(): void
    {
        $app = Application::create(['company_name' => 'Acme', 'locale' => 'es']);

        $component = Livewire::actingAs($this->advisor())->test(ApplyForm::class, ['token' => $app->token]);

        // Simulate a later request that starts in the default locale.
        app()->setLocale('en');

        $component->set('step', 2)->call('addRow', 'drivers');
        $this->assertSame('es', app()->getLocale());
        $component->assertSee('Lista de Conductores')->assertSee('Fecha de Vencimiento del CDL');
    }

    public function test_validation_messages_follow_the_language(): void
    {
        app()->setLocale('es');
        $es = DataQuality::validateRows('drivers', [['dob' => '2014-05-01']]);
        app()->setLocale('en');
        $en = DataQuality::validateRows('drivers', [['dob' => '2014-05-01']]);

        $this->assertStringContainsString('al menos', $es['drivers.0.dob']);
        $this->assertStringContainsString('at least', $en['drivers.0.dob']);
    }

    public function test_demo_flag_shows_on_the_client_page(): void
    {
        $app = Application::create(['company_name' => 'Acme', 'is_demo' => true]);

        $this->get("/apply/{$app->token}")->assertSee(__('app.demo_notice'));
    }

    public function test_format_is_unambiguous_in_both_languages(): void
    {
        $this->assertSame('Oct 2, 2026', Format::date('2026-10-02', 'en'));
        $this->assertSame('2 oct 2026', Format::date('2026-10-02', 'es'));
        $this->assertSame('$1,234.50', Format::money(1234.5));
        $this->assertSame('—', Format::money(null));
    }
}
