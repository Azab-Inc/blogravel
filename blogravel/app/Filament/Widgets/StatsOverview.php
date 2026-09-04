<?php

namespace App\Filament\Widgets;

use App\Enums\CommentStatus;
use App\Enums\SubscriberStatus;
use App\Models\Comment;
use App\Models\Subscriber;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $tenant = auth()->user()->tenant;

        return [
            Stat::make('Posts', $tenant->posts()->count()),
            Stat::make('Pages', $tenant->pages()->count()),
            Stat::make('Pending Comments', Comment::where('tenant_id', $tenant->id)->where('status', CommentStatus::Pending)->count()),
            Stat::make('Subscribers', Subscriber::where('tenant_id', $tenant->id)->where('status', SubscriberStatus::Subscribed)->count()),
        ];
    }
}
