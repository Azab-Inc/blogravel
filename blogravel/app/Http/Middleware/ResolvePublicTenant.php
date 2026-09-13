<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantHostResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolvePublicTenant
{
    public function __construct(private TenantHostResolver $resolver) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $tenant = $this->resolver->resolve($host);

        if ($tenant) {
            $queryTenant = $this->resolveQueryTenant($request);

            if ($queryTenant && ! $queryTenant->is($tenant)) {
                abort(404, 'Tenant not found.');
            }

            $request->attributes->set('tenant', $tenant);

            return $next($request);
        }

        if ($this->isLocalHost($host)) {
            $tenant = $this->resolveQueryTenant($request);

            if ($tenant) {
                $request->attributes->set('tenant', $tenant);

                return $next($request);
            }
        }

        abort(404, 'Tenant not found.');
    }

    private function resolveQueryTenant(Request $request): ?Tenant
    {
        $identifier = $request->query('tenant');

        if (! is_string($identifier) || $identifier === '') {
            return null;
        }

        $tenant = Tenant::where('domain', strtolower($identifier))
            ->orWhere('custom_domain', strtolower($identifier))
            ->first();

        if ($tenant || ! Str::isUuid($identifier)) {
            return $tenant;
        }

        return Tenant::where('id', $identifier)->first();
    }

    private function isLocalHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1', 'lvh.me'], true);
    }
}
