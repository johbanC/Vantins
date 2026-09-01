<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Application extends Model
{
    use HasFactory;

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
     * Columns that may still change on a locked application: status handling,
     * the signature block, the generated PDF and the display language. Any other
     * column is frozen once the client has signed.
     */
    public const MUTABLE_WHEN_LOCKED = [
        'status', 'locale', 'pdf_path',
        'signer_name', 'signature_path', 'signed_ip', 'disclosure_accepted_at',
        'submitted_at', 'in_review_at', 'quoted_at', 'signed_at', 'issued_at',
        'updated_at',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'effective_date' => 'date',
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
    ];

    protected static function booted(): void
    {
        static::creating(function (Application $application): void {
            $application->token ??= (string) Str::uuid();
            $application->verification_code ??= strtoupper(Str::random(10));
            $application->locale ??= 'en';
            $application->status ??= 'created';
        });

        // Total Policy Premium is derived from the payment plan the advisor enters.
        static::saving(fn (Application $application) => $application->applyPaymentPlan());

        // Once signed / issued, only the status + signature block may change.
        static::updating(function (Application $application): bool {
            if (! in_array($application->getOriginal('status'), self::LOCKED_STATUSES, true)) {
                return true;
            }

            $touchesFrozenColumn = collect(array_keys($application->getDirty()))
                ->diff(self::MUTABLE_WHEN_LOCKED)
                ->isNotEmpty();

            return ! $touchesFrozenColumn;
        });

        // Only a brand-new (unsigned) application may ever be deleted.
        static::deleting(fn (Application $application) => $application->isDeletable());
    }

    public function isLocked(): bool
    {
        return in_array($this->status, self::LOCKED_STATUSES, true);
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

    /** Total Policy Premium = Down Payment + (Monthly Payment x Number of Payments). */
    public function applyPaymentPlan(): void
    {
        $down = (float) $this->down_payment;
        $monthly = (float) $this->monthly_payment;
        $n = max((int) $this->number_of_payments, 0);

        $total = $down + $monthly * $n;

        $this->total_policy_premium = $total > 0 ? round($total, 2) : null;
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

    /** Allowed manual status moves out of a locked (signed) application. */
    public const LOCKED_STATUS_TRANSITIONS = ['signed' => ['issued']];

    /** Mark a new status and stamp its timestamp column when present. */
    public function markStatus(string $status): void
    {
        abort_unless(in_array($status, self::STATUSES, true), 422);

        // A signed / issued document may only move forward to "issued" – never
        // back to an editable or deletable state.
        if ($this->isLocked() && $status !== $this->status) {
            $allowed = self::LOCKED_STATUS_TRANSITIONS[$this->status] ?? [];
            abort_unless(in_array($status, $allowed, true), 403);
        }

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
}
