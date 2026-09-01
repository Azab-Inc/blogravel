<?php

namespace App\Observers;

use App\Enums\Plan;
use App\Models\Tenant;
use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        if ($user->tenant_id || app()->runningUnitTests()) {
            return;
        }

        $tenant = Tenant::create([
            'name' => $user->name,
            'domain' => $user->email,
            'plan' => Plan::Free,
        ]);

        $user->update(['tenant_id' => $tenant->id]);
    }
}
