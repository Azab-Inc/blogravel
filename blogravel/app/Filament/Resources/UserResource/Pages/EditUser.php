<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Actions\AuthorizeSuperAdminAssignment;
use App\Enums\Role;
use App\Filament\Resources\UserResource;
use App\Services\AccountLifecycleService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->getRecord()->role !== Role::SuperAdmin && ($data['role'] ?? null) === Role::SuperAdmin->value) {
            app(AuthorizeSuperAdminAssignment::class)->handle();
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $data['name'] = trim($data['first_name'].' '.$data['last_name']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->modalDescription('Removing this user blocks self-recovery. They may only create a new account with the same email.')
                ->action(function (AccountLifecycleService $lifecycle): void {
                    $lifecycle->remove(auth()->user(), $this->getRecord());
                }),
        ];
    }
}
