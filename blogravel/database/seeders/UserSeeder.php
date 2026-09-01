<?php

namespace Database\Seeders;

use App\Enums\Plan;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::updateOrCreate(
            ['domain' => 'azaber.com'],
            [
                'name' => 'Alex Zab',
                'plan' => Plan::Free,
            ]
        );

        User::updateOrCreate(
            ['email' => 'contact@azaber.com'],
            [
                'name' => 'Alex Zab',
                'first_name' => 'Alex',
                'last_name' => 'Zab',
                'password' => Hash::make('password'),
                'role' => Role::SuperAdmin,
                'tenant_id' => $tenant->id,
            ]
        );
    }
}
