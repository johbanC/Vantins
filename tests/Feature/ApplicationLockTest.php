<?php

namespace Tests\Feature;

use App\Filament\Resources\ApplicationResource\Pages\EditApplication;
use App\Filament\Resources\ApplicationResource\RelationManagers\CoveragesRelationManager;
use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApplicationLockTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function signedApplication(): Application
    {
        $app = Application::create([
            'company_name' => 'Acme',
            'status' => 'created',
            'down_payment' => 1000,
            'number_of_payments' => 10,
            'monthly_payment' => 100,
        ]);

        $app->markStatus('signed');

        return $app;
    }

    public function test_finance_proposal_cannot_be_edited_once_signed(): void
    {
        $app = $this->signedApplication();

        $app->update(['monthly_payment' => 999, 'company_name' => 'Hacked']);

        $app->refresh();
        $this->assertSame('100.00', $app->monthly_payment);
        $this->assertSame('Acme', $app->company_name);
        $this->assertEquals(2000, $app->total_policy_premium);
    }

    public function test_coverages_cannot_be_created_edited_or_deleted_once_signed(): void
    {
        $app = Application::create(['company_name' => 'Acme', 'status' => 'created']);
        $coverage = $app->coverages()->create(['coverage' => 'Liability', 'limit_amount' => '1M']);
        $app->markStatus('signed');

        // create
        $new = $app->coverages()->create(['coverage' => 'Cargo']);
        $this->assertFalse($new->exists);
        $this->assertSame(1, $app->coverages()->count());

        // edit
        $coverage->limit_amount = 'CHANGED';
        $this->assertFalse($coverage->save());
        $this->assertSame('1M', $coverage->fresh()->limit_amount);

        // delete
        $this->assertFalse($coverage->delete());
        $this->assertModelExists($coverage);
    }

    public function test_a_signed_application_cannot_roll_back_to_an_editable_status(): void
    {
        $app = $this->signedApplication();

        $threw = false;
        try {
            $app->markStatus('created');
        } catch (HttpException $e) {
            $threw = true;
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertTrue($threw, 'signed -> created must be rejected');
        $this->assertSame('signed', $app->fresh()->status);
    }

    public function test_signed_may_still_move_forward_to_issued(): void
    {
        $app = $this->signedApplication();

        $app->markStatus('issued');

        $this->assertSame('issued', $app->fresh()->status);
    }

    public function test_edit_page_shows_the_locked_notice_and_drops_the_save_button(): void
    {
        $signed = Application::create(['company_name' => 'Acme', 'status' => 'signed']);
        $open = Application::create(['company_name' => 'Beta', 'status' => 'created']);

        $lockedPage = Livewire::actingAs($this->staff())
            ->test(EditApplication::class, ['record' => $signed->getKey()])
            ->assertOk()
            ->assertSeeText(__('panel.locked_notice'));

        $this->assertSame([], $this->invokeFormActions($lockedPage->instance()));

        $openPage = Livewire::actingAs($this->staff())
            ->test(EditApplication::class, ['record' => $open->getKey()])
            ->assertOk()
            ->assertDontSeeText(__('panel.locked_notice'));

        $this->assertNotSame([], $this->invokeFormActions($openPage->instance()));
    }

    private function invokeFormActions(EditApplication $page): array
    {
        $method = new \ReflectionMethod($page, 'getFormActions');
        $method->setAccessible(true);

        return $method->invoke($page);
    }

    public function test_relation_managers_report_locked_and_hide_write_actions(): void
    {
        $signed = Application::create(['company_name' => 'Acme', 'status' => 'signed']);
        $open = Application::create(['company_name' => 'Beta', 'status' => 'created']);

        $lockedRm = Livewire::actingAs($this->staff())
            ->test(CoveragesRelationManager::class, [
                'ownerRecord' => $signed,
                'pageClass' => EditApplication::class,
            ]);
        $this->assertTrue($lockedRm->instance()->isApplicationLocked());
        $lockedRm->assertTableActionDoesNotExist('create')
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete');

        $openRm = Livewire::actingAs($this->staff())
            ->test(CoveragesRelationManager::class, [
                'ownerRecord' => $open,
                'pageClass' => EditApplication::class,
            ]);
        $this->assertFalse($openRm->instance()->isApplicationLocked());
        $openRm->assertTableActionExists('create');
    }

    public function test_pdf_is_not_generated_until_the_client_has_signed(): void
    {
        $app = Application::create(['company_name' => 'Acme', 'status' => 'created']);

        $this->assertFalse($app->canGeneratePdf());
        $this->get('/applications/'.$app->token.'/pdf')->assertForbidden();

        // Manually flipping the status is not enough: a real signature is required.
        $app->markStatus('signed');
        $this->assertFalse($app->fresh()->canGeneratePdf());
        $this->get('/applications/'.$app->token.'/pdf')->assertForbidden();

        $app->forceFill(['signature_path' => 'signatures/'.$app->token.'.png'])->save();

        $this->assertTrue($app->fresh()->canGeneratePdf());
        $this->get('/applications/'.$app->token.'/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_action_is_visible_but_disabled_until_signed(): void
    {
        $open = Application::create(['company_name' => 'Acme', 'status' => 'created']);
        $signed = Application::create(['company_name' => 'Beta', 'status' => 'signed']);
        $signed->forceFill(['signature_path' => 'signatures/'.$signed->token.'.png'])->save();

        Livewire::actingAs($this->staff())
            ->test(EditApplication::class, ['record' => $open->getKey()])
            ->assertActionVisible('pdf')
            ->assertActionDisabled('pdf');

        Livewire::actingAs($this->staff())
            ->test(EditApplication::class, ['record' => $signed->getKey()])
            ->assertActionVisible('pdf')
            ->assertActionEnabled('pdf');
    }
}
