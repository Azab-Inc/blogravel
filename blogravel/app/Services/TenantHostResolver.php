<?php

namespace App\Services;

use App\Models\Tenant;

class TenantHostResolver
{
    public function resolve(string $host): ?Tenant
    {
        $host = strtolower(trim($host));
        $host = str_contains($host, ':') ? explode(':', $host, 2)[0] : $host;

        $platformDomain = strtolower(trim((string) config('tenancy.platform_domain')));
        $suffix = '.'.$platformDomain;

        if ($host === $platformDomain) {
            return null;
        }

        $label = str_ends_with($host, $suffix)
            ? substr($host, 0, -strlen($suffix))
            : null;
        $reservedLabels = array_map(
            static fn (mixed $reservedLabel): string => strtolower(trim((string) $reservedLabel)),
            config('tenancy.reserved_labels', []),
        );

        if ($label !== null && in_array($label, $reservedLabels, true)) {
            return null;
        }

        $tenant = Tenant::whereRaw('LOWER(custom_domain) = ?', [$host])->first();
        if ($tenant) {
            return $tenant;
        }

        $tenant = Tenant::whereRaw('LOWER(domain) = ?', [$host])->first();
        if ($tenant) {
            return $tenant;
        }

        if ($label === null) {
            return null;
        }

        if ($label === '' || str_contains($label, '.') || in_array($label, $reservedLabels, true)) {
            return null;
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label)) {
            return null;
        }

        return Tenant::where('slug', $label)->first();
    }
}
