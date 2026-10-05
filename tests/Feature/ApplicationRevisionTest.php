<?php

namespace Tests\Feature;

use App\Filament\Resources\ApplicationResource\Pages\ListApplications;
use App\Filament\Resources\ApplicationResource\Pages\ViewApplication;
use App\Livewire\ApplyForm;
use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Client;
use App\Models\Coverage;
use App\Models\CoverageType;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create(['role' => 'agent', 'name' => 'Ana Agent', 'locale' => 'es']);
    }

    /** An application with data in every list, ready to be signed. */
    private function application(): Application
    {
        $application = Application::createForClient(Client::factory()->create(['company_name' => 'Acme Freight LLC']), $this->agent);
        $application->update(['radius_of_operations' => '500 miles', 'down_payment' => 3000, 'monthly_payment' => 1000, 'number_of_payments' => 10]);
        $application->drivers()->create(['driver_name' => 'John Driver', 'dob' => '1985-03-10']);
        $vehicle = $application->vehicles()->create(['year' => '2021', 'make' => 'Volvo', 'vin' => '1HGCM82633A004352']);
        $trailer = $application->trailers()->create(['year' => '2020', 'make' => 'Great Dane']);
        $application->coverages()->create([
            'coverage_type_id' => CoverageType::where('key', 'physical_damage')->value('id'),
            'coverage' => 'Physical Damage', 'deductible' => '2500', 'details' => ['vehicle_ids' => [$vehicle->id]],
        ]);
        $application->coverages()->create([
            'coverage_type_id' => CoverageType::where('key', 'trailer_interchange')->value('id'),
            'coverage' => 'Trailer Interchange', 'limit_amount' => '50000', 'deductible' => '1000', 'details' => ['trailer_ids' => [$trailer->id]],
        ]);

        return $application->refresh();
    }

    private function signed(): Application
    {
        $application = $this->application();
        $application->forceFill(['signer_name' => 'John Doe', 'disclosure_accepted_at' => now()])->save();
        $application->markStatus('signed');

        return $application->refresh();
    }

    public function test_what_the_client_signed_can_no_longer_change(): void
    {
        $application = $this->signed();

        $this->assertTrue($application->isSigned());
        $this->assertFalse($application->update(['company_name' => 'Renamed LLC']));
        $this->assertFalse($application->update(['down_payment' => 1]));
        $this->assertFalse($application->update(['signer_name' => 'Someone Else']));
        $this->assertSame('Acme Freight LLC', $application->fresh()->company_name);

        $this->assertFalse($application->drivers()->first()->update(['driver_name' => 'Changed']));
        $this->assertFalse($application->drivers()->first()->delete());
        $application->drivers()->create(['driver_name' => 'New']);
        $this->assertSame(1, $application->drivers()->count(), 'no row can be added');
    }

    public function test_rows_cannot_be_added_changed_or_removed_after_signing(): void
    {
        $application = $this->signed();

        foreach (['drivers', 'vehicles', 'trailers', 'coverages'] as $relation) {
            $row = $application->{$relation}()->first();
            $this->assertFalse($row->delete(), "{$relation} row deleted after signing");
            $this->assertSame($application->{$relation}()->count(), $application->fresh()->{$relation}()->count());
        }

        $this->assertSame(1, $application->drivers()->count());
        $this->assertFalse($application->coverages()->first()->update(['limit_amount' => '1']));
    }

    public function test_the_workflow_still_moves_after_signing_without_unlocking_the_data(): void
    {
        $application = $this->signed();

        foreach (['in_review', 'quoted', 'issued', 'cancelled'] as $status) {
            $application->markStatus($status);
            $this->assertSame($status, $application->fresh()->status);
        }

        $application->markStatus('in_review');
        $this->assertTrue($application->fresh()->isLocked(), 'in review is no longer an unlocked state');
        $this->assertFalse($application->fresh()->update(['company_name' => 'Renamed LLC']));

        // Language, link and filing are not part of what was signed.
        $this->assertTrue($application->update(['locale' => 'es']));
        $application->renewLink();
        $this->assertTrue($application->fresh()->link_pin !== null);
    }

    public function test_a_revision_copies_everything_and_keeps_the_signed_one_as_it_was(): void
    {
        $this->actingAs($this->agent);
        $original = $this->signed();

        $copy = $original->createRevision('The driver date of birth was wrong');

        $this->assertSame(2, $copy->revision);
        $this->assertSame($original->id, $copy->revision_of_id);
        $this->assertSame('The driver date of birth was wrong', $copy->revision_reason);
        $this->assertSame('created', $copy->status);
        $this->assertNull($copy->signed_at);
        $this->assertNull($copy->signer_name);
        $this->assertNotSame($original->token, $copy->token);
        $this->assertNotSame($original->verification_code, $copy->verification_code);
        $this->assertSame('active', $copy->linkStatus());
        $this->assertFalse($copy->isSigned());

        $this->assertSame('Acme Freight LLC', $copy->company_name);
        $this->assertSame('500 miles', $copy->radius_of_operations);
        $this->assertEquals(13000, $copy->total_policy_premium);
        $this->assertSame(['John Driver'], $copy->drivers->pluck('driver_name')->all());
        $this->assertSame(['1HGCM82633A004352'], $copy->vehicles->pluck('vin')->all());
        $this->assertSame(['Great Dane'], $copy->trailers->pluck('make')->all());
        $this->assertCount(2, $copy->coverages);

        // The coverages follow the new copies of the vehicles and trailers, not the old ones.
        $newVehicle = $copy->vehicles->first();
        $newTrailer = $copy->trailers->first();
        $this->assertSame([$newVehicle->id], $copy->coverages->firstWhere('coverage', 'Physical Damage')->details['vehicle_ids']);
        $this->assertSame([$newTrailer->id], $copy->coverages->firstWhere('coverage', 'Trailer Interchange')->details['trailer_ids']);

        // The signed original is untouched, marked as replaced, and its link stops.
        $original->refresh();
        $this->assertTrue($original->isSuperseded());
        $this->assertTrue($original->isSigned());
        $this->assertSame('revoked', $original->link_revoked_at ? 'revoked' : 'active');
        $this->assertSame(1, $original->drivers()->count());
        $this->assertSame('Acme Freight LLC', $original->company_name);

        $log = ActivityLog::where('event', 'revision_created')->firstOrFail();
        $this->assertSame($copy->id, $log->application_id);
        $this->assertSame([null, 'The driver date of birth was wrong'], $log->changes['revision_reason']);
    }

    public function test_the_revision_can_be_edited_and_signed_like_a_new_application(): void
    {
        $this->actingAs($this->agent);
        $copy = $this->signed()->createRevision('Fix the radius');

        $copy->update(['radius_of_operations' => '300 miles']);
        $this->assertSame('300 miles', $copy->fresh()->radius_of_operations);

        auth()->logout();
        Livewire::test(ApplyForm::class, ['token' => $copy->token])
            ->set('pin', $copy->link_pin)->call('verifyPin')
            ->set('signerName', 'John Doe')
            ->set('disclosureAccepted', true)
            ->set('signatureData', 'data:image/png;base64,'.base64_encode('x'))
            ->call('sign')
            ->assertSet('done', 'signed');

        $this->assertTrue($copy->fresh()->isSigned());
    }

    public function test_only_a_current_signed_application_can_be_revised(): void
    {
        $this->actingAs($this->agent);

        $open = $this->application();
        $this->assertFalse($this->agent->can('revise', $open));

        $signed = $this->signed();
        $this->assertTrue($this->agent->can('revise', $signed));

        $signed->createRevision('First');
        $this->assertFalse($this->agent->can('revise', $signed->fresh()), 'a replaced version is not revised again');
    }

    public function test_the_signed_application_page_offers_what_is_still_allowed(): void
    {
        $application = $this->signed();

        // No edit page for a signed application, but the view page and its actions work.
        $this->actingAs($this->agent)->get("/admin/applications/{$application->id}/edit")->assertForbidden();
        $this->actingAs($this->agent)->get("/admin/applications/{$application->id}")->assertOk();

        Livewire::actingAs($this->agent)->test(ViewApplication::class, ['record' => $application->id])
            ->assertActionVisible('revision')
            ->assertActionVisible('changeStatus')
            ->assertActionVisible('copyLink')
            ->assertActionHidden('renewLink')   // nothing left to fill in: a signed application needs no new link
            ->callAction('revision', data: ['reason' => 'Wrong VIN'])
            ->assertRedirect();

        $this->assertSame(2, Application::where('client_id', $application->client_id)->max('revision'));

        // The unsigned application keeps its edit page and has nothing to revise.
        $open = $this->application();
        $this->actingAs($this->agent)->get("/admin/applications/{$open->id}/edit")->assertOk();
        Livewire::actingAs($this->agent)->test(ViewApplication::class, ['record' => $open->id])->assertActionHidden('revision');
    }

    public function test_a_reason_is_required_and_a_viewer_cannot_revise(): void
    {
        $application = $this->signed();

        Livewire::actingAs($this->agent)->test(ViewApplication::class, ['record' => $application->id])
            ->callAction('revision', data: ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);
        $this->assertSame(1, Application::count());

        Livewire::actingAs(User::factory()->create(['role' => 'viewer']))->test(ViewApplication::class, ['record' => $application->id])
            ->assertActionHidden('revision');
    }

    public function test_the_list_shows_current_versions_by_default(): void
    {
        $this->actingAs($this->agent);
        $original = $this->signed();
        $copy = $original->createRevision('Fix');

        Livewire::test(ListApplications::class)
            ->assertCanSeeTableRecords([$copy])
            ->assertCanNotSeeTableRecords([$original])
            ->removeTableFilter('current')
            ->assertCanSeeTableRecords([$original, $copy]);
    }

    public function test_the_page_of_a_replaced_version_says_so(): void
    {
        $this->actingAs($this->agent);
        $original = $this->signed();
        $original->createRevision('Fix');
        auth()->logout();

        $original->refresh();

        Livewire::test(ApplyForm::class, ['token' => $original->token])
            ->set('pin', $original->link_pin)->call('verifyPin')
            ->assertSee('earlier version');
    }

    public function test_quotes_keep_working_on_a_signed_application(): void
    {
        $application = $this->signed();

        $this->assertTrue($this->agent->can('manage', $application));
        $this->assertTrue($this->agent->can('create', Quote::class));
        $this->actingAs($this->agent)->get("/admin/quotes/create?application={$application->id}")->assertOk();
    }

    public function test_a_coverage_row_of_the_catalog_is_still_readable_on_a_signed_application(): void
    {
        $application = $this->signed();

        $this->assertSame('Physical Damage', Coverage::where('application_id', $application->id)->where('coverage', 'Physical Damage')->first()->displayName('en'));
    }
}
