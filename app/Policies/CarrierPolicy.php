<?php

namespace App\Policies;

use App\Models\Carrier;
use App\Models\User;

/** Everyone on staff sees the carriers, writers can add one while quoting, admins maintain the list. */
class CarrierPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Carrier $carrier): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canWrite();
    }

    public function update(User $user, Carrier $carrier): bool
    {
        return $user->isAdmin();
    }

    // A carrier that has quotes is history: switch it off instead.
    public function delete(User $user, Carrier $carrier): bool
    {
        return $user->isAdmin() && ! $carrier->quotes()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
