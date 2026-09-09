<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin, Role::Editor, Role::Author], true);
    }

    public function view(User $user, Post $post): bool
    {
        return match ($user->role) {
            Role::SuperAdmin, Role::Admin, Role::Editor => true,
            Role::Author => $post->author_id === $user->id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin, Role::Editor], true);
    }

    public function update(User $user, Post $post): bool
    {
        return match ($user->role) {
            Role::SuperAdmin, Role::Admin, Role::Editor => true,
            Role::Author => $post->author_id === $user->id,
            default => false,
        };
    }

    public function delete(User $user, Post $post): bool
    {
        return match ($user->role) {
            Role::SuperAdmin => true,
            Role::Admin, Role::Editor => true,
            Role::Author => $post->author_id === $user->id,
            default => false,
        };
    }

    public function restore(User $user, Post $post): bool
    {
        return match ($user->role) {
            Role::SuperAdmin => true,
            Role::Admin, Role::Editor => true,
            default => false,
        };
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    private function sameTenant(User $user, Post $post): bool
    {
        return $user->tenant_id !== null && $user->tenant_id === $post->tenant_id;
    }
}
