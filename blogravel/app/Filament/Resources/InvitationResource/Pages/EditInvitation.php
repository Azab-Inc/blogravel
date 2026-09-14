<?php

namespace App\Filament\Resources\InvitationResource\Pages;

use App\Actions\AuthorizeSuperAdminAssignment;
use App\Enums\Role;
use App\Filament\Resources\InvitationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvitation extends EditRecord
{
    protected static string $resource = InvitationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['role'] ?? null) === Role::SuperAdmin->value) {
            app(AuthorizeSuperAdminAssignment::class)->handle();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
