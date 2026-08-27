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
        'signed',     // auto: client finished the form and signed
        'in_review',  // manual
        'quoted',     // manual
        'issued',     // manual
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

        // Keep the monthly payment consistent whenever the advisor edits the plan.
        static::saving(fn (Application $application) => $application->applyPaymentPlan());
    }

    /** Monthly Payment = (Total Policy Premium - Down Payment) / # of Payments. */
    public function applyPaymentPlan(): void
    {
        $n = (int) $this->number_of_payments;
        $financed = (float) $this->total_policy_premium - (float) $this->down_payment;

        $this->monthly_payment = ($n > 0 && $financed > 0)
            ? round($financed / $n, 2)
            : null;
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

    /** Total Policy Premium is always the sum of the coverage premiums. */
    public function recalculatePremium(): void
    {
        $sum = (float) $this->coverages()->sum('premium');

        $this->total_policy_premium = $sum > 0 ? $sum : null;
        $this->applyPaymentPlan();
        $this->saveQuietly();
    }

    /** Mark a new status and stamp its timestamp column when present. */
    public function markStatus(string $status): void
    {
        abort_unless(in_array($status, self::STATUSES, true), 422);

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
