<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Carrier;
use App\Models\CoverageType;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'carrier_id' => Carrier::factory(),
            'product' => 'Commercial Auto',
            'stage' => 'lead',
            'qualification_note' => 'Owner operator, 3 trucks, needs coverage this month.',
            'next_step' => 'Send quote',
            'carrier_premium' => 12000,
            'fees' => 500,
            'producer_fee' => 750,
            'down_payment' => 3000,
            'number_of_payments' => 9,
            'installment_amount' => 1250,
            'expires_at' => now()->addDays(15)->toDateString(),
        ];
    }

    /** Ready to hand to the client: priced, with coverage lines. */
    public function priced(): static
    {
        return $this->afterCreating(function (Quote $quote) {
            $quote->coverages()->create(['coverage_type_id' => CoverageType::where('key', 'auto_liability')->value('id'), 'limit_amount' => 1000000, 'premium' => 9000]);
        });
    }
}
