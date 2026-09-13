<?php

namespace App\Http\Middleware;

use App\Services\TenantHostResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantHost
{
    public function __construct(private TenantHostResolver $resolver) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $platformDomain = strtolower(trim((string) config('tenancy.platform_domain')));

        if ($host === $platformDomain) {
            return $next($request);
        }

        $tenant = $this->resolver->resolve($host);
        if (! $tenant) {
            abort(404, 'Tenant host not found.');
        }

        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
