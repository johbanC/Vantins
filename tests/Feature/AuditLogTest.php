<?php

namespace Tests\Feature;

use App\Filament\RelationManagers\ActivityRelationManager;
use App\Filament\Resources\ApplicationResource\Pages\EditApplication;
use App\Livewire\ApplyForm;
use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'Ana Admin', 'locale' => 'es']);
    }

    public function test_creating_and_changing_an_application_is_recorded_with_who_and_what(): void
    {
        $ana = $this->admin();
        $this->actingAs($ana);

        $client = Client::factory()->create();
        $app = Application::createForClient($client, $ana);

        $created = ActivityLog::where('subject_type', 'application')->where('event', 'created')->firstOrFail();
        $this->assertSame($ana->id, $created->user_id);
        $this->assertSame('Ana Admin', $created->user_name);
        $this->assertSame($app->id, $created->application_id);
        $this->assertSame($client->id, $created->client_id);

        $app->update(['radius_of_operations' => '500 miles']);

        $updated = ActivityLog::where('event', 'updated')->where('subject_type', 'application')->firstOrFail();
        $this->assertSame([null, '500 miles'], $updated->changes['radius_of_operations']);
    }

    public function test_status_changes_are_their_own_event(): void
    {
        $this->actingAs($this->admin());
        $app = Application::createForClient(Client::factory()->create());

        $app->markStatus('in_review');

        $log = ActivityLog::where('event', 'status_changed')->firstOrFail();
        $this->assertSame(['created', 'in_review'], $log->changes['status']);
        $this->assertContains('Estatus: Creada → En revisión', $this->inSpanish(fn () => $log->summaryLines()));
    }

    public function test_saving_without_changes_writes_nothing(): void
    {
        $this->actingAs($this->admin());
        $app = Application::createForClient(Client::factory()->create());
        $before = ActivityLog::count();

        $app->update(['company_name' => $app->company_name]);

        $this->assertSame($before, ActivityLog::count());
    }

    public function test_dob_and_cdl_values_are_never_stored(): void
    {
        $this->actingAs($this->admin());
        $app = Application::createForClient(Client::factory()->create());
        $driver = $app->drivers()->create(['driver_name' => 'John', 'cdl_number' => 'SECRET123456', 'dob' => '1985-03-10']);

        $driver->update(['cdl_number' => 'OTHER9876543', 'dob' => '1986-04-11', 'experience' => '5']);

        $log = ActivityLog::where('subject_type', 'driver')->where('event', 'updated')->firstOrFail();
        $this->assertSame($app->id, $log->application_id);
        $this->assertSame([ActivityLog::HIDDEN, ActivityLog::HIDDEN], $log->changes['cdl_number']);
        $this->assertSame([ActivityLog::HIDDEN, ActivityLog::HIDDEN], $log->changes['dob']);
        $this->assertSame([null, '5'], $log->changes['experience']);
        $this->assertStringNotContainsString('SECRET123456', json_encode(ActivityLog::all()->toArray()));
        $this->assertStringNotContainsString('OTHER9876543', json_encode(ActivityLog::all()->toArray()));
    }

    public function test_the_log_cannot_be_edited_or_deleted(): void
    {
        $this->actingAs($this->admin());
        Application::createForClient(Client::factory()->create());
        $log = ActivityLog::firstOrFail();

        $this->assertFalse($log->update(['event' => 'tampered']));
        $this->assertFalse($log->delete());
        $this->assertSame('created', $log->fresh()->event);
    }

    public function test_the_advisor_form_updates_rows_in_place_and_logs_only_real_changes(): void
    {
        $ana = $this->admin();
        $this->actingAs($ana);
        $app = Application::createForClient(Client::factory()->create(), $ana);

        $component = Livewire::actingAs($ana)->test(ApplyForm::class, ['token' => $app->token])
            ->set('step', 2)
            ->set('drivers', [['driver_name' => 'John', 'dob' => '1985-03-10']])
            ->call('next')
            ->assertHasNoErrors();

        $driverId = $app->drivers()->firstOrFail()->id;
        $createdLogs = ActivityLog::where('subject_type', 'driver')->count();
        $this->assertSame(1, $createdLogs);

        // Going back and forward again with nothing changed must not touch the row nor the log.
        $component->call('back')->call('next');
        $this->assertSame($driverId, $app->drivers()->firstOrFail()->id);
        $this->assertSame(1, $app->drivers()->count());
        $this->assertSame($createdLogs, ActivityLog::where('subject_type', 'driver')->count());

        // A real edit is logged as one update on the same row.
        $component->set('step', 2)->set('drivers.0.experience', '7')->call('next');
        $this->assertSame($driverId, $app->drivers()->firstOrFail()->id);
        $this->assertSame(1, ActivityLog::where('subject_type', 'driver')->where('event', 'updated')->count());

        // Removing the row is a delete.
        $component->set('step', 2)->call('removeRow', 'drivers', 0)->call('next');
        $this->assertSame(0, $app->drivers()->count());
        $this->assertSame(1, ActivityLog::where('subject_type', 'driver')->where('event', 'deleted')->count());
    }

    public function test_the_client_signing_through_the_link_is_recorded_without_a_user(): void
    {
        Storage::fake('public');
        $app = Application::createForClient(Client::factory()->create());

        Livewire::test(ApplyForm::class, ['token' => $app->token])
            ->set('signerName', 'John Doe')
            ->set('disclosureAccepted', true)
            ->set('signatureData', 'data:image/png;base64,'.base64_encode('x'))
            ->call('sign');

        $log = ActivityLog::where('event', 'status_changed')->firstOrFail();
        $this->assertNull($log->user_id);
        $this->assertSame(['created', 'signed'], $log->changes['status']);
        $this->inSpanish(fn () => $this->assertSame('Cliente (por su enlace)', $log->actorName()));
    }

    public function test_history_tab_shows_in_the_panel_and_the_global_log_is_admin_only(): void
    {
        app()->setLocale('es');
        $ana = $this->admin();
        $this->actingAs($ana);
        $app = Application::createForClient(Client::factory()->create(), $ana);
        $app->markStatus('in_review');

        Livewire::actingAs($ana)->test(ActivityRelationManager::class, [
            'ownerRecord' => $app,
            'pageClass' => EditApplication::class,
        ])->assertSee('Ana Admin')->assertSee('Cambió el estatus');

        $this->actingAs($ana)->get('/admin/activity-logs')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'agent']))->get('/admin/activity-logs')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'viewer']))->get('/admin/activity-logs')->assertForbidden();
    }

    private function inSpanish(callable $callback): mixed
    {
        app()->setLocale('es');

        return $callback();
    }
}
