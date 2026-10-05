<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    protected $guarded = ['id'];

    protected $casts = [
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
}
