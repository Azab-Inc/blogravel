<?php

namespace App\Filament\Pages;

use App\Enums\NavGroup;
use App\Enums\Plan;
use App\Models\Tenant;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BillingPage extends Page
{
    protected string $view = 'filament.pages.billing';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';

    protected static UnitEnum|string|null $navigationGroup = NavGroup::Administration;

    protected static ?string $title = 'Billing & Plans';

    protected static ?int $navigationSort = 50;

    public ?Tenant $tenant = null;

    public ?string $currentPlan = null;

    public ?int $postCount = null;

    public ?int $userCount = null;

    public ?int $postLimit = null;

    public ?int $userLimit = null;

    public ?int $imageLimit = null;

    public static function canAccess(): bool
    {
        return config('billing.enabled') && Auth::user()->isSuperAdmin();
    }

    public function mount(): void
    {
        $this->tenant = Auth::user()->tenant;

        if (! $this->tenant) {
            return;
        }

        $this->currentPlan = $this->tenant->plan?->value ?? Plan::Free->value;
        $plan = $this->tenant->plan ?? Plan::Free;

        $this->postCount = $this->tenant->posts()->count();
        $this->userCount = $this->tenant->users()->count();
        $this->postLimit = $plan->limit('posts');
        $this->userLimit = $plan->limit('users');
        $this->imageLimit = $plan->limit('max_image_size_mb');
    }

    public function getPlans(): array
    {
        return [
            Plan::Free->value => [
                'label' => 'Free',
                'price' => '$0/mo',
                'posts' => '50 posts',
                'image_size' => '2 MB images',
                'users' => '3 users',
            ],
            Plan::Pro->value => [
                'label' => 'Pro',
                'price' => '$19/mo',
                'posts' => 'Unlimited posts',
                'image_size' => '10 MB images',
                'users' => '10 users',
            ],
            Plan::Business->value => [
                'label' => 'Business',
                'price' => '$49/mo',
                'posts' => 'Unlimited posts',
                'image_size' => '25 MB images',
                'users' => 'Unlimited users',
            ],
        ];
    }
}
