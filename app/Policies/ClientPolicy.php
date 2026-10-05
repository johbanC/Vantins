<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * admin: everything. agent: only clients assigned to or created by them. viewer: read-only, sees all.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        return $user->isAdmin() || $user->isViewer() || $client->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->canWrite();
    }

    public function update(User $user, Client $client): bool
    {
        return $user->canWrite() && ($user->isAdmin() || $client->isOwnedBy($user));
    }

    // A client with applications is history and is never deleted.
    public function delete(User $user, Client $client): bool
    {
        return $this->update($user, $client) && ! $client->applications()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canWrite();
    }
}
