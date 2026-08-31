<?php

namespace App\Models\Concerns;

use App\Models\Application;

/**
 * Child rows of an application (coverages, drivers, vehicles, trailers) become
 * read-only the moment the parent application is signed / issued: they can no
 * longer be created, edited or deleted.
 */
trait FreezesWithApplication
{
    protected static function bootFreezesWithApplication(): void
    {
        static::saving(fn ($model) => ! $model->belongsToLockedApplication());
        static::deleting(fn ($model) => ! $model->belongsToLockedApplication());
    }

    /** Checked against the current DB state, never a possibly stale relation. */
    public function belongsToLockedApplication(): bool
    {
        if (! $this->application_id) {
            return false;
        }

        return in_array(
            Application::whereKey($this->application_id)->value('status'),
            Application::LOCKED_STATUSES,
            true,
        );
    }
}
