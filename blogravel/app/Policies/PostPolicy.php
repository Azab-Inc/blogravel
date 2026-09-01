<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Post $post): bool
    {
        if ($user->role === Role::SuperAdmin || $user->role === Role::Editor) {
            return true;
        }

        return $user->role === Role::Author && $post->author_id === $user->id;
    }

    public function delete(User $user, Post $post): bool
    {
        if ($user->role === Role::SuperAdmin) {
            return true;
        }

        if ($user->role === Role::Editor && $post->author_id === $user->id) {
            return true;
        }

        return $user->role === Role::Author && $post->author_id === $user->id;
    }

    public function restore(User $user, Post $post): bool
    {
        return $this->delete($user, $post);
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->role === Role::SuperAdmin;
    }
}
