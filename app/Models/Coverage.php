<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\Format;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coverage extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'premium' => 'decimal:2',
        'details' => 'array',
        'needs_review' => 'boolean',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CoverageType::class, 'coverage_type_id');
    }

    /** @return array{application_id: ?int, client_id: ?int} */
    public function auditContext(): array
    {
        return ['application_id' => $this->application_id, 'client_id' => $this->application?->client_id];
    }

    public function auditLabel(): string
    {
        return $this->displayName('en');
    }

    /** The uniform catalog name in the requested language; the typed name for "Other" or legacy rows. */
    public function displayName(?string $locale = null): string
    {
        if ($this->type && ! $this->type->isRepeatable()) {
            return $this->type->name($locale);
        }

        return (string) ($this->custom_name ?: $this->coverage ?: $this->type?->name($locale));
    }

    /** "$1,000,000" for amounts; anything typed in the old free-text field is shown as it was. */
    public static function formatAmount(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return is_numeric($value) ? Format::money($value) : $value;
    }

    public function displayLimit(): string
    {
        $limit = static::formatAmount($this->limit_amount);

        return $this->aggregate_limit
            ? $limit.' / '.__('app.aggregate').' '.static::formatAmount($this->aggregate_limit)
            : $limit;
    }

    /**
     * Extra lines under the coverage: the vehicles / trailers it applies to, what an umbrella sits
     * over, and the notes.
     *
     * @return list<string>
     */
    public function detailLines(): array
    {
        $lines = [];

        foreach (['vehicle_ids' => [Vehicle::class, 'app.vehicles_schedule'], 'trailer_ids' => [Trailer::class, 'app.trailers_schedule']] as $key => [$model, $label]) {
            $ids = (array) ($this->details[$key] ?? []);

            if ($ids === []) {
                continue;
            }

            $units = $model::query()->where('application_id', $this->application_id)->whereKey($ids)->orderBy('sort_order')->get()
                ->map(fn ($unit) => trim($unit->year.' '.$unit->make.' '.$unit->vin))
                ->implode('; ');

            $lines[] = __($label).': '.$units;
        }

        if ($this->underlying_coverage) {
            $lines[] = __('app.underlying_coverage').': '.$this->underlying_coverage;
        }
        if ($this->notes) {
            $lines[] = $this->notes;
        }

        return $lines;
    }
}
