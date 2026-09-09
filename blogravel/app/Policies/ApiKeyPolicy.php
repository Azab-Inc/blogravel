<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ApiKey;
use App\Models\User;

class ApiKeyPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin], true);
    }

    public function view(User $user, ApiKey $apiKey): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin], true);
    }

    public function update(User $user, ApiKey $apiKey): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin], true);
    }

    public function delete(User $user, ApiKey $apiKey): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin], true);
    }

    public function restore(User $user, ApiKey $apiKey): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin], true);
    }

    public function forceDelete(User $user, ApiKey $apiKey): bool
    {
        return $user->role === Role::SuperAdmin;
    }
}
