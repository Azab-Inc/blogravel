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

    public function getApiKeyCount(): int
    {
        return ApiKey::count();
    }

    public function isVisible(): bool
    {
        return $this->getApiKeyCount() === 0;
    }
}
