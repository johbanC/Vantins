<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPanelLocale
{
    /** Apply the signed-in staff member's preferred UI language to the panel. */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if (in_array($locale, ['en', 'es'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
