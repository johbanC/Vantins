<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One carrier's quote for an application. Its stage is the commercial pipeline and is
 * independent from the status of the application itself.
 */
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory, RecordsActivity;

    /** The pipeline, in order. */
    public const STAGES = ['lead', 'quote_sent', 'accepted', 'binder', 'paid', 'sold'];

    /** Ways to close without a sale. */
    public const LOSS_STAGES = ['lost', 'declined', 'cancelled'];

    public const LOSS_REASONS = [
        'price', 'coverage_unavailable', 'carrier_declined', 'no_response',
        'went_elsewhere', 'no_longer_needed', 'documents_missing', 'other',
    ];

    /** Once accepted by the client, none of these may change any more. */
    public const FROZEN_AFTER_ACCEPTANCE = [
        'application_id', 'carrier_id', 'product', 'carrier_premium', 'fees', 'producer_fee',
        'down_payment', 'number_of_payments', 'installment_amount', 'effective_date', 'expires_at',
        'accepted_at', 'accepted_signer_name', 'accepted_signature_path', 'accepted_ip', 'accepted_snapshot',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'is_demo' => 'boolean',
        'superseded_at' => 'datetime',
        'follow_up_at' => 'date',
        'quoted_at' => 'date',
        'sent_at' => 'datetime',
        'expires_at' => 'date',
        'effective_date' => 'date',
        'binder_effective_date' => 'date',
        'paid_at' => 'date',
        'closed_at' => 'date',
        'carrier_premium' => 'decimal:2',
        'fees' => 'decimal:2',
        'producer_fee' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'acceptance_expires_at' => 'datetime',
        'acceptance_revoked_at' => 'datetime',
        'accepted_at' => 'datetime',
        'accepted_snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Quote $quote): void {
            $quote->client_id ??= $quote->application?->client_id;
            $quote->is_demo = (bool) ($quote->application?->is_demo ?? $quote->is_demo);
            $quote->effective_date ??= $quote->application?->effective_date;
        });

        // A quote the client signed is a closed record: its terms never change.
        static::updating(function (Quote $quote): ?bool {
            if ($quote->getOriginal('accepted_at') === null) {
                return null;
            }

            return array_intersect(array_keys($quote->getDirty()), self::FROZEN_AFTER_ACCEPTANCE) === [] ? null : false;
        });

        static::deleting(fn (Quote $quote) => $quote->stage === 'lead' && $quote->version === 1 && $quote->accepted_at === null);
    }

    public static function allStages(): array
    {
        return array_merge(self::STAGES, self::LOSS_STAGES);
    }

    public function isClosed(): bool
    {
        return in_array($this->stage, ['sold', ...self::LOSS_STAGES], true);
    }

    public function isCurrent(): bool
    {
        return $this->superseded_at === null;
    }

    /** Still being worked: neither closed nor replaced by a newer version. */
    public function isOpen(): bool
    {
        return $this->isCurrent() && ! $this->isClosed();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null || $this->getOriginal('accepted_at') !== null;
    }

    /** The commercial terms can only be typed while it is a lead; afterwards a new version is made. */
    public function isEditable(): bool
    {
        return $this->stage === 'lead' && $this->isCurrent() && ! $this->isAccepted();
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->current()->whereNotIn('stage', ['sold', ...self::LOSS_STAGES]);
    }

    /** Admins and read-only users see every quote; an agent those of the applications they can see. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->isViewer()) {
            return $query;
        }

        return $query->whereHas('application', fn (Builder $a) => $a->visibleTo($user));
    }

    // ---- money -----------------------------------------------------------------------------

    /** Premium + fees + the producer's fee: what the policy costs before financing. */
    public function totalCost(): float
    {
        return round((float) $this->carrier_premium + (float) $this->fees + (float) $this->producer_fee, 2);
    }

    /** What is financed: the instalments. */
    public function amountFinanced(): float
    {
        return round((float) $this->installment_amount * (int) $this->number_of_payments, 2);
    }

    /** Down payment + instalments: what the client ends up paying. */
    public function totalPayable(): float
    {
        return round((float) $this->down_payment + $this->amountFinanced(), 2);
    }

    /** Payable above cost = financing charges. Negative means the plan does not cover the cost. */
    public function financeCharge(): float
    {
        return round($this->totalPayable() - $this->totalCost(), 2);
    }

    // ---- links ------------------------------------------------------------------------------

    /** 'none' | 'open' | 'expired' | 'revoked' | 'accepted' */
    public function acceptanceStatus(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->acceptance_token === null => 'none',
            $this->acceptance_revoked_at !== null => 'revoked',
            $this->acceptance_expires_at !== null && $this->acceptance_expires_at->isPast() => 'expired',
            default => 'open',
        };
    }

    public function acceptanceUrl(): ?string
    {
        return $this->acceptance_token ? route('proposal.show', $this->acceptance_token) : null;
    }

    /** A fresh link for the client; any earlier one stops working. It lives until the proposal expires. */
    public function issueAcceptanceLink(): void
    {
        $this->forceFill([
            'acceptance_token' => (string) Str::uuid(),
            'acceptance_expires_at' => $this->expires_at?->copy()->endOfDay(),
            'acceptance_revoked_at' => null,
        ])->save();

        ActivityLog::record($this, 'link_issued');
    }

    public function revokeAcceptanceLink(): void
    {
        if ($this->acceptance_token && $this->accepted_at === null && $this->acceptance_revoked_at === null) {
            $this->forceFill(['acceptance_revoked_at' => now()])->save();
            ActivityLog::record($this, 'link_revoked');
        }
    }

    // ---- versions ---------------------------------------------------------------------------

    /**
     * A new version to correct or improve this quote. The old one stays as it was, marked as
     * replaced, and its link to the client stops working.
     */
    public function revise(): self
    {
        abort_if($this->isClosed() || ! $this->isCurrent(), 422);

        return DB::transaction(function (): self {
            $copy = $this->replicate([
                'stage', 'version', 'previous_quote_id', 'superseded_at', 'sent_at', 'binder_number', 'binder_effective_date',
                'paid_at', 'policy_number', 'closed_at', 'loss_reason', 'loss_note', 'competitor',
                'acceptance_token', 'acceptance_expires_at', 'acceptance_revoked_at',
                'accepted_at', 'accepted_signer_name', 'accepted_signature_path', 'accepted_ip', 'accepted_snapshot',
            ]);
            $copy->stage = 'lead';
            $copy->version = $this->version + 1;
            $copy->previous_quote_id = $this->id;
            $copy->save();

            foreach ($this->coverages as $line) {
                $copy->coverages()->create($line->only([
                    'coverage_type_id', 'custom_name', 'limit_amount', 'aggregate_limit', 'deductible', 'premium', 'details', 'notes', 'sort_order',
                ]));
            }

            $this->revokeAcceptanceLink();
            $this->forceFill(['superseded_at' => now()])->save();

            if ($this->application->selected_quote_id === $this->id) {
                $this->application->forceFill(['selected_quote_id' => null])->save();
            }

            return $copy;
        });
    }

    // ---- relations --------------------------------------------------------------------------

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'producer_id');
    }

    public function previousQuote(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_quote_id');
    }

    public function coverages(): HasMany
    {
        return $this->hasMany(QuoteCoverage::class)->orderBy('sort_order');
    }

    public function stageChanges(): HasMany
    {
        return $this->hasMany(QuoteStageChange::class)->latest('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(QuoteDocument::class)->latest('id');
    }

    public function activity(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'subject_id')->where('subject_type', 'quote')->latest('id');
    }

    /** @return array{application_id: ?int, client_id: ?int} */
    public function auditContext(): array
    {
        return ['application_id' => $this->application_id, 'client_id' => $this->client_id];
    }

    public function auditLabel(): string
    {
        return trim(($this->carrier?->name ?? '').' v'.$this->version);
    }
}
