<?php

namespace App\Services;

use App\Enums\Plan;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;

class TenantPlanLimitService
{
    public function limit(Tenant $tenant, string $check): ?int
    {
        return ($tenant->plan ?? Plan::Free)->limit($check);
    }

    public function current(Tenant $tenant, string $check): int
    {
        return match ($check) {
            'posts' => Post::where('tenant_id', $tenant->id)->count(),
            'users' => User::where('tenant_id', $tenant->id)->count(),
            default => 0,
        };
    }

    public function hasReached(Tenant $tenant, string $check): bool
    {
        $limit = $this->limit($tenant, $check);

        return $limit !== null && $this->current($tenant, $check) >= $limit;
    }
}
