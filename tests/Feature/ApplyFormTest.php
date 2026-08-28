<?php

namespace Tests\Feature;

use App\Livewire\ApplyForm;
use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ApplyFormTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'agent', 'locale' => 'es']);
    }

    public function test_client_link_loads_and_shows_the_review(): void
    {
        $app = Application::create(['company_name' => 'Acme']);

        $this->get("/apply/{$app->token}")
            ->assertOk()
            ->assertSee(__('app.review_title'))
            ->assertSee(__('app.sign_send'));
    }

    public function test_unknown_token_is_404(): void
    {
        $this->get('/apply/not-a-real-token')->assertNotFound();
    }

    public function test_client_cannot_edit_only_sign(): void
    {
        $app = Application::create(['company_name' => 'Acme']);

        Livewire::test(ApplyForm::class, ['token' => $app->token])
            ->assertSet('editable', false)
            ->call('next')
            ->assertStatus(403);
    }

    public function test_advisor_gets_the_editable_form(): void
    {
        $app = Application::create(['company_name' => 'Acme']);

        Livewire::actingAs($this->staff())
            ->test(ApplyForm::class, ['token' => $app->token])
            ->assertSet('editable', true)
            ->set('form.company_name', 'Acme Freight LLC')
            ->call('next')
            ->assertHasNoErrors();

        $this->assertSame('Acme Freight LLC', $app->fresh()->company_name);
    }

    public function test_signing_locks_the_document(): void
    {
        Storage::fake('public');

        $app = Application::create(['company_name' => 'Acme']);
        $png = 'data:image/png;base64,'.base64_encode('x');

        Livewire::test(ApplyForm::class, ['token' => $app->token])
            ->set('signerName', 'John Doe')
            ->set('disclosureAccepted', true)
            ->set('signatureData', $png)
            ->call('sign')
            ->assertSet('done', 'signed');

        $app->refresh();
        $this->assertSame('signed', $app->status);
        $this->assertSame('John Doe', $app->signer_name);
        $this->assertNotNull($app->signed_at);

        // Re-opening a signed doc shows the locked state, not the form.
        $this->get("/apply/{$app->token}")->assertSee(__('app.already_signed_title'));
    }

    public function test_advisor_oversized_row_values_are_truncated(): void
    {
        $app = Application::create(['company_name' => 'Acme']);

        Livewire::actingAs($this->staff())
            ->test(ApplyForm::class, ['token' => $app->token])
            ->set('vehicles', [[
                'year' => str_repeat('X', 200),
                'vin' => str_repeat('Y', 200),
            ]])
            ->call('next')
            ->assertHasNoErrors();

        $vehicle = $app->vehicles()->first();
        $this->assertLessThanOrEqual(20, mb_strlen($vehicle->year));
        $this->assertLessThanOrEqual(64, mb_strlen($vehicle->vin));
    }
}
