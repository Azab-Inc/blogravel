<?php

namespace App\Filament\Widgets;

use App\Models\ApiKey;
use Filament\Widgets\Widget;

class OpenModeWarning extends Widget
{
    protected static ?int $sort = 4;

    protected string $view = 'filament.widgets.open-mode-warning';

    public static function getSort(): int
    {
        return 100;
    }

    public static function canView(): bool
    {
        return static::getApiKeyCount() === 0;
    }

    protected static function getApiKeyCount(): int
    {
        $user = auth()->user();

        if (! $user || ! $user->tenant_id) {
            return 0;
        }

        return ApiKey::where('tenant_id', $user->tenant_id)->count();
    }
}
