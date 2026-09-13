<?php

namespace App\Http\Middleware;

use App\Services\TenantHostResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseLocalSessionCookies
{
    public function __construct(private TenantHostResolver $resolver) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->resolver->isLocalHost($request->getHost())) {
            return $response;
        }

        $configuredDomain = config('session.domain');
        if (! is_string($configuredDomain) || $configuredDomain === '') {
            return $response;
        }

        $cookies = $response->headers->getCookies();
        $response->headers->remove('Set-Cookie');

        foreach ($cookies as $cookie) {
            if (in_array($cookie->getName(), [config('session.cookie'), 'XSRF-TOKEN'], true)
                && $cookie->getDomain() === $configuredDomain) {
                $cookie = $cookie->withDomain(null);
            }

            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
