<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'contact@azaber.com'],
            [
                'name' => 'Alex Zab',
                'first_name' => 'Alex',
                'last_name' => 'Zab',
                'password' => Hash::make('password'),
                'role' => Role::SuperAdmin,
            ]
        );
    }
}
