<?php

namespace App\Filament\Resources\BackupRuleResource\Pages;

use App\Filament\Resources\BackupRuleResource;
use App\Support\BackupSchedule;
use Filament\Resources\Pages\CreateRecord;

class CreateBackupRule extends CreateRecord
{
    protected static string $resource = BackupRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = BackupSchedule::prepareFormData($data);
        $data['tenant_id'] = auth()->user()->tenant_id;

        return $data;
    }
}
