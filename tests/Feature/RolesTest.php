<?php

namespace Tests\Feature;

use App\Filament\Resources\ApplicationResource\Pages\ListApplications;
use App\Filament\Resources\ApplicationResource\Pages\ViewApplication;
use App\Filament\Resources\ApplicationResource\RelationManagers\DriversRelationManager;
use App\Filament\Resources\ClientResource\Pages\EditClient;
use App\Filament\Resources\ClientResource\Pages\ListClients;
use App\Livewire\ApplyForm;
use App\Models\Application;
use App\Models\Client;
use App\Models\User;
use App\Support\Mask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'locale' => 'es']);
    }

    /** @return array{0: Client, 1: Application} */
    private function clientWithApplication(User $owner): array
    {
        $client = Client::factory()->create(['assigned_user_id' => $owner->id, 'created_by' => $owner->id]);

        return [$client, Application::createForClient($client, $owner)];
    }

    public function test_an_agent_only_sees_their_own_applications_and_clients(): void
    {
        $ana = $this->user('agent');
        $bob = $this->user('agent');
        [$anaClient, $anaApp] = $this->clientWithApplication($ana);
        [$bobClient, $bobApp] = $this->clientWithApplication($bob);

        Livewire::actingAs($ana)->test(ListApplications::class)
            ->assertCanSeeTableRecords([$anaApp])
            ->assertCanNotSeeTableRecords([$bobApp]);

        Livewire::actingAs($ana)->test(ListClients::class)
            ->assertCanSeeTableRecords([$anaClient])
            ->assertCanNotSeeTableRecords([$bobClient]);

        $this->actingAs($ana)->get("/admin/applications/{$bobApp->id}/edit")->assertNotFound();
        $this->actingAs($ana)->get("/admin/clients/{$bobClient->id}/edit")->assertNotFound();
        $this->actingAs($ana)->get("/admin/applications/{$anaApp->id}/edit")->assertOk();
    }

    public function test_admin_and_viewer_see_everything(): void
    {
        [, $app] = $this->clientWithApplication($this->user('agent'));

        foreach (['admin', 'viewer'] as $role) {
            Livewire::actingAs($this->user($role))->test(ListApplications::class)
                ->assertCanSeeTableRecords([$app]);
        }
    }

    public function test_viewer_cannot_create_or_edit(): void
    {
        $viewer = $this->user('viewer');
        [$client, $app] = $this->clientWithApplication($this->user('agent'));

        $this->actingAs($viewer)->get('/admin/applications/create')->assertForbidden();
        $this->actingAs($viewer)->get('/admin/clients/create')->assertForbidden();
        $this->actingAs($viewer)->get("/admin/applications/{$app->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->get("/admin/clients/{$client->id}/edit")->assertForbidden();

        $this->actingAs($viewer)->get("/admin/applications/{$app->id}")->assertOk();
        $this->actingAs($viewer)->get("/admin/clients/{$client->id}")->assertOk();
    }

    public function test_users_menu_is_admin_only(): void
    {
        $this->actingAs($this->user('agent'))->get('/admin/users')->assertForbidden();
        $this->actingAs($this->user('viewer'))->get('/admin/users')->assertForbidden();
        $this->actingAs($this->user('admin'))->get('/admin/users')->assertOk();
    }

    public function test_cdl_and_dob_are_masked_unless_admin_or_creator(): void
    {
        $creator = $this->user('agent');
        [, $app] = $this->clientWithApplication($creator);
        $app->drivers()->create(['driver_name' => 'John Doe', 'cdl_number' => 'D123456789012', 'dob' => '1985-03-10']);

        $this->assertTrue($app->canRevealSensitiveData($creator));
        $this->assertTrue($app->canRevealSensitiveData($this->user('admin')));
        $this->assertFalse($app->canRevealSensitiveData($this->user('viewer')));
        $this->assertFalse($app->canRevealSensitiveData($this->user('agent')));

        $drivers = fn (User $user) => Livewire::actingAs($user)->test(DriversRelationManager::class, [
            'ownerRecord' => $app,
            'pageClass' => ViewApplication::class,
        ]);

        // The read-only user sees masked values.
        $drivers($this->user('viewer'))
            ->assertSee(Mask::cdl('D123456789012'))
            ->assertDontSee('D123456789012')
            ->assertDontSee('Mar 10, 1985')
            ->assertDontSee('10 mar 1985');

        // The creator and admins see them in full.
        $drivers($creator)->assertSee('D123456789012');
        $drivers($this->user('admin'))->assertSee('D123456789012');

        $this->assertSame('••••••9012', Mask::cdl('D123456789012'));
        $this->assertSame(Mask::HIDDEN, Mask::dob('1985-03-10'));
        $this->assertSame('—', Mask::dob(null));
    }

    public function test_agent_who_only_has_the_assigned_client_can_view_but_not_edit(): void
    {
        $creator = $this->user('agent');
        $assigned = $this->user('agent');
        [$client, $app] = $this->clientWithApplication($creator);
        $client->update(['assigned_user_id' => $assigned->id]);

        $this->actingAs($assigned)->get("/admin/applications/{$app->id}")->assertOk();
        $this->actingAs($assigned)->get("/admin/applications/{$app->id}/edit")->assertForbidden();
    }

    public function test_only_admins_can_reassign_a_client(): void
    {
        $agent = $this->user('agent');
        [$client] = $this->clientWithApplication($agent);

        Livewire::actingAs($agent)->test(EditClient::class, ['record' => $client->id])
            ->assertFormFieldIsDisabled('assigned_user_id');

        Livewire::actingAs($this->user('admin'))->test(EditClient::class, ['record' => $client->id])
            ->assertFormFieldIsEnabled('assigned_user_id');
    }

    public function test_advisor_form_is_editable_only_for_who_may_update_the_application(): void
    {
        $owner = $this->user('agent');
        [, $app] = $this->clientWithApplication($owner);

        Livewire::actingAs($owner)->test(ApplyForm::class, ['token' => $app->token])
            ->assertSet('editable', true)
            ->assertSet('staffReadOnly', false);

        foreach (['viewer', 'agent'] as $role) {
            Livewire::actingAs($this->user($role))->test(ApplyForm::class, ['token' => $app->token])
                ->assertSet('editable', false)
                ->assertSet('staffReadOnly', true)
                ->assertDontSee(__('app.sign_send'))
                ->set('signerName', 'Someone')
                ->set('disclosureAccepted', true)
                ->set('signatureData', 'data:image/png;base64,'.base64_encode('x'))
                ->call('sign')
                ->assertForbidden();
        }

        $this->assertSame('created', $app->fresh()->status);
    }

    public function test_the_client_without_login_still_reviews_and_signs(): void
    {
        [, $app] = $this->clientWithApplication($this->user('agent'));

        Livewire::test(ApplyForm::class, ['token' => $app->token])
            ->assertSet('editable', false)
            ->assertSet('staffReadOnly', false)
            ->assertSee(__('app.sign_send'));
    }
}
