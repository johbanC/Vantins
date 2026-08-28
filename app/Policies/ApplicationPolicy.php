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
