<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Carrier;
use App\Models\Client;
use App\Models\CoverageType;
use App\Models\Quote;
use App\Models\QuoteStageChange;
use App\Models\User;
use App\Support\QuotePipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuotePipelineTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin', string $name = 'Ana Admin'): User
    {
        return User::factory()->create(['role' => $role, 'name' => $name]);
    }

    private function quoteFor(User $owner, array $attributes = []): Quote
    {
        $application = Application::createForClient(Client::factory()->create(), $owner);

        return Quote::factory()->priced()->create(['application_id' => $application->id, 'producer_id' => $owner->id] + $attributes);
    }

    private function document(Quote $quote, string $type): void
    {
        $quote->documents()->create(['type' => $type, 'name' => $type.'.pdf', 'path' => 'quotes/'.$type.'.pdf']);
    }

    /** @return array<string, string>  field => message of the validation errors a move raises */
    private function errorsOf(callable $move): array
    {
        try {
            $move();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    }

    public function test_a_quote_inherits_client_demo_flag_and_effective_date_from_its_application(): void
    {
        $ana = $this->user();
        $application = Application::createForClient(Client::factory()->create(['is_demo' => true]), $ana);
        $application->update(['effective_date' => '2026-12-01']);

        $quote = Quote::factory()->create(['application_id' => $application->id]);

        $this->assertSame($application->client_id, $quote->client_id);
        $this->assertTrue($quote->is_demo);
        $this->assertSame('2026-12-01', $quote->effective_date->toDateString());
        $this->assertSame('lead', $quote->stage);
        $this->assertSame('created', $application->fresh()->status, 'the application status is a separate field');
    }

    public function test_money_is_split_and_the_total_reconciles(): void
    {
        $quote = Quote::factory()->make();   // 12,000 + 500 + 750 ; 3,000 + 9 x 1,250

        $this->assertSame(13250.0, $quote->totalCost());
        $this->assertSame(11250.0, $quote->amountFinanced());
        $this->assertSame(14250.0, $quote->totalPayable());
        $this->assertSame(1000.0, $quote->financeCharge());
    }

    public function test_who_can_move_where(): void
    {
        $owner = $this->user('agent');
        $quote = $this->quoteFor($owner);

        $this->assertSame(['quote_sent', 'lost', 'declined', 'cancelled'], QuotePipeline::targets($quote, $owner));
        $this->assertSame(['quote_sent', 'accepted', 'binder', 'paid', 'sold', 'lost', 'declined', 'cancelled'], QuotePipeline::targets($quote, $this->user('admin')));
        $this->assertSame([], QuotePipeline::targets($quote, $this->user('viewer')));

        $quote->update(['stage' => 'sold']);
        $this->assertSame([], QuotePipeline::targets($quote->fresh(), $owner), 'a closed quote does not move');
    }

    public function test_sending_requires_a_priced_quote_with_coverages_and_a_sane_plan(): void
    {
        $ana = $this->user();
        $application = Application::createForClient(Client::factory()->create(), $ana);
        $quote = Quote::factory()->create([
            'application_id' => $application->id, 'carrier_premium' => null, 'expires_at' => now()->subDay()->toDateString(),
            'number_of_payments' => 2, 'installment_amount' => 100, 'down_payment' => 100,
        ]);

        $errors = $this->errorsOf(fn () => QuotePipeline::move($quote, 'quote_sent', [], $ana));

        $this->assertEqualsCanonicalizing(['carrier_premium', 'expires_at', 'coverages', 'number_of_payments'], array_keys($errors));

        $quote->update(['carrier_premium' => 5000, 'expires_at' => now()->addWeek()->toDateString()]);
        $quote->coverages()->create(['coverage_type_id' => CoverageType::where('key', 'auto_liability')->value('id'), 'limit_amount' => 1000000]);

        $errors = $this->errorsOf(fn () => QuotePipeline::move($quote->fresh(), 'quote_sent', [], $ana));
        $this->assertSame(['number_of_payments'], array_keys($errors), 'payments of $300 cannot cover a cost of $6,250');

        $quote->update(['down_payment' => 4000, 'installment_amount' => 1500, 'number_of_payments' => 2]);
        QuotePipeline::move($quote->fresh(), 'quote_sent', [], $ana);

        $quote->refresh();
        $this->assertSame('quote_sent', $quote->stage);
        $this->assertNotNull($quote->sent_at);
        $this->assertSame('open', $quote->acceptanceStatus());
        $this->assertSame($quote->id, $application->fresh()->selected_quote_id);
        $this->assertSame('created', $application->fresh()->status);

        $change = QuoteStageChange::firstOrFail();
        $this->assertSame(['lead', 'quote_sent'], [$change->from_stage, $change->to_stage]);
        $this->assertSame('Ana Admin', $change->user_name);
    }

    public function test_only_one_alternative_is_in_front_of_the_client(): void
    {
        $ana = $this->user();
        $application = Application::createForClient(Client::factory()->create(), $ana);
        $first = Quote::factory()->priced()->create(['application_id' => $application->id]);
        $second = Quote::factory()->priced()->create(['application_id' => $application->id]);

        QuotePipeline::move($first, 'quote_sent', [], $ana);
        QuotePipeline::move($second, 'quote_sent', [], $ana);

        $this->assertSame('revoked', $first->fresh()->acceptanceStatus());
        $this->assertSame('open', $second->fresh()->acceptanceStatus());
        $this->assertSame($second->id, $application->fresh()->selected_quote_id);
    }

    public function test_the_full_path_to_sold_asks_for_evidence_at_each_step(): void
    {
        $ana = $this->user();
        $quote = $this->quoteFor($ana);
        QuotePipeline::move($quote, 'quote_sent', [], $ana);

        // Accepted by phone / e-mail: needs who accepted, a note and the signed document.
        $this->assertEqualsCanonicalizing(['accepted_signer_name', 'acceptance_note', 'documents'],
            array_keys($this->errorsOf(fn () => QuotePipeline::move($quote->fresh(), 'accepted', [], $ana))));
        $this->document($quote, 'signed_acceptance');
        QuotePipeline::move($quote->fresh(), 'accepted', ['accepted_signer_name' => 'John Doe', 'acceptance_note' => 'Confirmed by e-mail'], $ana);

        $this->assertEqualsCanonicalizing(['binder_number', 'binder_effective_date', 'documents'],
            array_keys($this->errorsOf(fn () => QuotePipeline::move($quote->fresh(), 'binder', [], $ana))));
        $this->document($quote, 'binder');
        QuotePipeline::move($quote->fresh(), 'binder', ['binder_number' => 'BND-1', 'binder_effective_date' => '2026-11-01'], $ana);

        $this->assertEqualsCanonicalizing(['paid_at', 'documents'],
            array_keys($this->errorsOf(fn () => QuotePipeline::move($quote->fresh(), 'paid', [], $ana))));
        $this->document($quote, 'receipt');
        QuotePipeline::move($quote->fresh(), 'paid', ['paid_at' => '2026-11-02'], $ana);

        $this->assertEqualsCanonicalizing(['policy_number', 'closed_at'],
            array_keys($this->errorsOf(fn () => QuotePipeline::move($quote->fresh(), 'sold', [], $ana))));
        QuotePipeline::move($quote->fresh(), 'sold', ['policy_number' => 'POL-99', 'closed_at' => '2026-11-03'], $ana);

        $quote->refresh();
        $this->assertSame('sold', $quote->stage);
        $this->assertSame('BND-1', $quote->binder_number);
        $this->assertSame('POL-99', $quote->policy_number);
        $this->assertSame(
            ['quote_sent', 'accepted', 'binder', 'paid', 'sold'],
            $quote->stageChanges()->reorder('id')->pluck('to_stage')->all()
        );
        $this->assertTrue($quote->isClosed());
    }

    public function test_an_agent_cannot_skip_stages(): void
    {
        $owner = $this->user('agent');
        $quote = $this->quoteFor($owner);

        $this->assertArrayHasKey('stage', $this->errorsOf(fn () => QuotePipeline::move($quote, 'binder', [], $owner)));
        $this->assertSame('lead', $quote->fresh()->stage);
    }

    public function test_losing_a_quote_needs_a_normalized_reason_and_clears_the_link(): void
    {
        $ana = $this->user();
        $quote = $this->quoteFor($ana);
        QuotePipeline::move($quote, 'quote_sent', [], $ana);

        $this->assertArrayHasKey('loss_reason', $this->errorsOf(fn () => QuotePipeline::move($quote->fresh(), 'lost', ['closed_at' => '2026-11-01'], $ana)));
        $this->assertArrayHasKey('loss_reason', $this->errorsOf(fn () => QuotePipeline::move($quote->fresh(), 'lost', ['loss_reason' => 'because', 'closed_at' => '2026-11-01'], $ana)));

        QuotePipeline::move($quote->fresh(), 'declined', ['loss_reason' => 'carrier_declined', 'closed_at' => '2026-11-01', 'competitor' => 'Acme Mutual'], $ana, 'Carrier declined the risk');

        $quote->refresh();
        $this->assertSame('declined', $quote->stage);
        $this->assertSame('carrier_declined', $quote->loss_reason);
        $this->assertSame('revoked', $quote->acceptanceStatus());
        $this->assertNull($quote->application->selected_quote_id);
        $this->assertSame('Carrier declined the risk', $quote->stageChanges()->first()->note);
    }

    public function test_revising_creates_a_new_version_and_keeps_the_old_one(): void
    {
        $ana = $this->user();
        $quote = $this->quoteFor($ana);
        QuotePipeline::move($quote, 'quote_sent', [], $ana);

        $new = $quote->fresh()->revise();

        $this->assertSame(2, $new->version);
        $this->assertSame('lead', $new->stage);
        $this->assertSame($quote->id, $new->previous_quote_id);
        $this->assertSame(1, $new->coverages()->count(), 'coverage lines are copied');
        $this->assertNull($new->acceptance_token);

        $old = $quote->fresh();
        $this->assertNotNull($old->superseded_at);
        $this->assertSame('revoked', $old->acceptanceStatus());
        $this->assertSame('quote_sent', $old->stage, 'the old version is kept exactly as it was');
        $this->assertNull($old->application->selected_quote_id);
        $this->assertSame([], QuotePipeline::targets($old, $ana), 'a replaced version does not move');
        $this->assertSame([$new->id], Quote::current()->where('application_id', $old->application_id)->pluck('id')->all());
    }

    public function test_an_accepted_quote_can_no_longer_change(): void
    {
        $ana = $this->user();
        $quote = $this->quoteFor($ana);
        QuotePipeline::move($quote, 'quote_sent', [], $ana);
        QuotePipeline::accept($quote->fresh(), 'John Doe', 'signatures/q.png', '10.0.0.1');

        $quote->refresh();
        $this->assertSame('accepted', $quote->stage);
        $this->assertNotNull($quote->accepted_at);
        $this->assertSame('accepted', $quote->acceptanceStatus());

        $this->assertFalse($quote->update(['carrier_premium' => 1]));
        $this->assertEquals(12000, $quote->fresh()->carrier_premium);

        $line = $quote->coverages()->firstOrFail();
        $this->assertFalse($line->update(['limit_amount' => 1]));
        $this->assertFalse($line->delete());

        // The pipeline itself keeps going: binder data is not part of the signed terms.
        $quote->fresh()->update(['binder_number' => 'BND-7']);
        $this->assertSame('BND-7', $quote->fresh()->binder_number);
    }

    public function test_the_signed_snapshot_never_contains_the_carrier(): void
    {
        $ana = $this->user();
        $carrier = Carrier::factory()->create(['name' => 'Secret Mutual Insurance']);
        $quote = $this->quoteFor($ana, ['carrier_id' => $carrier->id]);
        QuotePipeline::move($quote, 'quote_sent', [], $ana);
        QuotePipeline::accept($quote->fresh(), 'John Doe', 'signatures/q.png', null);

        $snapshot = $quote->fresh()->accepted_snapshot;
        $this->assertStringNotContainsString('Secret Mutual', json_encode($snapshot));
        $this->assertSame(13250.0, (float) $snapshot['total_cost']);
        $this->assertSame('Auto Liability', $snapshot['coverages'][0]['name_en']);

        $change = $quote->stageChanges()->first();
        $this->assertNull($change->user_id, 'a client signing through the link has no user');
        $this->assertSame(['signed_through_link' => true], $change->evidence);
    }

    public function test_stage_history_cannot_be_edited_or_deleted(): void
    {
        $ana = $this->user();
        $quote = $this->quoteFor($ana);
        QuotePipeline::move($quote, 'quote_sent', [], $ana);
        $change = QuoteStageChange::firstOrFail();

        $this->assertFalse($change->update(['to_stage' => 'sold']));
        $this->assertFalse($change->delete());
        $this->assertSame('quote_sent', $change->fresh()->to_stage);
    }

    public function test_agents_only_see_the_quotes_of_their_own_applications(): void
    {
        $ana = $this->user('agent');
        $bob = $this->user('agent');
        $anaQuote = $this->quoteFor($ana);
        $bobQuote = $this->quoteFor($bob);

        $this->assertSame([$anaQuote->id], Quote::visibleTo($ana)->pluck('id')->all());
        $this->assertCount(2, Quote::visibleTo($this->user('admin'))->get());
        $this->assertCount(2, Quote::visibleTo($this->user('viewer'))->get());
    }

    public function test_quote_changes_are_audited_without_leaking_the_client_link(): void
    {
        $ana = $this->user();
        $this->actingAs($ana);
        $quote = $this->quoteFor($ana);
        QuotePipeline::move($quote, 'quote_sent', [], $ana);

        $stageLog = ActivityLog::where('subject_type', 'quote')->where('event', 'stage_changed')->firstOrFail();
        $this->assertSame(['lead', 'quote_sent'], $stageLog->changes['stage']);
        $this->assertStringNotContainsString($quote->fresh()->acceptance_token, json_encode(ActivityLog::all()->toArray()));
        $this->assertTrue(ActivityLog::where('event', 'link_issued')->exists());
    }
}
