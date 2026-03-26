<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenantFromSubdomain;
use App\Http\Middleware\ResolveTenantFromUser;
use App\Http\Middleware\SetLocaleFromUrl;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request): string {
            $locale = $request->route('locale', app()->getLocale());

            return route('login', ['locale' => $locale]);
        });

        $middleware->alias([
            'tenant.subdomain' => ResolveTenantFromSubdomain::class,
            'tenant.user' => ResolveTenantFromUser::class,
            'locale' => SetLocaleFromUrl::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->api(append: [
            ResolveTenantFromUser::class,
        ]);
    })
    ->withEvents(discover: [
        __DIR__.'/../app/Domain/*/Listeners',
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
