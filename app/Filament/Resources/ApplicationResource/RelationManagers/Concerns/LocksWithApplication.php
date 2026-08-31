<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers\Concerns;

use App\Models\Application;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns a relation manager fully read-only once the owning application has been
 * signed / issued: no create, edit, delete or reorder, and the form is locked.
 */
trait LocksWithApplication
{
    public function isApplicationLocked(): bool
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Application && $owner->isLocked();
    }

    public function isReadOnly(): bool
    {
        return $this->isApplicationLocked() || parent::isReadOnly();
    }

    protected function canCreate(): bool
    {
        return ! $this->isApplicationLocked() && parent::canCreate();
    }

    protected function canEdit(Model $record): bool
    {
        return ! $this->isApplicationLocked() && parent::canEdit($record);
    }

    protected function canDelete(Model $record): bool
    {
        return ! $this->isApplicationLocked() && parent::canDelete($record);
    }

    protected function canDeleteAny(): bool
    {
        return ! $this->isApplicationLocked() && parent::canDeleteAny();
    }

    protected function canReorder(): bool
    {
        return ! $this->isApplicationLocked() && parent::canReorder();
    }
}
