<?php

namespace Tests\Feature;

use App\Filament\Resources\ApplicationResource\Pages\EditApplication;
use App\Filament\Resources\ApplicationResource\RelationManagers\TrailersRelationManager;
use App\Filament\Resources\ApplicationResource\RelationManagers\VehiclesRelationManager;
use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleYearTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A 4-digit year used to fail with "the year cannot be greater than 4": a maxLength(4) next to the
     * integer rule makes Laravel compare the VALUE (2026) with 4.
     */
    public function test_a_four_digit_year_is_accepted_on_vehicles_and_trailers(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'locale' => 'es']);
        $application = Application::create(['company_name' => 'Acme', 'status' => 'created']);

        foreach ([VehiclesRelationManager::class, TrailersRelationManager::class] as $manager) {
            Livewire::actingAs($admin)
                ->test($manager, ['ownerRecord' => $application, 'pageClass' => EditApplication::class])
                ->callTableAction('create', data: ['year' => '2026', 'make' => 'Volvo'])
                ->assertHasNoTableActionErrors();
        }

        $this->assertSame(1, $application->vehicles()->where('year', '2026')->count());
        $this->assertSame(1, $application->trailers()->where('year', '2026')->count());
    }

    public function test_an_unrealistic_year_is_still_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'locale' => 'es']);
        $application = Application::create(['company_name' => 'Acme', 'status' => 'created']);

        Livewire::actingAs($admin)
            ->test(VehiclesRelationManager::class, ['ownerRecord' => $application, 'pageClass' => EditApplication::class])
            ->callTableAction('create', data: ['year' => '1900'])
            ->assertHasTableActionErrors(['year']);
    }
}
