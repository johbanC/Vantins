<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

/**
 * admin: everything. agent: sees applications they created or of their clients, edits only the ones
 * they created. viewer: read-only, sees all.
 */
class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Application $application): bool
    {
        return $user->isAdmin() || $user->isViewer() || $application->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->canWrite();
    }

    public function update(User $user, Application $application): bool
    {
        return $user->canWrite() && $application->canRevealSensitiveData($user);
    }

    public function reorder(User $user): bool
    {
        return $user->canWrite();
    }

    // A signed / issued document can never be deleted.
    public function delete(User $user, Application $application): bool
    {
        return $this->update($user, $application) && $application->isDeletable();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canWrite();
    }

    public function forceDelete(User $user, Application $application): bool
    {
        return $this->delete($user, $application);
    }

    public function restore(User $user, Application $application): bool
    {
        return $this->update($user, $application);
    }
}
