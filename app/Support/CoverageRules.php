<?php

namespace App\Support;

use App\Models\CoverageType;
use Illuminate\Support\Collection;

/**
 * Hard rules for the coverages an advisor selects: required fields per coverage, amounts that
 * come from the catalog, and no coverage added twice.
 */
class CoverageRules
{
    /**
     * @param  list<array<string, mixed>>  $rows  one row per ticked coverage: ['type' => key, ...]
     * @param  Collection<int, CoverageType>  $types  every type that may appear, keyed by key
     * @param  list<int>  $vehicleIds  vehicles of this application
     * @param  list<int>  $trailerIds  trailers of this application
     * @return array<string, string> 'coverages.0.limit_amount' => message
     */
    public static function validateRows(array $rows, Collection $types, array $vehicleIds = [], array $trailerIds = []): array
    {
        $errors = [];
        $seen = [];

        foreach (array_values($rows) as $i => $row) {
            $key = "coverages.{$i}";
            $type = $types->get($row['type'] ?? null);

            if (! $type) {
                $errors["{$key}.type"] = __('app.validation.coverage_unknown');

                continue;
            }

            // The same coverage cannot be added twice ("Other" only with a different name).
            $identity = $type->isRepeatable() ? 'other:'.mb_strtolower(trim((string) ($row['custom_name'] ?? ''))) : $type->key;
            if (isset($seen[$identity])) {
                $errors["{$key}.".($type->isRepeatable() ? 'custom_name' : 'type')] = __('app.validation.coverage_duplicate');
            }
            $seen[$identity] = true;

            if ($type->hasField('custom_name') && blank($row['custom_name'] ?? null)) {
                $errors["{$key}.custom_name"] = static::required('custom_name');
            }

            foreach (['limit' => 'limit_amount', 'aggregate' => 'aggregate_limit', 'deductible' => 'deductible'] as $field => $column) {
                if ($type->hasField($field)) {
                    $error = static::amountError($type, $field, $row[$column] ?? null);

                    if ($error) {
                        $errors["{$key}.{$column}"] = $error;
                    }
                }
            }

            if ($type->hasField('limit') && $type->hasField('aggregate')
                && is_numeric($row['limit_amount'] ?? null) && is_numeric($row['aggregate_limit'] ?? null)
                && (float) $row['aggregate_limit'] < (float) $row['limit_amount']) {
                $errors["{$key}.aggregate_limit"] = __('app.validation.coverage_aggregate');
            }

            if ($type->hasField('underlying') && blank($row['underlying_coverage'] ?? null)) {
                $errors["{$key}.underlying_coverage"] = static::required('underlying_coverage');
            }

            foreach (['vehicles' => ['vehicle_ids', $vehicleIds], 'trailers' => ['trailer_ids', $trailerIds]] as $field => [$column, $available]) {
                if (! $type->hasField($field)) {
                    continue;
                }

                $picked = array_filter((array) ($row[$column] ?? []));

                if ($picked === [] || array_diff($picked, $available) !== []) {
                    $errors["{$key}.{$column}"] = __('app.validation.coverage_pick_units');
                }
            }

            if (mb_strlen((string) ($row['notes'] ?? '')) > 1000) {
                $errors["{$key}.notes"] = __('validation.max.string', ['attribute' => __('app.notes'), 'max' => 1000]);
            }
        }

        return $errors;
    }

    protected static function amountError(CoverageType $type, string $field, mixed $value): ?string
    {
        if (blank($value)) {
            return static::required($field);
        }
        if (! is_numeric($value) || (float) $value <= 0) {
            return __('app.validation.coverage_amount');
        }

        $options = $type->options($field);

        if ($options !== [] && ! in_array((float) $value, array_map('floatval', $options), true)) {
            return __('app.validation.coverage_option');
        }

        return null;
    }

    protected static function required(string $field): string
    {
        return __('app.validation.coverage_required', ['field' => __('app.'.$field)]);
    }
}
