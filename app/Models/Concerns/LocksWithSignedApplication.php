<?php

namespace App\Models\Concerns;

/**
 * Rows of an application (drivers, vehicles, trailers, coverages) cannot be added, changed or
 * removed once the application is signed: what the client signed stays as it was.
 */
trait LocksWithSignedApplication
{
    public static function bootLocksWithSignedApplication(): void
    {
        $guard = fn ($row) => $row->application?->isSigned() ? false : null;

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }
}
