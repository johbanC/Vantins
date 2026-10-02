<?php

namespace App\Support;

use App\Rules\ValidVin;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Data-quality rules shared by the advisor form and the panel.
 *
 *  - rules / validateRows(): HARD blocks, the value is impossible or malformed.
 *  - *Warnings(): SOFT notes, the value is unusual but may be real (shown, never block).
 */
class DataQuality
{
    public const MIN_DRIVER_AGE = 18;

    public const WARN_YOUNG_DRIVER_AGE = 21;

    public const WARN_OLD_DRIVER_AGE = 75;

    public const CDL_EXPIRING_DAYS = 60;

    public const PD_DEDUCTIBLE_MIN = 1000;

    public const PD_DEDUCTIBLE_MAX = 2500;

    public const ROW_FIELDS = [
        'drivers' => ['driver_name', 'dob', 'cdl_number', 'state_issued', 'cdl_issue_date', 'cdl_expiry_date', 'experience', 'date_of_hire'],
        'vehicles' => ['year', 'make', 'vin', 'body_type', 'garaging_zip', 'stated_value', 'has_physical_damage', 'physical_damage_value', 'physical_damage_deductible'],
        'trailers' => ['year', 'make', 'vin', 'body_type', 'stated_value'],
    ];

    public static function applicantRules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone_number' => ['nullable', 'string', 'max:40'],
            'effective_date' => ['nullable', 'date', 'after_or_equal:2000-01-01', 'before_or_equal:+2 years'],
            'power_units' => ['nullable', 'integer', 'between:0,9999'],
            'down_payment' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'number_of_payments' => ['nullable', 'integer', 'between:0,60'],
            'monthly_payment' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    public static function driverRules(): array
    {
        return [
            'driver_name' => ['nullable', 'string', 'max:190'],
            'dob' => ['nullable', 'date', 'after_or_equal:1920-01-01', 'before_or_equal:-'.self::MIN_DRIVER_AGE.' years'],
            'cdl_number' => ['nullable', 'string', 'between:4,25', 'regex:/^[A-Za-z0-9\- ]+$/'],
            'state_issued' => ['nullable', 'string', 'max:40'],
            'cdl_issue_date' => ['nullable', 'date', 'after_or_equal:1950-01-01', 'before_or_equal:today'],
            'cdl_expiry_date' => ['nullable', 'date', 'after_or_equal:1990-01-01', 'before_or_equal:+20 years'],
            'experience' => ['nullable', 'string', 'max:60'],
            'date_of_hire' => ['nullable', 'date', 'after_or_equal:1950-01-01', 'before_or_equal:+1 year'],
        ];
    }

    public static function vehicleRules(array $row = []): array
    {
        return [
            'year' => ['nullable', 'integer', 'between:1950,'.(now()->year + 1)],
            'make' => ['nullable', 'string', 'max:190'],
            'vin' => ['nullable', 'string', new ValidVin($row['year'] ?? null)],
            'body_type' => ['nullable', 'string', 'max:190'],
            'garaging_zip' => ['nullable', 'regex:/^\d{5}(-\d{4})?$/'],
            'stated_value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'has_physical_damage' => ['nullable', 'boolean'],
            'physical_damage_value' => ['nullable', 'required_if:has_physical_damage,true,1', 'numeric', 'gt:0', 'max:99999999'],
            'physical_damage_deductible' => ['nullable', 'required_if:has_physical_damage,true,1', 'numeric', 'gt:0', 'max:9999999'],
        ];
    }

    public static function trailerRules(array $row = []): array
    {
        return array_intersect_key(static::vehicleRules($row), array_flip(self::ROW_FIELDS['trailers']));
    }

    /**
     * Validate a list of rows of one kind. Returns ['drivers.0.dob' => 'message', ...].
     */
    public static function validateRows(string $kind, array $rows): array
    {
        $errors = [];

        foreach (array_values($rows) as $i => $row) {
            $rules = match ($kind) {
                'drivers' => static::driverRules(),
                'vehicles' => static::vehicleRules($row),
                'trailers' => static::trailerRules($row),
            };

            $validator = Validator::make($row, $rules, static::messages(), static::attributes());

            if ($kind === 'drivers') {
                $validator->after(fn (ValidatorInstance $v) => static::driverCrossChecks($v, $row));
            }

            foreach ($validator->errors()->messages() as $field => $messages) {
                $errors["{$kind}.{$i}.{$field}"] = $messages[0];
            }
        }

        return $errors;
    }

    public static function validateApplicant(array $form): array
    {
        $validator = Validator::make($form, static::applicantRules(), static::messages(), static::attributes());

        return collect($validator->errors()->messages())
            ->mapWithKeys(fn ($m, $field) => ["form.{$field}" => $m[0]])
            ->all();
    }

    /** Relationships between two dates of the same driver. */
    protected static function driverCrossChecks(ValidatorInstance $v, array $row): void
    {
        $dob = static::date($row['dob'] ?? null);
        $issue = static::date($row['cdl_issue_date'] ?? null);
        $expiry = static::date($row['cdl_expiry_date'] ?? null);
        $hire = static::date($row['date_of_hire'] ?? null);

        if ($dob && $issue && $issue->lt($dob->copy()->addYears(self::MIN_DRIVER_AGE))) {
            $v->errors()->add('cdl_issue_date', __('app.validation.cdl_issue_before_18'));
        }
        if ($issue && $expiry && $expiry->lte($issue)) {
            $v->errors()->add('cdl_expiry_date', __('app.validation.cdl_expiry_before_issue'));
        }
        if ($dob && $hire && $hire->lt($dob)) {
            $v->errors()->add('date_of_hire', __('app.validation.hire_before_birth'));
        }
    }

    /** @return list<string> */
    public static function driverWarnings(array $row, ?CarbonInterface $reference = null): array
    {
        $reference ??= now();
        $warnings = [];

        if ($dob = static::date($row['dob'] ?? null)) {
            $age = $dob->diffInYears($reference);
            if ($age >= self::MIN_DRIVER_AGE && $age < self::WARN_YOUNG_DRIVER_AGE) {
                $warnings[] = __('app.warning.driver_young', ['age' => self::WARN_YOUNG_DRIVER_AGE]);
            } elseif ($age > self::WARN_OLD_DRIVER_AGE) {
                $warnings[] = __('app.warning.driver_old', ['age' => self::WARN_OLD_DRIVER_AGE]);
            }
        }

        $expiry = static::date($row['cdl_expiry_date'] ?? null);
        if ($expiry && $expiry->lt($reference->copy()->startOfDay())) {
            $warnings[] = __('app.warning.cdl_expired');
        } elseif ($expiry && $expiry->lte($reference->copy()->addDays(self::CDL_EXPIRING_DAYS))) {
            $warnings[] = __('app.warning.cdl_expiring', ['days' => self::CDL_EXPIRING_DAYS]);
        }

        if (filled($row['cdl_number'] ?? null) && (blank($row['cdl_issue_date'] ?? null) || blank($row['cdl_expiry_date'] ?? null))) {
            $warnings[] = __('app.warning.cdl_dates_missing');
        }

        return $warnings;
    }

    /** @return list<string> */
    public static function vehicleWarnings(array $row): array
    {
        $warnings = [];
        $vin = strtoupper(trim((string) ($row['vin'] ?? '')));
        $year = (int) ($row['year'] ?? 0);

        if (strlen($vin) === 17 && $year >= 1981 && preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin) && ! static::vinCheckDigitOk($vin)) {
            $warnings[] = __('app.warning.vin_check_digit');
        }

        if ($year > 0 && $year < now()->year - 30) {
            $warnings[] = __('app.warning.vehicle_old');
        }

        $deductible = (float) ($row['physical_damage_deductible'] ?? 0);
        if (! empty($row['has_physical_damage']) && $deductible > 0
            && ($deductible < self::PD_DEDUCTIBLE_MIN || $deductible > self::PD_DEDUCTIBLE_MAX)) {
            $warnings[] = __('app.warning.pd_deductible_range', [
                'min' => number_format(self::PD_DEDUCTIBLE_MIN), 'max' => number_format(self::PD_DEDUCTIBLE_MAX),
            ]);
        }

        return $warnings;
    }

    /** ISO 3779 / FMVSS 115 check digit (position 9) for North-American VINs. */
    public static function vinCheckDigitOk(string $vin): bool
    {
        $map = array_merge(
            array_combine(range('A', 'H'), range(1, 8)),
            array_combine(['J', 'K', 'L', 'M', 'N'], range(1, 5)),
            ['P' => 7, 'R' => 9],
            array_combine(['S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'], range(2, 9)),
        );
        $weights = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];

        $sum = 0;
        foreach (str_split($vin) as $i => $char) {
            $value = ctype_digit($char) ? (int) $char : ($map[$char] ?? null);
            if ($value === null) {
                return false;
            }
            $sum += $value * $weights[$i];
        }

        $check = $sum % 11;

        return $vin[8] === ($check === 10 ? 'X' : (string) $check);
    }

    protected static function date(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Field labels in the active language, used inside validation messages. */
    public static function attributes(): array
    {
        $attributes = [];
        foreach (array_unique(array_merge(...array_values(self::ROW_FIELDS))) as $field) {
            $attributes[$field] = __('app.'.$field);
        }
        foreach (array_keys(static::applicantRules()) as $field) {
            $attributes[$field] = __('app.'.$field);
        }
        foreach (['garaging_zip', 'has_physical_damage', 'physical_damage_value', 'physical_damage_deductible'] as $field) {
            $attributes[$field] = __('app.'.$field);
        }

        return $attributes;
    }

    public static function messages(): array
    {
        return [
            'dob.before_or_equal' => __('app.validation.dob_min_age', ['age' => self::MIN_DRIVER_AGE]),
            'dob.after_or_equal' => __('app.validation.date_implausible'),
            'cdl_issue_date.before_or_equal' => __('app.validation.date_future'),
            'cdl_issue_date.after_or_equal' => __('app.validation.date_implausible'),
            'cdl_expiry_date.before_or_equal' => __('app.validation.cdl_expiry_too_far'),
            'cdl_expiry_date.after_or_equal' => __('app.validation.date_implausible'),
            'date_of_hire.before_or_equal' => __('app.validation.date_future'),
            'date_of_hire.after_or_equal' => __('app.validation.date_implausible'),
            'cdl_number.regex' => __('app.validation.cdl_chars'),
            'cdl_number.between' => __('app.validation.cdl_length'),
            'garaging_zip.regex' => __('app.validation.zip'),
            'year.between' => __('app.validation.year_range', ['min' => 1950, 'max' => now()->year + 1]),
            'effective_date.before_or_equal' => __('app.validation.date_implausible'),
            'effective_date.after_or_equal' => __('app.validation.date_implausible'),
        ];
    }
}
