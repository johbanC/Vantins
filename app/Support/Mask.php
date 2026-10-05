<?php

namespace App\Support;

/** Hides personal identifiers from people who are not allowed to read them in full. */
class Mask
{
    public const HIDDEN = '••••••';

    /** "D123-4567-8901" becomes "••••••8901". */
    public static function cdl(?string $cdl): string
    {
        $cdl = trim((string) $cdl);

        if ($cdl === '') {
            return '—';
        }

        return self::HIDDEN.mb_substr($cdl, -4);
    }

    public static function dob(mixed $dob): string
    {
        return blank($dob) ? '—' : self::HIDDEN;
    }
}
