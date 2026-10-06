<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Application extends Model
{
    use HasFactory;
    use RecordsActivity;

    public const STATUSES = [
        'created',    // auto: application created, link ready to send
        'signed',     // auto: client verified + signed
        'in_review',  // manual: advisors reviewing the submitted info
        'quoted',     // manual: quote created and sent to the client
        'issued',     // manual: client accepted -> policy issued (final)
        'cancelled',  // manual: the client cancelled, no further progress
    ];

    /** Signed / issued: permanent, read-only (has a legal signature). */
    public const LOCKED_STATUSES = ['signed', 'issued'];

    /**
     * Once signed, an application is a closed record. Only these may still change: the workflow
     * status, the link, who it is filed under and the language of the pages. Everything the client
     * signed (data, finance, agency, signature) is frozen; a correction is a new revision.
     */
    public const CHANGEABLE_AFTER_SIGNING = [
        'status', 'in_review_at', 'quoted_at', 'issued_at', 'selected_quote_id', 'client_id', 'pdf_path',
        'token', 'link_expires_at', 'link_revoked_at', 'link_pin', 'is_demo', 'locale', 'superseded_at', 'welcome_letter_sent_at', 'updated_at',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'link_expires_at' => 'datetime',
        'link_revoked_at' => 'datetime',
        'link_pin' => 'encrypted',
        'superseded_at' => 'datetime',
        'effective_date' => 'date',
        'is_demo' => 'boolean',
        'total_policy_premium' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'number_of_payments' => 'integer',
        'monthly_payment' => 'decimal:2',
        'power_units' => 'integer',
        'disclosure_accepted_at' => 'datetime',
        'submitted_at' => 'datetime',
        'in_review_at' => 'datetime',
        'quoted_at' => 'datetime',
        'signed_at' => 'datetime',
        'issued_at' => 'datetime',
        'welcome_letter_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Application $application): void {
            $application->token ??= (string) Str::uuid();
            $application->verification_code ??= strtoupper(Str::random(10));
            $application->locale ??= 'en';
            $application->status ??= 'created';
            $application->link_pin ??= (string) random_int(100000, 999999);
            $application->link_expires_at ??= now()->addDays(config('vantins.link_days', 30));
        });

        // A signed application cannot be edited behind the client's back.
        static::updating(function (Application $application): ?bool {
            if (! $application->wasSignedBeforeThisSave()) {
                return null;
            }

            return array_diff(array_keys($application->getDirty()), self::CHANGEABLE_AFTER_SIGNING) === [] ? null : false;
        });

        // Total Policy Premium is derived from the payment plan the advisor enters.
        static::saving(fn (Application $application) => $application->applyPaymentPlan());

        // Only a brand-new (unsigned) application may ever be deleted.
        static::deleting(fn (Application $application) => $application->isDeletable());
    }

    /** A new application for a client, prefilled with what the client record already knows. */
    public static function createForClient(Client $client, ?User $advisor = null): self
    {
        return static::create([
            'client_id' => $client->id,
            'company_name' => $client->company_name,
            'company_representative' => $client->contact_name,
            'email' => $client->email,
            'phone_number' => $client->phone,
            'us_dot_number' => $client->us_dot_number,
            'mailing_address' => $client->mailing_address,
            'parking_address' => $client->parking_address,
            'is_demo' => (bool) $client->is_demo,
            'created_by' => $advisor?->id,
            'status' => 'created',
            'locale' => 'en',
            // The agency block is fixed: Vantins + the advisor creating the application.
            'agency_name' => config('vantins.agency_name'),
            'agency_phone' => config('vantins.agency_phone'),
            'contact_agent_name' => $advisor?->name,
        ]);
    }

    /** Admins and read-only users see everything; an agent only what is theirs. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->isViewer()) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('created_by', $user->id)
            ->orWhereHas('client', fn (Builder $c) => $c->where('assigned_user_id', $user->id)));
    }

    /** Created by the user, or belongs to a client assigned to the user. */
    public function isOwnedBy(User $user): bool
    {
        return $this->created_by === $user->id || $this->client?->assigned_user_id === $user->id;
    }

    /** Full CDL number and date of birth: admins and whoever created the application. */
    public function canRevealSensitiveData(User $user): bool
    {
        return $user->isAdmin() || $this->created_by === $user->id;
    }

    /** Signed by the client (or issued): the data is frozen for good. */
    public function isSigned(): bool
    {
        return $this->signed_at !== null || in_array($this->status, self::LOCKED_STATUSES, true);
    }

    protected function wasSignedBeforeThisSave(): bool
    {
        return $this->getOriginal('signed_at') !== null || in_array($this->getOriginal('status'), self::LOCKED_STATUSES, true);
    }

    public function isLocked(): bool
    {
        return $this->isSigned();
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_at !== null;
    }

    // ---- the client's link ------------------------------------------------------------------

    /** 'completed' (signed) | 'revoked' | 'expired' | 'active' */
    public function linkStatus(): string
    {
        return match (true) {
            $this->isSigned() => 'completed',
            $this->link_revoked_at !== null => 'revoked',
            $this->link_expires_at !== null && $this->link_expires_at->isPast() => 'expired',
            default => 'active',
        };
    }

    /** Driver data sits behind a PIN, for the client's link only. */
    public function needsPin(): bool
    {
        return config('vantins.link_pin') && $this->link_pin !== null && $this->drivers()->exists();
    }

    /** A new link: the old address stops working, the clock restarts and the PIN changes. */
    public function renewLink(): void
    {
        $this->forceFill([
            'token' => (string) Str::uuid(),
            'link_expires_at' => now()->addDays(config('vantins.link_days', 30)),
            'link_revoked_at' => null,
            'link_pin' => (string) random_int(100000, 999999),
        ])->save();

        ActivityLog::record($this, 'link_issued');
    }

    public function revokeLink(): void
    {
        if ($this->link_revoked_at === null) {
            $this->forceFill(['link_revoked_at' => now()])->save();
            ActivityLog::record($this, 'link_revoked');
        }
    }

    // ---- revisions --------------------------------------------------------------------------

    /**
     * The way to correct a signed application: a copy of it as a new version, with the reason, to be
     * reviewed and signed again. The signed one stays exactly as it was, marked as replaced.
     */
    public function createRevision(string $reason): self
    {
        abort_unless($this->isSigned() && ! $this->isSuperseded(), 422);

        return DB::transaction(function () use ($reason): self {
            $copy = $this->replicate([
                'token', 'verification_code', 'status', 'link_expires_at', 'link_revoked_at', 'link_pin',
                'signed_at', 'disclosure_accepted_at', 'signer_name', 'signature_path', 'signed_ip', 'pdf_path',
                'submitted_at', 'in_review_at', 'quoted_at', 'issued_at', 'selected_quote_id', 'superseded_at', 'revision_reason',
            ]);
            $copy->status = 'created';
            $copy->revision = $this->revision + 1;
            $copy->revision_of_id = $this->id;
            $copy->revision_reason = $reason;
            $copy->save();

            // Rows are copied as they are; coverages refer to vehicles / trailers by id, so those ids
            // are translated to the copies.
            $newIds = ['vehicle_ids' => [], 'trailer_ids' => []];

            foreach (['drivers' => null, 'vehicles' => 'vehicle_ids', 'trailers' => 'trailer_ids'] as $relation => $idsKey) {
                foreach ($this->{$relation} as $row) {
                    $new = $row->replicate()->forceFill(['application_id' => $copy->id]);
                    $new->save();

                    if ($idsKey) {
                        $newIds[$idsKey][$row->id] = $new->id;
                    }
                }
            }

            foreach ($this->coverages as $row) {
                $new = $row->replicate()->forceFill(['application_id' => $copy->id]);
                $details = $row->details ?? [];

                foreach ($newIds as $idsKey => $map) {
                    if (isset($details[$idsKey])) {
                        $details[$idsKey] = array_values(array_filter(array_map(fn ($id) => $map[$id] ?? null, $details[$idsKey])));
                    }
                }

                $new->details = $details ?: null;
                $new->save();
            }

            $this->forceFill(['superseded_at' => now()])->save();
            $this->revokeLink();

            ActivityLog::record($copy, 'revision_created', ['revision_reason' => [null, $reason]]);

            return $copy->refresh();
        });
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'revision_of_id');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isDeletable(): bool
    {
        return $this->status === 'created';
    }

    /**
     * The branded PDF is only available once the client has signed: before that
     * the application is still editable, so a generated document could show data
     * the client never agreed to.
     */
    public function canGeneratePdf(): bool
    {
        return $this->isLocked() && (bool) $this->signature_path;
    }

    /** The welcome letter follows the signed document: same availability rule. */
    public function canSendWelcomeLetter(): bool
    {
        return $this->canGeneratePdf();
    }

    /** Person the documents are addressed to. */
    public function recipientName(): string
    {
        return $this->signer_name
            ?: $this->company_representative
            ?: $this->company_name
            ?: '';
    }

    /**
     * Stamp the moment the welcome letter first goes out. The date printed on
     * the letter is this timestamp, so it stays fixed on later downloads.
     */
    public function markWelcomeLetterSent(): void
    {
        if (! $this->welcome_letter_sent_at) {
            $this->forceFill(['welcome_letter_sent_at' => now()])->save();
        }
    }

    /** Total Policy Premium = Down Payment + (Monthly Payment x Number of Payments). */
    public function applyPaymentPlan(): void
    {
        $down = (float) $this->down_payment;
        $monthly = (float) $this->monthly_payment;
        $n = max((int) $this->number_of_payments, 0);

        $total = $down + $monthly * $n;

        $this->total_policy_premium = $total > 0 ? round($total, 2) : null;
    }

    public function activity(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'application_id')->latest('id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->latest('id');
    }

    /** The alternative currently in front of the client. */
    public function selectedQuote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'selected_quote_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class)->orderBy('sort_order');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class)->orderBy('sort_order');
    }

    public function trailers(): HasMany
    {
        return $this->hasMany(Trailer::class)->orderBy('sort_order');
    }

    public function coverages(): HasMany
    {
        return $this->hasMany(Coverage::class)->orderBy('sort_order');
    }

    /** Recompute the derived Total Policy Premium and persist quietly. */
    public function recalculatePremium(): void
    {
        $this->applyPaymentPlan();
        $this->saveQuietly();
    }

    /** Mark a new status and stamp its timestamp column when present. */
    public function markStatus(string $status): void
    {
        abort_unless(in_array($status, self::STATUSES, true), 422);

        // A signed document is a closed record: it moves forward through review, quote and issue (or is
        // cancelled), but never back to the editable, deletable "created" state.
        abort_if($this->isLocked() && $status === 'created', 403);

        $column = match ($status) {
            'signed' => 'signed_at',
            'in_review' => 'in_review_at',
            'quoted' => 'quoted_at',
            'issued' => 'issued_at',
            default => null,
        };

        $this->status = $status;
        if ($column && ! $this->{$column}) {
            $this->{$column} = now();
        }
        $this->save();
    }

    /** @return array{application_id: int, client_id: ?int} */
    public function auditContext(): array
    {
        return ['application_id' => $this->getKey(), 'client_id' => $this->client_id];
    }

    /** Text for pickers and lists: an application may have no company name yet. */
    public function pickerLabel(): string
    {
        return $this->company_name ?: '#'.$this->getKey();
    }
}
