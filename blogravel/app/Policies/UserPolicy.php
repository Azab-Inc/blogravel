<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function view(User $user, User $model): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function update(User $user, User $model): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function restore(User $user, User $model): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->role === Role::SuperAdmin;
    }
}
