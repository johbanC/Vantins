<?php

namespace App\Models\Concerns;

use App\Models\Application;

/**
 * Rows of an application (drivers, vehicles, trailers, coverages) cannot be added, changed or
 * removed once the application is signed: what the client signed stays as it was.
 */
trait LocksWithSignedApplication
{
    public static function bootLocksWithSignedApplication(): void
    {
        $guard = fn ($row) => $row->belongsToSignedApplication() ? false : null;

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    /** Checked against the current database state, never against a relation loaded earlier. */
    public function belongsToSignedApplication(): bool
    {
        if (! $this->application_id) {
            return false;
        }

        return Application::query()->find($this->application_id, ['id', 'status', 'signed_at'])?->isSigned() ?? false;
    }
}
