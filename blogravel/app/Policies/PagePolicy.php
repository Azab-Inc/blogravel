<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Admin || $user->role === Role::Editor;
    }

    public function view(User $user, Page $page): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin, Role::Editor], true);
    }

    public function update(User $user, Page $page): bool
    {
        return match ($user->role) {
            Role::SuperAdmin => true,
            Role::Admin, Role::Editor => true,
            default => false,
        };
    }

    public function delete(User $user, Page $page): bool
    {
        return match ($user->role) {
            Role::SuperAdmin => true,
            Role::Admin, Role::Editor => true,
            default => false,
        };
    }

    public function restore(User $user, Page $page): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin, Role::Editor], true);
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return $user->role === Role::SuperAdmin;
    }
}
