<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AuthorizeSuperAdminAssignment
{
    public function handle(): void
    {
        Gate::authorize('assignSuperAdmin', User::class);
    }
}
