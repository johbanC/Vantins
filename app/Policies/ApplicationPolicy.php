<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    // All staff (agent or admin) may see and work on every application.
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Application $application): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    // The record stays viewable after signing, but every editable section is
    // disabled and the Save button is removed (see EditApplication /
    // ApplicationResource::form). The model itself also refuses to persist
    // changes to a locked application, so this can safely stay open.
    public function update(User $user, Application $application): bool
    {
        return true;
    }

    public function reorder(User $user): bool
    {
        return true;
    }

    // A signed / issued document can never be deleted.
    public function delete(User $user, Application $application): bool
    {
        return $application->isDeletable();
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }

    public function forceDelete(User $user, Application $application): bool
    {
        return $application->isDeletable();
    }

    public function restore(User $user, Application $application): bool
    {
        return true;
    }
}
