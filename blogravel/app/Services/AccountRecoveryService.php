<?php

namespace App\Services;

use App\Enums\AccountRecoveryResult;
use App\Enums\DeletionReason;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AccountRecoveredNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountRecoveryService
{
    public function recover(string $email, string $password): AccountRecoveryResult
    {
        $normalizedEmail = Str::lower(trim($email));
        $candidates = User::withoutGlobalScopes()
            ->withTrashed()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->whereNotNull('deleted_at')
            ->orderByDesc('deleted_at')
            ->get();

        $candidate = $candidates->first(
            fn (User $user): bool => Hash::check($password, $user->password)
        );

        if ($candidate === null) {
            return AccountRecoveryResult::InvalidCredentials;
        }

        $result = DB::transaction(function () use ($candidate, $normalizedEmail, $password): array {
            $user = User::withoutGlobalScopes()
                ->withTrashed()
                ->lockForUpdate()
                ->find($candidate->getKey());

            if ($user === null || ! Hash::check($password, $user->password)) {
                return [AccountRecoveryResult::InvalidCredentials, null];
            }

            if ($user->deletion_reason === DeletionReason::AdminRemoved) {
                return [AccountRecoveryResult::AdminRemovalBlocked, null];
            }

            if ($user->deletion_reason === DeletionReason::TenantClosed) {
                return [AccountRecoveryResult::TenantClosureBlocked, null];
            }

            $recoveryDeadline = now()->subDays(30);
            if ($user->deleted_at?->isBefore($recoveryDeadline)) {
                return [AccountRecoveryResult::Expired, null];
            }

            if (User::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->exists()) {
                return [AccountRecoveryResult::ActiveEmailConflict, null];
            }

            $tenant = $user->tenant_id
                ? Tenant::withTrashed()->lockForUpdate()->find($user->tenant_id)
                : null;

            if ($user->tenant_id !== null && $tenant === null) {
                if ($user->role === Role::Admin) {
                    $user->forceFill(['tenant_id' => null])->saveQuietly();
                    $user->restore();
                    session()->put('recovery.needs_tenant_setup', $user->getKey());

                    return [AccountRecoveryResult::RestoredUserNeedsTenant, $user];
                }

                return [AccountRecoveryResult::TenantClosureBlocked, null];
            }

            if ($tenant?->trashed()) {
                if ($user->role !== Role::Admin) {
                    return [AccountRecoveryResult::TenantClosureBlocked, null];
                }

                if ($tenant->deleted_at?->isBefore($recoveryDeadline)) {
                    $user->forceFill(['tenant_id' => null])->saveQuietly();
                    $user->restore();
                    session()->put('recovery.needs_tenant_setup', $user->getKey());

                    return [AccountRecoveryResult::RestoredUserNeedsTenant, $user];
                }

                $tenant->restore();
                $user->restore();

                return [AccountRecoveryResult::RestoredUserAndTenant, $user];
            }

            $user->restore();

            return [AccountRecoveryResult::RestoredUser, $user];
        });

        [$recoveryResult, $restoredUser] = $result;

        if ($restoredUser instanceof User) {
            $restoredUser->notify(new AccountRecoveredNotification);
        }

        return $recoveryResult;
    }
}
