<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantPlanLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWithinPlanLimits
{
    public function __construct(private readonly TenantPlanLimitService $limits) {}

    /**
     * Enforce plan limits for the current tenant.
     * Only active when BILLING_ENABLED=true.
     */
    public function handle(Request $request, Closure $next, string ...$checks): Response
    {
        if (! config('billing.enabled')) {
            return $next($request);
        }

        $tenantId = $this->resolveTenantId($request);

        if (! $tenantId) {
            return $next($request);
        }

        $apiKey = $request->attributes->get('apiKey');
        $tenant = $apiKey?->tenant;

        if (! $tenant) {
            return $next($request);
        }

        foreach ($checks as $check) {
            $this->check($tenant, $check);
        }

        return $next($request);
    }

    private function resolveTenantId(Request $request): ?string
    {
        $apiKey = $request->attributes->get('apiKey');

        if ($apiKey) {
            return $apiKey->tenant_id;
        }

        $user = $request->user();

        if ($user) {
            return $user->tenant_id;
        }

        return null;
    }

    private function check(Tenant $tenant, string $check): void
    {
        $limit = $this->limits->limit($tenant, $check);

        if (! $this->limits->hasReached($tenant, $check)) {
            return; // Unlimited
        }

        abort(403, json_encode([
            'message' => "Plan limit reached for {$check}.",
            'limit' => $limit,
            'current' => $this->limits->current($tenant, $check),
            'plan' => $tenant->plan?->value,
            'upgrade_url' => '/admin/billing',
        ]));
    }
}
