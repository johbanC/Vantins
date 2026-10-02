<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Hard VIN check: only characters a VIN can contain, and the full 17 once the
 * vehicle is 1981 or newer (older vehicles may carry a shorter serial).
 * The check-digit is only a warning (see DataQuality::vinWarning).
 */
class ValidVin implements ValidationRule
{
    public function __construct(private readonly mixed $year = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $vin = strtoupper(trim((string) $value));

        if ($vin === '') {
            return;
        }

        if (! preg_match('/^[A-HJ-NPR-Z0-9]+$/', $vin)) {
            $fail('app.validation.vin_chars')->translate();

            return;
        }

        $year = (int) $this->year;
        $legacy = $year > 0 && $year < 1981;

        if (strlen($vin) > 17 || (! $legacy && strlen($vin) !== 17) || ($legacy && strlen($vin) < 5)) {
            $fail('app.validation.vin_length')->translate();
        }
    }
}
