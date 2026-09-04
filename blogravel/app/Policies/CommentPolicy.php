<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function view(User $user, Comment $comment): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function update(User $user, Comment $comment): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function restore(User $user, Comment $comment): bool
    {
        return $user->role === Role::SuperAdmin || $user->role === Role::Editor;
    }

    public function forceDelete(User $user, Comment $comment): bool
    {
        return $user->role === Role::SuperAdmin;
    }
}
