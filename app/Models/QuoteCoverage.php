<?php

namespace App\Models;

use App\Support\Format;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A coverage as one carrier quoted it. Frozen once the client has accepted the quote. */
class QuoteCoverage extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'limit_amount' => 'decimal:2',
        'aggregate_limit' => 'decimal:2',
        'deductible' => 'decimal:2',
        'premium' => 'decimal:2',
        'details' => 'array',
    ];

    protected static function booted(): void
    {
        $frozen = fn (QuoteCoverage $line) => $line->quote?->isAccepted() ? false : null;

        static::creating($frozen);
        static::updating($frozen);
        static::deleting($frozen);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CoverageType::class, 'coverage_type_id');
    }

    public function displayName(?string $locale = null): string
    {
        if ($this->type && ! $this->type->isRepeatable()) {
            return $this->type->name($locale);
        }

        return (string) ($this->custom_name ?: $this->type?->name($locale));
    }

    public function displayLimit(): string
    {
        $limit = Format::money($this->limit_amount);

        return $this->aggregate_limit
            ? $limit.' / '.__('app.aggregate').' '.Format::money($this->aggregate_limit)
            : $limit;
    }
}
