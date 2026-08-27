<?php

namespace Tests\Feature;

use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TotalPremiumTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_policy_premium_is_the_sum_of_coverage_premiums(): void
    {
        $app = Application::create(['company_name' => 'Acme']);

        $app->coverages()->create(['coverage' => 'Liability', 'premium' => 1200.50]);
        $app->coverages()->create(['coverage' => 'Physical Damage', 'premium' => 800]);

        $this->assertEquals(2000.50, $app->fresh()->total_policy_premium);

        // Removing a coverage updates the total.
        $app->coverages()->where('coverage', 'Liability')->first()->delete();
        $this->assertEquals(800, $app->fresh()->total_policy_premium);

        // Removing the last one clears it.
        $app->coverages()->first()->delete();
        $this->assertNull($app->fresh()->total_policy_premium);
    }
}
