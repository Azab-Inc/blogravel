<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Actions\AuthorizeSuperAdminAssignment;
use App\Enums\Role;
use App\Filament\Resources\UserResource;
use App\Services\TenantPlanLimitService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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

    protected function beforeCreate(): void
    {
        $tenant = auth()->user()->tenant;
        $limits = app(TenantPlanLimitService::class);

        if (! $limits->hasReached($tenant, 'users')) {
            return;
        }

        Notification::make()
            ->title('Upgrade required')
            ->body('Your plan has reached its user limit.')
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
