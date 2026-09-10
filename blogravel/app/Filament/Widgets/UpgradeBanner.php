<?php

namespace App\Filament\Widgets;

use App\Enums\Plan;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class UpgradeBanner extends Widget
{
    protected static string $view = 'filament.widgets.upgrade-banner';

    protected int|string|array $columnSpan = 'full';

    public ?string $currentPlan = null;

    public static function canView(): bool
    {
        if (! config('billing.enabled')) {
            return false;
        }

        $user = Auth::user();

        if (! $user) {
            return false;
        }

        $tenant = $user->tenant;

        if (! $tenant) {
            return false;
        }

        return ($tenant->plan ?? Plan::Free) === Plan::Free;
    }

    public function mount(): void
    {
        $user = Auth::user();
        $tenant = $user?->tenant;

        $this->currentPlan = $tenant->plan?->value ?? Plan::Free->value;
    }
}
