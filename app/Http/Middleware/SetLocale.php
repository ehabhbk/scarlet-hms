<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('scarlet.supported_locales', ['ar', 'en']);

        $candidate = $request->header('X-Locale')
            ?: $request->getPreferredLanguage($supported)
            ?: $request->user()?->locale
            ?: config('scarlet.default_locale', 'ar');

        $locale = substr((string) $candidate, 0, 2);

        if (in_array($locale, $supported, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
