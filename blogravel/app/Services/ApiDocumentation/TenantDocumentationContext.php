<?php

namespace App\Services\ApiDocumentation;

use App\Models\Tenant;
use InvalidArgumentException;

final readonly class TenantDocumentationContext
{
    private function __construct(
        private Tenant $tenant,
        public string $slug,
        private string $platformDomain,
    ) {}

    public static function fromSlug(string $slug): self
    {
        $normalizedSlug = strtolower(trim($slug));

        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $normalizedSlug) !== 1) {
            throw new InvalidArgumentException('The tenant slug is invalid.');
        }

        $reservedLabels = array_map(
            static fn (mixed $label): string => strtolower(trim((string) $label)),
            config('tenancy.reserved_labels', []),
        );

        if (in_array($normalizedSlug, $reservedLabels, true)) {
            throw new InvalidArgumentException('The tenant slug is reserved.');
        }

        return self::fromTenant(new Tenant(['slug' => $normalizedSlug]));
    }

    public static function fromTenant(Tenant $tenant): self
    {
        if (blank($tenant->slug)) {
            throw new InvalidArgumentException('The tenant has no valid slug.');
        }

        return new self(
            $tenant,
            (string) $tenant->slug,
            (string) config('tenancy.platform_domain', 'blogravel.com'),
        );
    }

    public function host(): string
    {
        return (string) ($this->tenant->custom_domain
            ?: $this->tenant->domain
            ?: $this->slug.'.'.$this->platformDomain);
    }

    public function url(string $path): string
    {
        return 'https://'.$this->host().'/'.ltrim($path, '/');
    }
}
