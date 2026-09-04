<?php

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Models\Post;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestPosts extends TableWidget
{
    protected static ?int $sort = 1;

    protected string $view = 'filament-widgets::table-widget';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest Posts')
            ->query(
                Post::where('tenant_id', auth()->user()->tenant_id)
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->url(fn (Post $record): string => route('filament.admin.resources.posts.edit', $record)),
                TextColumn::make('author.name')
                    ->label('Author'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PostStatus $state): string => match ($state) {
                        PostStatus::Draft => 'gray',
                        PostStatus::Published => 'success',
                        PostStatus::Scheduled => 'info',
                    }),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->date(),
            ]);
    }
}
