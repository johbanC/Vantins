<?php

namespace Tests\Feature;

use App\Filament\Resources\ApplicationResource\Pages\EditApplication;
use App\Filament\Resources\ApplicationResource\RelationManagers\CoveragesRelationManager;
use App\Filament\Resources\CoverageTypeResource\Pages\EditCoverageType;
use App\Livewire\ApplyForm;
use App\Models\Application;
use App\Models\Client;
use App\Models\Coverage;
use App\Models\CoverageType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CoverageCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'locale' => 'es']);
    }

    private function application(User $advisor): Application
    {
        return Application::createForClient(Client::factory()->create(), $advisor);
    }

    private function form(Application $app, User $advisor)
    {
        return Livewire::actingAs($advisor)->test(ApplyForm::class, ['token' => $app->token])->set('step', 5);
    }

    public function test_the_catalog_ships_with_the_agreed_coverages_and_amounts(): void
    {
        $catalog = CoverageType::catalog();

        $this->assertSame(
            ['auto_liability', 'motor_truck_cargo', 'physical_damage', 'general_liability', 'reefer_breakdown', 'trailer_interchange', 'umbrella', 'um', 'other'],
            $catalog->keys()->all()
        );
        $this->assertFalse($catalog->has('p_and_p'), 'P&P stays out until its meaning is confirmed');
        $this->assertSame([750000, 1000000, 1500000], $catalog['auto_liability']->options('limit'));
        $this->assertSame([50000, 100000, 250000], $catalog['motor_truck_cargo']->options('limit'));
        $this->assertSame([1000, 1500, 2000, 2500], $catalog['motor_truck_cargo']->options('deductible'));
        $this->assertSame([2000000], $catalog['general_liability']->options('aggregate'));
        $this->assertSame('Auto Liability', $catalog['auto_liability']->name('en'));
        $this->assertSame('Daño Físico', $catalog['physical_damage']->name('es'));
    }

    public function test_ticking_a_coverage_adds_it_once_and_unticking_removes_it(): void
    {
        $admin = $this->admin();
        $form = $this->form($this->application($admin), $admin);

        $form->call('toggleCoverage', 'motor_truck_cargo')->assertCount('coverages', 1);
        $form->call('toggleCoverage', 'auto_liability')->assertCount('coverages', 2);
        $form->call('toggleCoverage', 'motor_truck_cargo')->assertCount('coverages', 1);
        $form->call('toggleCoverage', 'auto_liability')->assertCount('coverages', 0);

        // "Other" can be added repeatedly, every other coverage cannot duplicate.
        $form->call('toggleCoverage', 'other')->call('toggleCoverage', 'other')->assertCount('coverages', 2);
        $form->call('toggleCoverage', 'p_and_p')->assertStatus(422);
    }

    public function test_only_the_fields_of_the_ticked_coverage_are_shown(): void
    {
        $admin = $this->admin();
        $app = $this->application($admin);
        $app->update(['locale' => 'es']);
        $form = $this->form($app, $admin);

        $form->call('toggleCoverage', 'auto_liability')
            ->assertSee('Límite')
            ->assertDontSee('Deducible de')   // no deductible field for Auto Liability
            ->assertDontSee('Agregado');

        $form->call('toggleCoverage', 'general_liability')->assertSee('Agregado');
    }

    public function test_required_fields_and_catalog_amounts_are_enforced(): void
    {
        $admin = $this->admin();
        $app = $this->application($admin);
        $app->vehicles()->create(['year' => '2021', 'make' => 'Volvo']);

        $this->form($app, $admin)
            ->call('toggleCoverage', 'motor_truck_cargo')
            ->call('next')
            ->assertHasErrors(['coverages.0.limit_amount', 'coverages.0.deductible'])
            ->set('coverages.0.limit_amount', '60000')           // not one of 50K / 100K / 250K
            ->set('coverages.0.deductible', '1500')
            ->call('next')
            ->assertHasErrors(['coverages.0.limit_amount'])
            ->assertHasNoErrors(['coverages.0.deductible'])
            ->set('coverages.0.limit_amount', '100000')
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 6);
    }

    public function test_coverages_that_need_units_underlying_or_a_name(): void
    {
        $admin = $this->admin();
        $app = $this->application($admin);
        $app->vehicles()->create(['year' => '2021', 'make' => 'Volvo']);

        $this->form($app, $admin)
            ->call('toggleCoverage', 'physical_damage')
            ->call('toggleCoverage', 'trailer_interchange')
            ->call('toggleCoverage', 'umbrella')
            ->call('toggleCoverage', 'other')
            ->call('next')
            ->assertHasErrors([
                'coverages.0.vehicle_ids', 'coverages.0.deductible',
                'coverages.1.trailer_ids', 'coverages.1.limit_amount', 'coverages.1.deductible',
                'coverages.2.underlying_coverage',
                'coverages.3.custom_name',
            ]);
    }

    public function test_general_liability_aggregate_cannot_be_lower_than_the_limit(): void
    {
        $admin = $this->admin();
        CoverageType::where('key', 'general_liability')->update(['aggregate_options' => [500000, 2000000]]);

        $this->form($this->application($admin), $admin)
            ->call('toggleCoverage', 'general_liability')
            ->set('coverages.0.limit_amount', '1000000')
            ->set('coverages.0.aggregate_limit', '500000')
            ->call('next')
            ->assertHasErrors(['coverages.0.aggregate_limit']);
    }

    public function test_a_valid_selection_is_saved_with_its_details_and_other_goes_to_review(): void
    {
        $admin = $this->admin();
        $app = $this->application($admin);
        $vehicle = $app->vehicles()->create(['year' => '2021', 'make' => 'Volvo', 'vin' => '1HGCM82633A004352']);

        $form = Livewire::actingAs($admin)->test(ApplyForm::class, ['token' => $app->token])
            ->set('step', 3)
            ->call('next')                                  // keeps the saved vehicle (it carries its id)
            ->set('step', 5)
            ->call('toggleCoverage', 'auto_liability')
            ->call('toggleCoverage', 'physical_damage')
            ->call('toggleCoverage', 'other')
            ->set('coverages.0.limit_amount', '1000000')
            ->set('coverages.1.vehicle_ids', [(string) $vehicle->id])
            ->set('coverages.1.deductible', '2500')
            ->set('coverages.2.custom_name', 'Hired Auto')
            ->set('coverages.2.limit_amount', '75000')
            ->set('coverages.2.deductible', '1000')
            ->call('next')
            ->assertHasNoErrors();

        $rows = $app->coverages()->with('type')->get();
        $this->assertCount(3, $rows);

        [$liability, $physical, $other] = $rows;
        $this->assertSame('auto_liability', $liability->type->key);
        $this->assertSame('Auto Liability', $liability->coverage);
        $this->assertSame('1000000', $liability->limit_amount);
        $this->assertFalse($liability->needs_review);

        $this->assertSame([$vehicle->id], $physical->details['vehicle_ids']);
        $this->assertStringContainsString('1HGCM82633A004352', implode(' ', $physical->detailLines()));

        $this->assertTrue($other->needs_review);
        $this->assertSame('Hired Auto', $other->displayName('en'));

        // Saving again keeps the same rows (updated in place, not recreated).
        $ids = $rows->pluck('id')->all();
        $form->set('step', 5)->call('next');
        $this->assertSame($ids, $app->coverages()->pluck('id')->all());
    }

    public function test_the_client_sees_the_uniform_name_in_the_language_of_the_document(): void
    {
        $admin = $this->admin();
        $app = $this->application($admin);
        $type = CoverageType::where('key', 'auto_liability')->first();
        $app->coverages()->create(['coverage_type_id' => $type->id, 'coverage' => 'Auto Liability', 'limit_amount' => '1000000']);

        $app->update(['locale' => 'es']);
        $this->get("/apply/{$app->token}")->assertSee('Responsabilidad Civil de Auto')->assertSee('$1,000,000.00');

        $app->update(['locale' => 'en']);
        $this->get("/apply/{$app->token}")->assertSee('Auto Liability');

        // The branded PDF exists only once the client has signed.
        $this->get("/applications/{$app->token}/pdf/es")->assertForbidden();
        $app->forceFill(['signature_path' => 'signatures/x.png'])->save();
        $app->markStatus('signed');
        $this->get("/applications/{$app->token}/pdf/es")->assertOk();
    }

    public function test_the_panel_form_adds_a_coverage_and_refuses_a_duplicate(): void
    {
        $admin = $this->admin();
        $app = $this->application($admin);
        $cargo = CoverageType::where('key', 'motor_truck_cargo')->first();

        $manager = Livewire::actingAs($admin)->test(CoveragesRelationManager::class, [
            'ownerRecord' => $app,
            'pageClass' => EditApplication::class,
        ]);

        $manager->callTableAction('create', data: [
            'coverage_type_id' => $cargo->id, 'limit_amount' => '100000', 'deductible' => '2500',
        ])->assertHasNoTableActionErrors();

        $saved = $app->coverages()->firstOrFail();
        $this->assertSame('Motor Truck Cargo', $saved->coverage);
        $this->assertSame('100000', $saved->limit_amount);

        $manager->callTableAction('create', data: [
            'coverage_type_id' => $cargo->id, 'limit_amount' => '50000', 'deductible' => '1000',
        ])->assertHasTableActionErrors(['coverage_type_id']);

        $this->assertSame(1, $app->coverages()->count());
    }

    public function test_admins_edit_the_catalog_amounts_and_nobody_else_can(): void
    {
        $admin = $this->admin();
        $cargo = CoverageType::where('key', 'motor_truck_cargo')->first();

        Livewire::actingAs($admin)->test(EditCoverageType::class, ['record' => $cargo->id])
            ->fillForm(['limit_options' => ['100000', '$50,000', '250000', '50000']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([50000, 100000, 250000], $cargo->fresh()->options('limit'));

        $this->actingAs($admin)->get('/admin/coverage-types')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'agent']))->get('/admin/coverage-types')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'viewer']))->get('/admin/coverage-types')->assertForbidden();
    }

    public function test_formatting_of_old_free_text_limits_is_kept(): void
    {
        $this->assertSame('$1,000,000.00', Coverage::formatAmount('1000000'));
        $this->assertSame('CSL', Coverage::formatAmount('CSL'));
        $this->assertSame('—', Coverage::formatAmount(null));
    }
}
