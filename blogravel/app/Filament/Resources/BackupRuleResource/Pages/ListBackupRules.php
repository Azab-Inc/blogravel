<?php

namespace App\Filament\Resources\BackupRuleResource\Pages;

use App\Filament\Resources\BackupRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBackupRules extends ListRecords
{
    protected static string $resource = BackupRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
