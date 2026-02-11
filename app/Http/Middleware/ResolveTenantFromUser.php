<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class ResolveTenantFromUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if tenant is already resolved (e.g., from subdomain)
        if (Context::has('tenant_id')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Super admins (tenant_id = null) don't have tenant context
        if ($user->tenant_id === null) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if ($tenant === null) {
            throw new AccessDeniedHttpException('User has no valid tenant.');
        }

        if (! $tenant->isActive()) {
            throw new AccessDeniedHttpException('Tenant is inactive.');
        }

        Context::add('tenant_id', $tenant->id);
        Context::add('tenant', $tenant);

        return $next($request);
    }
}
