<?php

namespace App\Http\Middleware;

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

            return $next($request);
        }

        if ($this->isLocalHost($host)) {
            return $next($request);
        }

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

    private function isLocalHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1', 'lvh.me'], true);
    }

    private function hasTenantQueryOnPublicRoute(Request $request): bool
    {
        return $request->query('tenant') !== null
            && $request->is('post/*', 'category/*', 'subscribe', 'contact');
    }
}
