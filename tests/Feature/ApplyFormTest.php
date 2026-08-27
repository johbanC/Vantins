<?php

namespace Tests\Feature;

use App\Livewire\ApplyForm;
use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApplyFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_loads_for_a_valid_token(): void
    {
        $app = Application::create(['company_name' => 'Acme']);

        $this->get("/apply/{$app->token}")->assertOk()->assertSee('Applicant Information');
    }

    public function test_unknown_token_is_404(): void
    {
        $this->get('/apply/not-a-real-token')->assertNotFound();
    }

    public function test_oversized_row_values_are_truncated_not_500(): void
    {
        $app = Application::create(['company_name' => 'Acme']);

        Livewire::test(ApplyForm::class, ['token' => $app->token])
            ->set('vehicles', [[
                'year' => str_repeat('X', 200),
                'vin' => str_repeat('Y', 200),
                'make' => str_repeat('Z', 500),
            ]])
            ->call('next')
            ->assertHasNoErrors();

        $vehicle = $app->vehicles()->first();
        $this->assertNotNull($vehicle);
        $this->assertLessThanOrEqual(20, mb_strlen($vehicle->year));
        $this->assertLessThanOrEqual(64, mb_strlen($vehicle->vin));
    }
}
