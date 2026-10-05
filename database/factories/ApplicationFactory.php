<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'company_name' => fake()->company(),
            'email' => fake()->safeEmail(),
            'locale' => 'en',
            'status' => 'created',
            'agency_name' => config('vantins.agency_name'),
            'agency_phone' => config('vantins.agency_phone'),
        ];
    }
}
