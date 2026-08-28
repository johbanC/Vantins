<?php

namespace Tests\Feature;

use App\Filament\Resources\ApplicationResource\Pages\EditApplication;
use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_a_signed_application_cannot_be_deleted(): void
    {
        $app = Application::create(['company_name' => 'Acme', 'status' => 'signed']);

        $this->assertFalse($app->delete());
        $this->assertDatabaseHas('applications', ['id' => $app->id]);

        $this->assertFalse($this->staff()->can('delete', $app));
    }

    public function test_an_issued_application_cannot_be_deleted(): void
    {
        $app = Application::create(['company_name' => 'Acme', 'status' => 'issued']);

        $this->assertFalse($app->delete());
        $this->assertDatabaseHas('applications', ['id' => $app->id]);
    }

    public function test_an_unsigned_application_can_still_be_deleted(): void
    {
        $app = Application::create(['company_name' => 'Acme', 'status' => 'created']);

        $this->assertTrue($this->staff()->can('delete', $app));
        $this->assertTrue((bool) $app->delete());
        $this->assertDatabaseMissing('applications', ['id' => $app->id]);
    }

    public function test_edit_page_hides_delete_for_a_signed_application(): void
    {
        $signed = Application::create(['company_name' => 'Acme', 'status' => 'signed']);
        $open = Application::create(['company_name' => 'Beta', 'status' => 'created']);

        Livewire::actingAs($this->staff())
            ->test(EditApplication::class, ['record' => $signed->getKey()])
            ->assertActionHidden('delete');

        Livewire::actingAs($this->staff())
            ->test(EditApplication::class, ['record' => $open->getKey()])
            ->assertActionVisible('delete');
    }
}
