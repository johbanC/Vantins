<?php

namespace Database\Factories;

use App\Models\CoverageType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoverageType>
 */
class CoverageTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name_en' => fake()->words(2, true),
            'name_es' => fake()->words(2, true),
            'limit_options' => [],
            'aggregate_options' => [],
            'deductible_options' => [],
            'is_active' => true,
            'sort_order' => 99,
        ];
    }
}
