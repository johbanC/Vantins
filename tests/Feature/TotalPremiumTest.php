<?php

namespace Tests\Feature;

use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TotalPremiumTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_policy_premium_is_derived_from_the_payment_plan(): void
    {
        $app = Application::create(['company_name' => 'Acme']);

        $app->update([
            'down_payment' => 4000,
            'number_of_payments' => 10,
            'monthly_payment' => 1411.95,
        ]);

        // 4000 + 1411.95 * 10 = 18119.50
        $this->assertEquals(18119.50, $app->fresh()->total_policy_premium);
    }

    public function test_down_payment_only_still_sets_the_total(): void
    {
        $app = Application::create(['company_name' => 'Acme']);

        $app->update(['down_payment' => 2500]);

        $this->assertEquals(2500, $app->fresh()->total_policy_premium);
    }

    public function test_empty_plan_leaves_the_total_null(): void
    {
        $app = Application::create(['company_name' => 'Acme']);

        $app->update(['down_payment' => 4000, 'number_of_payments' => 10, 'monthly_payment' => 1411.95]);
        $app->update(['down_payment' => null, 'number_of_payments' => null, 'monthly_payment' => null]);

        $this->assertNull($app->fresh()->total_policy_premium);
    }
}
