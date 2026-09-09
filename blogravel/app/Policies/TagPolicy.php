<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Admin || $user->role === Role::Editor;
    }

    public function view(User $user, Tag $tag): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin, Role::Editor], true);
    }

    public function update(User $user, Tag $tag): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin, Role::Editor], true);
    }

    public function delete(User $user, Tag $tag): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin, Role::Editor], true);
    }

    public function restore(User $user, Tag $tag): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin, Role::Editor], true);
    }

    public function forceDelete(User $user, Tag $tag): bool
    {
        return $user->role === Role::SuperAdmin;
    }
}
