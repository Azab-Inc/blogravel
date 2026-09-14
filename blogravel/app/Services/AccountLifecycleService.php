<?php

namespace App\Services;

use App\Enums\DeletionReason;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AccountLifecycleService
{
    public function close(User $user, ?string $tenantConfirmation = null): void
    {
        DB::transaction(function () use ($tenantConfirmation, $user): void {
            $lockedUser = User::withoutGlobalScopes()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $tenant = $lockedUser->tenant_id
                ? Tenant::withTrashed()->whereKey($lockedUser->tenant_id)->lockForUpdate()->first()
                : null;
            $activeAdministrators = $tenant
                ? User::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->getKey())
                    ->whereNull('deleted_at')
                    ->whereIn('role', [Role::Admin->value, Role::SuperAdmin->value])
                    ->lockForUpdate()
                    ->get()
                : collect();
            $isLastAdministrator = $tenant !== null
                && $activeAdministrators->count() === 1
                && $activeAdministrators->first()->is($lockedUser);

            if ($isLastAdministrator && ! $this->matchesTenantConfirmation($tenant, $tenantConfirmation)) {
                throw ValidationException::withMessages([
                    'tenant_confirmation' => 'Type the tenant name or slug to confirm closure.',
                ]);
            }

            $lockedUser->forceFill([
                'deleted_by' => null,
                'deletion_reason' => DeletionReason::SelfClosed,
            ])->saveQuietly();
            $lockedUser->delete();

            if ($isLastAdministrator && ! $tenant->trashed()) {
                $tenant->delete();
            }
        });
    }

    public function remove(User $actor, User $target): void
    {
        DB::transaction(function () use ($actor, $target): void {
            $lockedTarget = User::withoutGlobalScopes()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('delete', $lockedTarget);

            $lockedTarget->forceFill([
                'deleted_by' => $actor->getKey(),
                'deletion_reason' => DeletionReason::AdminRemoved,
            ])->saveQuietly();
            $lockedTarget->delete();
        });
    }

    public function isLastAdministrator(User $user): bool
    {
        if (! in_array($user->role, [Role::Admin, Role::SuperAdmin], true) || $user->tenant_id === null) {
            return false;
        }

        return User::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->whereNull('deleted_at')
            ->whereIn('role', [Role::Admin->value, Role::SuperAdmin->value])
            ->count() === 1;
    }

    private function matchesTenantConfirmation(Tenant $tenant, ?string $tenantConfirmation): bool
    {
        return in_array($tenantConfirmation, [$tenant->name, $tenant->slug], true);
    }
}
