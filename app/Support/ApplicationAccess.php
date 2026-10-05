<?php

namespace App\Support;

use App\Models\Application;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Who may open the client's link of an application, and what stands in the way: it can expire
 * or be revoked, and when the application holds driver data the client must give the PIN first.
 * Signed-in staff are never asked: they work through the panel's own login.
 */
class ApplicationAccess
{
    /** 'ok' | 'expired' | 'revoked' | 'pin' */
    public static function state(Application $application): string
    {
        if (auth()->check()) {
            return 'ok';
        }

        $link = $application->linkStatus();

        if (in_array($link, ['expired', 'revoked'], true)) {
            return $link;
        }

        return $application->needsPin() && ! static::pinPassed($application) ? 'pin' : 'ok';
    }

    /**
     * The same checks for the PDF routes. The live summary also needs a usable link; the copy the
     * client already signed does not (it must stay downloadable after the link expires).
     *
     * @return 'ok'|'pin'|'inactive'
     */
    public static function downloadState(Application $application, bool $live): string
    {
        if (auth()->check()) {
            return 'ok';
        }

        if ($live && ! in_array($application->linkStatus(), ['active', 'completed'], true)) {
            return 'inactive';
        }

        return $application->needsPin() && ! static::pinPassed($application) ? 'pin' : 'ok';
    }

    /** A new token (renewed link) invalidates every PIN already given for the old one. */
    protected static function sessionKey(Application $application): string
    {
        return 'apply_pin.'.$application->id.'.'.substr(hash('sha256', $application->token), 0, 16);
    }

    public static function pinPassed(Application $application): bool
    {
        return (bool) session()->get(static::sessionKey($application), false);
    }

    /** @return 'ok'|'wrong'|'locked' */
    public static function verifyPin(Application $application, string $input): string
    {
        $key = 'apply-pin:'.$application->id.':'.request()->ip();
        $max = config('vantins.pin_attempts');

        if (RateLimiter::tooManyAttempts($key, $max)) {
            return 'locked';
        }

        if (hash_equals((string) $application->link_pin, trim($input))) {
            RateLimiter::clear($key);
            session()->put(static::sessionKey($application), true);

            return 'ok';
        }

        RateLimiter::hit($key, config('vantins.pin_decay_minutes') * 60);

        return RateLimiter::tooManyAttempts($key, $max) ? 'locked' : 'wrong';
    }

    public static function minutesLocked(Application $application): int
    {
        return (int) ceil(RateLimiter::availableIn('apply-pin:'.$application->id.':'.request()->ip()) / 60);
    }
}
