<?php

namespace App\Http\Middleware;

use App\Enums\Plan;
use App\Models\Post;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWithinPlanLimits
{
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

        $plan = $tenant->plan ?? Plan::Free;

        foreach ($checks as $check) {
            $this->check($plan, $tenantId, $check);
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

    private function check(Plan $plan, string $tenantId, string $check): void
    {
        $limit = $plan->limit($check);

        if ($limit === null) {
            return; // Unlimited
        }

        $current = match ($check) {
            'posts' => Post::where('tenant_id', $tenantId)->count(),
            'users' => User::where('tenant_id', $tenantId)->count(),
            default => 0,
        };

        if ($current >= $limit) {
            abort(403, json_encode([
                'message' => "Plan limit reached for {$check}.",
                'limit' => $limit,
                'current' => $current,
                'plan' => $plan->value,
                'upgrade_url' => '/admin/billing',
            ]));
        }
    }
}
