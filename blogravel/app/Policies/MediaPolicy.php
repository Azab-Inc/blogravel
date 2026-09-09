<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin, Role::Editor], true);
    }

    public function view(User $user, Media $media): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin, Role::Editor], true);
    }

    public function update(User $user, Media $media): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin, Role::Editor], true);
    }

    public function delete(User $user, Media $media): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin, Role::Editor], true);
    }

    public function restore(User $user, Media $media): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin, Role::Editor], true);
    }

    public function forceDelete(User $user, Media $media): bool
    {
        return $user->role === Role::SuperAdmin;
    }
}
