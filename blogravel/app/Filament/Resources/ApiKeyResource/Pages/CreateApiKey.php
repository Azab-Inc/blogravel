<?php

namespace App\Filament\Resources\ApiKeyResource\Pages;

use App\Filament\Resources\ApiKeyResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateApiKey extends CreateRecord
{
    protected static string $resource = ApiKeyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['token'] = Str::random(60);
        $data['tenant_id'] = auth()->user()->tenant_id;

        return $data;
    }

    protected function afterCreate(): void
    {
        $token = $this->record->token;

        Notification::make()
            ->title('API Key Created')
            ->body("Copy this token now — it won't be shown again:\n\n{$token}")
            ->success()
            ->persistent()
            ->send();
    }
}
