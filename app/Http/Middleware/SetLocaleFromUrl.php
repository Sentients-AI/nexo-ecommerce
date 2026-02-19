<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocaleFromUrl
{
    /** @var array<string> */
    private const SUPPORTED_LOCALES = ['en', 'ar', 'ms'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale', 'en');

        if (in_array($locale, self::SUPPORTED_LOCALES, true)) {
            app()->setLocale($locale);
        } else {
            app()->setLocale('en');
        }

        return $next($request);
    }
}
