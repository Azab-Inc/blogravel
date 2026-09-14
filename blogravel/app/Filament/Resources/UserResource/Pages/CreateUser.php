<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Actions\AuthorizeSuperAdminAssignment;
use App\Enums\Role;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['role'] ?? null) === Role::SuperAdmin->value) {
            app(AuthorizeSuperAdminAssignment::class)->handle();
        }

        $data['name'] = trim($data['first_name'].' '.$data['last_name']);
        $data['tenant_id'] = auth()->user()->tenant_id;

        return $data;
    }
}
