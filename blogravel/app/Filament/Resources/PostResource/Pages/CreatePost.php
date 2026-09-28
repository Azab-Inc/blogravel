<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Enums\PostStatus;
use App\Filament\Resources\PostResource;
use App\Services\TenantPlanLimitService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $data['slug'] = Str::slug($data['title']);
        $data['tenant_id'] = $user->tenant_id;
        $data['author_id'] = $user->id;

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return ($this->data['status'] ?? null) === PostStatus::Published->value
            ? 'Post published'
            : 'Post created';
    }

    protected function beforeCreate(): void
    {
        $tenant = auth()->user()->tenant;
        $limits = app(TenantPlanLimitService::class);

        if (! $limits->hasReached($tenant, 'posts')) {
            return;
        }

        Notification::make()
            ->title('Upgrade required')
            ->body('Your plan has reached its post limit.')
            ->danger()
            ->actions([
                Action::make('upgrade')
                    ->label('Upgrade plan')
                    ->url('/admin/billing')
                    ->button(),
            ])
            ->persistent()
            ->send();

        $this->halt();
    }
}
