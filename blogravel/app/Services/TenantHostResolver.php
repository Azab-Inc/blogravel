<?php

namespace App\Services;

use App\Models\Tenant;

class TenantHostResolver
{
    public function resolve(string $host): ?Tenant
    {
        $host = $this->normalizeHost($host);

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

        return $this->resolveSlug($label);
    }

    public function resolveSlug(?string $slug): ?Tenant
    {
        if (! is_string($slug)) {
            return null;
        }

        $slug = strtolower(trim($slug));
        $reservedLabels = array_map(
            static fn (mixed $reservedLabel): string => strtolower(trim((string) $reservedLabel)),
            config('tenancy.reserved_labels', []),
        );

        if ($slug === '' || str_contains($slug, '.') || in_array($slug, $reservedLabels, true)) {
            return null;
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug)) {
            return null;
        }

        return Tenant::where('slug', $slug)->first();
    }

    public function isLocalHost(string $host): bool
    {
        $host = $this->normalizeHost($host);

        return in_array($host, ['localhost', '127.0.0.1', '::1', 'lvh.me'], true)
            || str_ends_with($host, '.localhost');
    }

    public function resolveLocalSubdomain(string $host): ?Tenant
    {
        $host = $this->normalizeHost($host);
        if (! str_ends_with($host, '.localhost')) {
            return null;
        }

        $slug = substr($host, 0, -strlen('.localhost'));

        return $this->resolveSlug($slug);
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));

        return str_contains($host, ':') ? explode(':', $host, 2)[0] : $host;
    }
}
