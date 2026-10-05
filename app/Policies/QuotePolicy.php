<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

/**
 * admin: everything. agent: the quotes of applications they created (they see those of their
 * clients). viewer: read-only, sees all.
 */
class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Quote $quote): bool
    {
        return $user->isAdmin() || $user->isViewer() || $quote->application->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->canWrite();
    }

    /** Move it along the pipeline, attach documents, send or revoke the link, make a new version. */
    public function manage(User $user, Quote $quote): bool
    {
        return $user->canWrite()
            && $quote->isCurrent()
            && $user->can('manage', $quote->application);
    }

    /** Type the commercial terms: only while it is still a lead. */
    public function update(User $user, Quote $quote): bool
    {
        return $this->manage($user, $quote) && $quote->isEditable();
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $this->update($user, $quote) && $quote->version === 1;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
