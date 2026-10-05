<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

/** The audit trail is read by admins and never changed by anybody. */
class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ActivityLog $log): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ActivityLog $log): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $log): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
