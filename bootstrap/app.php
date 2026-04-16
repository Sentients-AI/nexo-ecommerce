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
use Inertia\Inertia;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhooks/stripe',
            '_boost/*',
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            $locale = $request->route('locale', app()->getLocale());

            return route('login', ['locale' => $locale]);
        });

        $middleware->redirectUsersTo('/en');

        $middleware->alias([
            'tenant.subdomain' => ResolveTenantFromSubdomain::class,
            'tenant.user' => ResolveTenantFromUser::class,
            'locale' => SetLocaleFromUrl::class,
            'super_admin' => App\Http\Middleware\EnsureSuperAdmin::class,
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
        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            if (! $request->inertia()) {
                return $response;
            }

            $status = $response->getStatusCode();

            $handled = [400, 401, 403, 404, 405, 419, 429, 500, 503];

            if (! in_array($status, $handled, true)) {
                return $response;
            }

            return Inertia::render('Error', [
                'statusCode' => $status,
                'message' => $e->getMessage(),
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
