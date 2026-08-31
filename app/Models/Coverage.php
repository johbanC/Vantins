<?php

namespace App\Models;

use App\Models\Concerns\FreezesWithApplication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coverage extends Model
{
    use FreezesWithApplication;

    protected $guarded = ['id'];

    protected $casts = [
        'premium' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function getFormattedLimitAmountAttribute(): ?string
    {
        return self::formatAmount($this->limit_amount);
    }

    public function getFormattedDeductibleAttribute(): ?string
    {
        return self::formatAmount($this->deductible);
    }

    /**
     * Group a free-text amount with thousands separators for display.
     * Non-numeric entries ("CSL", "1M", ranges, …) are returned untouched.
     */
    public static function formatAmount(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return $value;
        }

        $value = trim($value);
        $prefix = str_starts_with($value, '$') ? '$' : '';
        $number = str_replace([',', ' '], '', ltrim($value, '$'));

        if (! is_numeric($number)) {
            return $value;
        }

        $formatted = str_contains($number, '.')
            ? number_format((float) $number, 2)
            : number_format((int) $number);

        return $prefix.$formatted;
    }
}
