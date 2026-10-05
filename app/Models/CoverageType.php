<?php

namespace App\Models;

use Database\Factories\CoverageTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * An entry of the coverage catalog. Names, options and availability are edited by admins;
 * which fields each coverage asks for is fixed here, in FIELDS.
 */
class CoverageType extends Model
{
    /** @use HasFactory<CoverageTypeFactory> */
    use \App\Models\Concerns\RecordsActivity, HasFactory;

    /** Fields shown once a coverage is ticked. "limit", "aggregate" and "deductible" use the type's options. */
    public const FIELDS = [
        'auto_liability' => ['limit', 'notes'],
        'motor_truck_cargo' => ['limit', 'deductible', 'notes'],
        'physical_damage' => ['vehicles', 'deductible', 'notes'],
        'general_liability' => ['limit', 'aggregate', 'notes'],
        'reefer_breakdown' => ['limit', 'deductible', 'notes'],
        'trailer_interchange' => ['limit', 'deductible', 'trailers', 'notes'],
        'umbrella' => ['limit', 'underlying', 'notes'],
        'um' => ['limit', 'notes'],
        'p_and_p' => ['limit', 'deductible', 'notes'],
        'other' => ['custom_name', 'limit', 'deductible', 'notes'],
    ];

    /** "Other" can be added more than once (with different names); every other coverage only once. */
    public const REPEATABLE = 'other';

    protected $guarded = ['id'];

    protected $casts = [
        'limit_options' => 'array',
        'aggregate_options' => 'array',
        'deductible_options' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** The active catalog, keyed by coverage key, in display order. */
    public static function catalog(): Collection
    {
        return static::query()->active()->orderBy('sort_order')->get()->keyBy('key');
    }

    public function auditLabel(): string
    {
        return $this->name_en;
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'es' ? $this->name_es : $this->name_en;
    }

    /** @return list<string> */
    public function fields(): array
    {
        return self::FIELDS[$this->key] ?? ['limit', 'deductible', 'notes'];
    }

    public function hasField(string $field): bool
    {
        return in_array($field, $this->fields(), true);
    }

    /**
     * The amounts offered for "limit", "aggregate" or "deductible". Empty means the advisor
     * types a free amount.
     *
     * @return list<int|float>
     */
    public function options(string $field): array
    {
        $column = ['limit' => 'limit_options', 'aggregate' => 'aggregate_options', 'deductible' => 'deductible_options'][$field] ?? null;

        return $column ? array_values(array_filter((array) $this->{$column}, 'is_numeric')) : [];
    }

    public function isRepeatable(): bool
    {
        return $this->key === self::REPEATABLE;
    }

    public function coverages(): HasMany
    {
        return $this->hasMany(Coverage::class);
    }
}
