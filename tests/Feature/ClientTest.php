<?php

namespace Tests\Feature;

use App\Filament\Resources\ApplicationResource\Pages\CreateApplication;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ClientResource\Pages\CreateClient;
use App\Filament\Resources\ClientResource\Pages\ListClients;
use App\Models\Application;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'locale' => 'es']);
    }

    public function test_phone_is_normalized_ignoring_symbols_and_country_code(): void
    {
        $this->assertSame('7542900308', Client::normalizePhone('+1 (754) 290-0308'));
        $this->assertSame('7542900308', Client::normalizePhone('754-290-0308'));
        $this->assertSame('7542900308', Client::normalizePhone('1.754.290.0308'));
        $this->assertNull(Client::normalizePhone('n/a'));
        $this->assertSame('123456', Client::digits('MC-123456'));
    }

    public function test_search_finds_by_every_identifier(): void
    {
        $client = Client::factory()->create([
            'company_name' => 'Rodriguez Freight LLC', 'contact_name' => 'Maria Rodriguez',
            'email' => 'Maria@Rodriguez.test', 'phone' => '+1 (754) 290-0308',
            'us_dot_number' => 'DOT 1234567', 'mc_number' => 'MC-998877',
        ]);
        Client::factory()->create(['company_name' => 'Other Co', 'contact_name' => 'Zed Quinn', 'email' => 'zed@other.test', 'phone' => '305-111-2222', 'us_dot_number' => '555', 'mc_number' => '444']);

        foreach (['rodriguez freight', 'maria', 'MARIA@rodriguez.test', '754-290-0308', '(754) 290 0308', '7542900308', '+1 754 290 0308', '1234567', 'DOT 1234567', 'mc 998877'] as $term) {
            $found = Client::search($term)->pluck('id')->all();
            $this->assertSame([$client->id], $found, "search term: {$term}");
        }

        $this->assertCount(2, Client::search('')->get());
        $this->assertCount(0, Client::search('nothing-matches-this')->get());
    }

    public function test_search_is_wired_into_the_clients_table(): void
    {
        $match = Client::factory()->create(['phone' => '+1 (754) 290-0308']);
        $other = Client::factory()->create(['phone' => '305-111-2222']);

        Livewire::actingAs($this->admin())
            ->test(ListClients::class)
            ->searchTable('754-290-0308')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_duplicates_are_detected_and_explained(): void
    {
        $existing = Client::factory()->create(['email' => 'dup@x.test', 'phone' => '(754) 290-0308', 'us_dot_number' => '777']);

        $matches = Client::duplicatesOf(['email' => 'DUP@x.test', 'phone' => '+1 754 290 0308', 'us_dot_number' => 'DOT 777']);

        $this->assertCount(1, $matches);
        $this->assertSame($existing->id, $matches->first()['client']->id);
        $this->assertEqualsCanonicalizing(['email', 'phone', 'us_dot_number'], $matches->first()['reasons']);

        $this->assertCount(0, Client::duplicatesOf(['email' => 'dup@x.test'], $existing->id));
        $this->assertCount(0, Client::duplicatesOf([]));
    }

    public function test_creating_a_client_records_who_created_it(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateClient::class)
            ->fillForm(['company_name' => 'New Carrier LLC', 'phone' => '786-555-0101'])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = Client::where('company_name', 'New Carrier LLC')->firstOrFail();
        $this->assertSame($admin->id, $client->created_by);
        $this->assertSame('7865550101', $client->phone_normalized);
    }

    public function test_application_is_created_from_a_client_and_prefilled(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create(['us_dot_number' => '424242', 'is_demo' => true]);

        Livewire::actingAs($admin)
            ->test(CreateApplication::class)
            ->fillForm(['client_id' => $client->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $app = Application::where('client_id', $client->id)->firstOrFail();
        $this->assertSame($client->company_name, $app->company_name);
        $this->assertSame('424242', $app->us_dot_number);
        $this->assertSame($admin->id, $app->created_by);
        $this->assertSame($admin->name, $app->contact_agent_name);
        $this->assertTrue($app->is_demo);
        $this->assertSame('created', $app->status);
    }

    public function test_client_pages_load_for_a_client_with_applications(): void
    {
        $client = Client::factory()->create();
        Application::createForClient($client, $this->admin());

        $this->actingAs($this->admin())->get("/admin/clients/{$client->id}/edit")->assertOk();
        $this->actingAs($this->admin())->get('/admin/clients')->assertOk()->assertSee($client->company_name);
    }

    /**
     * The client form also opens inside the "create client" modal of the new-application form, where
     * there is no record behind it: a field declared with ->relationship() cannot resolve there and the
     * modal answers with a 500 ("Call to a member function isRelation() on null").
     */
    public function test_the_client_form_has_no_relationship_fields_so_it_works_inside_a_modal(): void
    {
        $method = new \ReflectionMethod(ClientResource::class, 'formSchema');
        $source = implode('', array_slice(file($method->getFileName()), $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        $this->assertStringNotContainsString('->relationship(', $source, 'the client form opens inside a modal with no record: use plain options');
    }
}
