<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

final class FilamentTenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->isSuperAdmin()) {
            // Super admins can select a tenant via session
            $selectedTenantId = session('filament_selected_tenant_id');
            if ($selectedTenantId !== null) {
                Context::add('tenant_id', (int) $selectedTenantId);
            }
            // If no tenant selected, Context remains empty for global view
        } elseif ($user->tenant_id !== null) {
            // Regular users are bound to their tenant
            Context::add('tenant_id', $user->tenant_id);
        }

        return $next($request);
    }
}
