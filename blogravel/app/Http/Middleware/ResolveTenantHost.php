<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantHostResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteDefinition;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantHost
{
    public function __construct(private TenantHostResolver $resolver) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*') || $request->is('up')) {
            return $next($request);
        }

        $host = strtolower($request->getHost());
        $platformDomain = strtolower(trim((string) config('tenancy.platform_domain')));

        if ($host === $platformDomain) {
            if ($this->hasTenantQueryOnPublicRoute($request)) {
                abort(404, 'Tenant host not found.');
            }

            $this->rejectTenantPath($request);

            return $next($request);
        }

        if ($this->resolver->isLocalHost($host)) {
            $tenant = $this->resolver->resolveLocalSubdomain($host)
                ?? $this->resolveLocalPathTenant($request);

            if ($tenant) {
                $request->attributes->set('tenant', $tenant);
                $request->attributes->set('tenant_path_slug', $this->tenantPathSlug($request));
                $this->forgetTenantPathParameter($request);
            } elseif ($this->hasLocalSubdomain($host) || $this->hasTenantPath($request)) {
                abort(404, 'Tenant host not found.');
            }

            return $next($request);
        }

        $this->rejectTenantPath($request);

        $tenant = $this->resolver->resolve($host);
        if ($tenant) {
            $request->attributes->set('tenant', $tenant);

            $route = $request->route();
            if ($route instanceof RouteDefinition && $route->parameter('tenant') !== null) {
                $route->setParameter('tenant', $tenant);
            }

            return $next($request);
        }

        abort(404, 'Tenant host not found.');
    }

    private function resolveLocalPathTenant(Request $request): ?Tenant
    {
        $slug = $this->tenantPathSlug($request);

        return is_string($slug) ? $this->resolver->resolveSlug($slug) : null;
    }

    private function hasLocalSubdomain(string $host): bool
    {
        return str_ends_with($host, '.localhost');
    }

    private function hasTenantPath(Request $request): bool
    {
        return $this->tenantPathSlug($request) !== null;
    }

    private function tenantPathSlug(Request $request): mixed
    {
        $route = $request->route();

        return $route instanceof RouteDefinition ? $route->parameter('tenantSlug') : null;
    }

    private function forgetTenantPathParameter(Request $request): void
    {
        $route = $request->route();
        if ($route instanceof RouteDefinition) {
            $route->forgetParameter('tenantSlug');
        }
    }

    private function rejectTenantPath(Request $request): void
    {
        if ($this->hasTenantPath($request)) {
            abort(404, 'Tenant host not found.');
        }
    }

    private function hasTenantQueryOnPublicRoute(Request $request): bool
    {
        return $request->query('tenant') !== null
            && $request->is('post/*', 'category/*', 'subscribe', 'contact');
    }
}
