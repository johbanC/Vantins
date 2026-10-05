<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

/**
 * admin: everything. agent: sees applications they created or of their clients, works only the
 * ones they created. viewer: read-only, sees all.
 *
 * Once the client signs, the data is frozen: nobody "updates" it any more. The owner still
 * "manages" the application (status, link, quotes, a new version).
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

    /** Work with the application: status, link, quotes, documents, a new version. */
    public function manage(User $user, Application $application): bool
    {
        return $user->canWrite() && $application->canRevealSensitiveData($user);
    }

    /** Change its data: only while it is not signed. */
    public function update(User $user, Application $application): bool
    {
        return $this->manage($user, $application) && ! $application->isSigned();
    }

    /** Correct a signed application by creating a new version of it. */
    public function revise(User $user, Application $application): bool
    {
        return $this->manage($user, $application) && $application->isSigned() && ! $application->isSuperseded();
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
