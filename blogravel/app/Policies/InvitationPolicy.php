<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin], true);
    }

    public function view(User $user, Invitation $invitation): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin], true);
    }

    public function update(User $user, Invitation $invitation): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin], true);
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin], true);
    }

    public function restore(User $user, Invitation $invitation): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin], true);
    }

    public function forceDelete(User $user, Invitation $invitation): bool
    {
        return $user->role === Role::SuperAdmin;
    }
}
