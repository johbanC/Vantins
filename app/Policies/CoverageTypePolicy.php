<?php

namespace App\Policies;

use App\Models\CoverageType;
use App\Models\User;

/** Everyone reads the catalog through the forms; only admins change it, and nobody deletes a coverage kind. */
class CoverageTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, CoverageType $type): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CoverageType $type): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, CoverageType $type): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
