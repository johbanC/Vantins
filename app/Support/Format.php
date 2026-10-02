<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * One place that decides how dates and money look, in every language,
 * on the client page, the panel and the PDFs.
 */
class Format
{
    /** "Oct 2, 2026" (en) / "2 oct 2026" (es): the month is spelled out, never ambiguous. */
    public static function date(CarbonInterface|string|null $date, ?string $locale = null): string
    {
        if (! $date) {
            return '—';
        }

        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);
        $locale ??= app()->getLocale();

        $text = $date->copy()->locale($locale)->translatedFormat($locale === 'es' ? 'j M Y' : 'M j, Y');

        // Spanish abbreviations come with a trailing dot ("oct."): drop it.
        return $locale === 'es' ? str_replace('.', '', $text) : $text;
    }

    public static function dateTime(CarbonInterface|string|null $date, ?string $locale = null): string
    {
        if (! $date) {
            return '—';
        }

        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return static::date($date, $locale).' '.$date->format('H:i');
    }

    /** Always US dollars with a point for decimals: $1,234.56. */
    public static function money(int|float|string|null $amount): string
    {
        if ($amount === null || $amount === '' || (float) $amount == 0.0) {
            return '—';
        }

        return '$'.number_format((float) $amount, 2);
    }
}
