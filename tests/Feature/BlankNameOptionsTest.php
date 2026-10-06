<?php

namespace Tests\Feature;

use App\Filament\Resources\QuoteResource\Pages\CreateQuote;
use App\Models\Application;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Records created without a company name (blank test data, or imported ones) used to break the
 * pickers: Filament cannot render an option whose label is null ("Argument #2 ($label) must be of
 * type string, null given") and the whole page answered with a 500.
 */
class BlankNameOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'locale' => 'es']);
    }

    public function test_the_new_quote_page_opens_when_an_application_has_no_company_name(): void
    {
        $application = Application::create(['status' => 'created']);

        $this->actingAs($this->admin())->get('/admin/quotes/create')->assertOk();

        Livewire::actingAs($this->admin())
            ->test(CreateQuote::class)
            ->assertFormFieldExists('application_id', fn ($field) => array_key_exists($application->id, $field->getOptions()) && is_string($field->getOptions()[$application->id]));
    }

    public function test_pickers_fall_back_to_something_readable_when_there_is_no_company_name(): void
    {
        $this->assertSame('Acme', Client::factory()->make(['company_name' => 'Acme'])->pickerLabel());
        $this->assertSame('Maria Perez', Client::factory()->make(['company_name' => '', 'contact_name' => 'Maria Perez'])->pickerLabel());
        $this->assertSame('#', Client::factory()->make(['company_name' => '', 'contact_name' => null])->pickerLabel());
        $this->assertSame('#', Application::make(['company_name' => null])->pickerLabel());
        $this->assertSame('Beta', Application::make(['company_name' => 'Beta'])->pickerLabel());
    }
}
