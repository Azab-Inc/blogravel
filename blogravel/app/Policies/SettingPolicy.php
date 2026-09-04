<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function view(User $user, Setting $setting): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function update(User $user, Setting $setting): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function delete(User $user, Setting $setting): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function restore(User $user, Setting $setting): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function forceDelete(User $user, Setting $setting): bool
    {
        return $user->role === Role::SuperAdmin;
    }
}
