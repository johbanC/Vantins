<?php

namespace App\Support;

use App\Mail\UserInvitationMail;
use App\Models\ActivityLog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * An invited user chooses their own password through a link: the administrator never knows it.
 * The link is the panel's password-reset page; a new link replaces the previous one.
 */
class UserInvitations
{
    public static function link(User $user): string
    {
        $panel = Filament::getPanel('admin');
        $token = Password::broker($panel->getAuthPasswordBroker())->createToken($user);

        return $panel->getResetPasswordUrl($token, $user);
    }

    /** How long a link keeps working, in days (the password broker's own expiry). */
    public static function validDays(): int
    {
        return max(1, (int) ceil(config('auth.passwords.users.expire') / 1440));
    }

    /** Email the link. Throws when the mail cannot be sent: the caller offers the copy-link fallback. */
    public static function send(User $user): void
    {
        Mail::to($user)->send(new UserInvitationMail($user, static::link($user), static::validDays()));

        $user->forceFill(['invited_at' => now()])->saveQuietly();
        ActivityLog::record($user, 'link_issued', ['invitation' => [null, 'email']]);
    }

    /** A fresh link to hand over by other means (chat, phone) when the email does not arrive. */
    public static function linkToShare(User $user): string
    {
        $link = static::link($user);

        $user->forceFill(['invited_at' => now()])->saveQuietly();
        ActivityLog::record($user, 'link_issued', ['invitation' => [null, 'copied']]);

        return $link;
    }
}
