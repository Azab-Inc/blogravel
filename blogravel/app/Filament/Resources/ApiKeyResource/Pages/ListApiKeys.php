<?php

namespace App\Filament\Resources\ApiKeyResource\Pages;

use App\Filament\Resources\ApiKeyResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ListApiKeys extends ListRecords
{
    protected static string $resource = ApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(function (array $data): array {
                    $plaintext = Str::random(60);
                    $data['token'] = $plaintext;
                    $data['key_hash'] = hash('sha256', $plaintext);
                    $data['tenant_id'] = auth()->user()->tenant_id;

                    return $data;
                })
                ->after(function (Model $record): void {
                    Notification::make()
                        ->title('API Key Created')
                        ->body("Copy this token now — it won't be shown again:\n\n{$record->token}")
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
