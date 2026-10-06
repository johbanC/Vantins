<?php

namespace App\Listeners;

use Illuminate\Auth\Events\PasswordReset;

/** Choosing a password through the invitation (or a reset) link ends the pending invitation. */
class MarkPasswordAsSet
{
    public function handle(PasswordReset $event): void
    {
        $event->user->forceFill([
            'password_set_at' => now(),
            // The person just proved they can read this mailbox.
            'email_verified_at' => $event->user->email_verified_at ?? now(),
        ])->saveQuietly();
    }
}
