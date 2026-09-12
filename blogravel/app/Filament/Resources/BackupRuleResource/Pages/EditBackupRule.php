<?php

namespace App\Filament\Resources\BackupRuleResource\Pages;

use App\Filament\Resources\BackupRuleResource;
use App\Support\BackupSchedule;
use Filament\Resources\Pages\EditRecord;

class EditBackupRule extends EditRecord
{
    protected static string $resource = BackupRuleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, BackupSchedule::formDataFor($data['schedule']));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return BackupSchedule::prepareFormData($data);
    }
}
