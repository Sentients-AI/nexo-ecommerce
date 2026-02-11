<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenant\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ResolveTenantFromSubdomain
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if (! $tenant instanceof Tenant) {
            throw new NotFoundHttpException('Tenant not found.');
        }

        if (! $tenant->isActive()) {
            throw new NotFoundHttpException('Tenant is inactive.');
        }

        Context::add('tenant_id', $tenant->id);
        Context::add('tenant', $tenant);

        return $next($request);
    }

    /**
     * Resolve the tenant from the request.
     */
    private function resolveTenant(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $baseDomain = config('tenancy.base_domain');

        // First, try to resolve by custom domain
        $tenant = Tenant::query()
            ->where('domain', $host)
            ->first();

        if ($tenant !== null) {
            return $tenant;
        }

        // Then, try to resolve by subdomain
        $subdomain = $this->extractSubdomain($host, $baseDomain);

        if ($subdomain === null) {
            return null;
        }

        // Check for reserved subdomains
        if (in_array($subdomain, config('tenancy.reserved_subdomains', []), true)) {
            return null;
        }

        return Tenant::query()
            ->where('slug', $subdomain)
            ->first();
    }

    /**
     * Extract the subdomain from the host.
     */
    private function extractSubdomain(string $host, ?string $baseDomain): ?string
    {
        if ($baseDomain === null) {
            return null;
        }

        // Handle case where base domain might include port
        $baseDomain = mb_strtolower($baseDomain);
        $host = mb_strtolower($host);

        // Remove port from host if present
        $hostWithoutPort = explode(':', $host)[0];
        $baseDomainWithoutPort = explode(':', $baseDomain)[0];

        // Check if host ends with base domain
        if (! str_ends_with($hostWithoutPort, $baseDomainWithoutPort)) {
            return null;
        }

        // Extract subdomain
        $subdomain = mb_substr($hostWithoutPort, 0, -mb_strlen($baseDomainWithoutPort));

        // Remove trailing dot
        $subdomain = mb_rtrim($subdomain, '.');

        if ($subdomain === '' || $subdomain === 'www') {
            return null;
        }

        return $subdomain;
    }
}
