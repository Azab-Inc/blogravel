<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function update(User $user, Category $category): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function forceDelete(User $user, Category $category): bool
    {
        return $user->role === Role::SuperAdmin;
    }
}
