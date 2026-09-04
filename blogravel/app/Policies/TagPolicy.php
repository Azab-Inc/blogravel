<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tag $tag): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function restore(User $user, Tag $tag): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function forceDelete(User $user, Tag $tag): bool
    {
        return $user->role === Role::SuperAdmin;
    }
}
