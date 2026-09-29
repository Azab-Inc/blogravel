<?php

namespace App\Filament\Pages;

use App\Enums\NavGroup;
use App\Models\Tenant;
use App\Services\ApiDocumentation\ApiDocumentationRenderer;
use App\Services\ApiDocumentation\TenantDocumentationContext;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ApiDocumentation extends Page
{
    protected string $view = 'filament.pages.api-documentation';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-code-bracket';

    protected static UnitEnum|string|null $navigationGroup = NavGroup::Administration;

    protected static ?string $title = 'API Documentation';

    protected static ?int $navigationSort = 45;

    public ?string $selectedTenantSlug = null;

    public function mount(): void
    {
        $this->selectedTenantSlug = Auth::user()?->tenant?->slug;
    }

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, string>
     */
    public function getTenantOptions(): array
    {
        if (! Auth::user()?->isSuperAdmin()) {
            return [];
        }

        return app(ApiDocumentationRenderer::class)->tenantOptions();
    }

    public function getSelectedTenantHost(): ?string
    {
        $tenant = $this->selectedTenant();

        if ($tenant === null) {
            return null;
        }

        return TenantDocumentationContext::fromTenant($tenant)->host();
    }

    public function getDocuments(): Collection
    {
        $tenant = $this->selectedTenant();

        if ($tenant === null) {
            return collect();
        }

        return app(ApiDocumentationRenderer::class)->render(
            TenantDocumentationContext::fromTenant($tenant),
        );
    }

    private function selectedTenant(): ?Tenant
    {
        $user = Auth::user();

        if (! $user || blank($this->selectedTenantSlug)) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            return Tenant::query()->where('slug', $this->selectedTenantSlug)->first();
        }

        return $user->tenant?->slug === $this->selectedTenantSlug
            ? $user->tenant
            : null;
    }
}
