<?php

namespace App\Scopes;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Tracks whether the authenticated user is currently being resolved.
     *
     * The session guard resolves the user by running a query against the User
     * model, which applies this global scope. Without this guard, resolving
     * the user would trigger the scope, which resolves the user again, causing
     * infinite recursion and a stack overflow on every authenticated request.
     */
    protected static bool $resolvingUser = false;

    /**
     * Resolve the authenticated user without allowing this scope to trigger
     * another user resolution while one is already in flight.
     */
    protected static function resolveUser(): ?User
    {
        if (static::$resolvingUser) {
            return null;
        }

        try {
            static::$resolvingUser = true;

            return auth()->user();
        } finally {
            static::$resolvingUser = false;
        }
    }

    public function apply(Builder $builder, Model $model): void
    {
        $user = static::resolveUser();

        if ($user === null) {
            return;
        }

        if ($user->role === Role::SuperAdmin) {
            return;
        }

        if ($user->tenant_id === null) {
            return;
        }

        $builder->where('tenant_id', $user->tenant_id)
            ->whereHas('tenant', fn ($q) => $q->withTrashed()->whereNull('deleted_at'));
    }
}
