<?php

namespace App\Services;

use App\Models\Tenant;

class TenantHostResolver
{
    public function resolve(string $host): ?Tenant
    {
        $host = strtolower(trim($host));
        $host = str_contains($host, ':') ? explode(':', $host, 2)[0] : $host;

        $tenant = Tenant::whereRaw('LOWER(custom_domain) = ?', [$host])->first();
        if ($tenant) {
            return $tenant;
        }

        $platformDomain = strtolower(trim((string) config('tenancy.platform_domain')));
        $suffix = '.'.$platformDomain;

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $label = substr($host, 0, -strlen($suffix));
        $reservedLabels = array_map(
            static fn (mixed $reservedLabel): string => strtolower(trim((string) $reservedLabel)),
            config('tenancy.reserved_labels', []),
        );

        if ($label === '' || str_contains($label, '.') || in_array($label, $reservedLabels, true)) {
            return null;
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label)) {
            return null;
        }

        return Tenant::where('slug', $label)->first();
    }
}
