<?php

namespace App\Filament\Widgets;

use App\Models\ApiKey;
use Filament\Widgets\Widget;

class OpenModeWarning extends Widget
{
    protected string $view = 'filament.widgets.open-mode-warning';

    public ?int $apiKeyCount = null;

    public function getApiKeyCount(): int
    {
        return $this->apiKeyCount ??= ApiKey::count();
    }

    public function isVisible(): bool
    {
        return $this->getApiKeyCount() === 0;
    }
}
