<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    use RecordsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'is_demo' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Store the searchable, normalized forms next to what the advisor typed.
        static::saving(function (Client $client): void {
            $client->phone_normalized = static::normalizePhone($client->phone);
            $client->email = $client->email ? mb_strtolower(trim($client->email)) : null;
            $client->us_dot_number = static::digits($client->us_dot_number);
            $client->mc_number = static::digits($client->mc_number);
        });
    }

    /** "+1 (754) 290-0308" and "754-290-0308" both become "7542900308". */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return $digits === '' ? null : $digits;
    }

    /** "MC-123456" / "DOT 98765" become "123456" / "98765". */
    public static function digits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    /**
     * Free-text search over name, company, e-mail, phone (ignoring dashes,
     * parentheses, spaces and +1) and DOT / MC numbers.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $digits = static::digits($term);
        $phone = static::normalizePhone($term);

        return $query->where(function (Builder $q) use ($like, $digits, $phone): void {
            $q->where('company_name', 'like', $like)
                ->orWhere('contact_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like);

            if ($digits !== null && strlen($digits) >= 3) {
                $q->orWhere('us_dot_number', 'like', "%{$digits}%")
                    ->orWhere('mc_number', 'like', "%{$digits}%")
                    ->orWhere('phone_normalized', 'like', '%'.$phone.'%');
            }
        });
    }

    /**
     * Other clients that share an e-mail, phone, DOT or MC number with the given data.
     *
     * @param  array{email?: ?string, phone?: ?string, us_dot_number?: ?string, mc_number?: ?string}  $data
     * @return Collection<int, array{client: Client, reasons: list<string>}>
     */
    public static function duplicatesOf(array $data, ?int $ignoreId = null): Collection
    {
        $email = ! empty($data['email']) ? mb_strtolower(trim($data['email'])) : null;
        $phone = static::normalizePhone($data['phone'] ?? null);
        $dot = static::digits($data['us_dot_number'] ?? null);
        $mc = static::digits($data['mc_number'] ?? null);

        if (! $email && ! $phone && ! $dot && ! $mc) {
            return collect();
        }

        return static::query()
            ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->where(function (Builder $q) use ($email, $phone, $dot, $mc): void {
                $q->whereRaw('1 = 0');
                $email && $q->orWhere('email', $email);
                $phone && $q->orWhere('phone_normalized', $phone);
                $dot && $q->orWhere('us_dot_number', $dot);
                $mc && $q->orWhere('mc_number', $mc);
            })
            ->limit(5)
            ->get()
            ->map(fn (Client $client) => [
                'client' => $client,
                'reasons' => array_values(array_filter([
                    $email && $client->email === $email ? 'email' : null,
                    $phone && $client->phone_normalized === $phone ? 'phone' : null,
                    $dot && $client->us_dot_number === $dot ? 'us_dot_number' : null,
                    $mc && $client->mc_number === $mc ? 'mc_number' : null,
                ])),
            ]);
    }

    /** Admins and read-only users see every client; an agent only the ones assigned to or created by them. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->isViewer()) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('assigned_user_id', $user->id)
            ->orWhere('created_by', $user->id));
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->assigned_user_id === $user->id || $this->created_by === $user->id;
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class)->latest();
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->latest('id');
    }

    /**
     * Where this client is commercially: the furthest pipeline stage among the current quotes,
     * or the way the last ones closed when nothing is open. Null when there is no quote yet.
     */
    public function commercialStage(): ?string
    {
        $current = $this->quotes->whereNull('superseded_at');

        $furthest = $current->map(fn (Quote $q) => array_search($q->stage, Quote::STAGES, true))->filter(fn ($i) => $i !== false)->max();

        if ($furthest !== null) {
            return Quote::STAGES[$furthest];
        }

        return $current->isEmpty() ? null : 'lost';
    }

    public function activity(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'client_id')->latest('id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array{application_id: null, client_id: int} */
    public function auditContext(): array
    {
        return ['application_id' => null, 'client_id' => $this->getKey()];
    }
}
