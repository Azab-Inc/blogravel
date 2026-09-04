<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Page $page): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function update(User $user, Page $page): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function restore(User $user, Page $page): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return $user->role === Role::SuperAdmin;
    }
}
