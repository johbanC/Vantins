<?php

namespace App\Policies;

use App\Models\User;

/** Managing staff accounts is an admin-only task. */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    // Nobody deletes their own account by accident.
    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->isNot($model);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
