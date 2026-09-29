<?php

namespace App\Filament\Pages;

use App\Enums\NavGroup;
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

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    public function getSelectedTenantHost(): ?string
    {
        $tenant = Auth::user()?->tenant;

        if ($tenant === null) {
            return null;
        }

        return TenantDocumentationContext::fromTenant($tenant)->host();
    }

    public function getDocuments(): Collection
    {
        $tenant = Auth::user()?->tenant;

        if ($tenant === null) {
            return collect();
        }

        return app(ApiDocumentationRenderer::class)->render(
            TenantDocumentationContext::fromTenant($tenant),
        );
    }
}
