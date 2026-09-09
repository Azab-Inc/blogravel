<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin, Role::Editor], true);
    }

    public function view(User $user, User $model): bool
    {
        return match ($user->role) {
            Role::SuperAdmin => true,
            Role::Admin => $this->sameTenant($user, $model),
            Role::Editor => $this->sameTenant($user, $model) && $model->role === Role::Author,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin], true);
    }

    public function update(User $user, User $model): bool
    {
        return match ($user->role) {
            Role::SuperAdmin => true,
            Role::Admin => $this->sameTenant($user, $model),
            default => false,
        };
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        return match ($user->role) {
            Role::SuperAdmin => true,
            Role::Admin => $this->sameTenant($user, $model) && $model->role !== Role::SuperAdmin,
            default => false,
        };
    }

    public function restore(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }

    private function sameTenant(User $user, User $model): bool
    {
        return $user->tenant_id !== null && $user->tenant_id === $model->tenant_id;
    }
}
