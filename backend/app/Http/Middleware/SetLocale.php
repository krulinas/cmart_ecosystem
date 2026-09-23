<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    private const SUPPORTED_LOCALES = ['ms', 'en'];

    public function handle(Request $request, Closure $next)
    {
        $requestedLocale = strtolower(substr((string) $request->header('Accept-Language', ''), 0, 2));
        $locale = in_array($requestedLocale, self::SUPPORTED_LOCALES, true)
            ? $requestedLocale
            : config('app.locale');

        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
