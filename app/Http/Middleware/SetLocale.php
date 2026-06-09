<?php

namespace App\Http\Middleware;

use App\Support\Translations;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Set the application locale from the Accept-Language header (limited to
     * supported locales), so API messages match the client's chosen language.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasHeader('Accept-Language')) {
            $locale = $request->getPreferredLanguage(Translations::locales());

            if ($locale !== null) {
                app()->setLocale($locale);
            }
        }

        return $next($request);
    }
}
