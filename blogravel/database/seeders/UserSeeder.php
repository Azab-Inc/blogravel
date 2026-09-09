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
    /**
     * Dev users keyed by email. All use the password "password".
     *
     * @var array<int, array{email: string, first_name: string, last_name: string, role: Role, tenant: string}>
     */
    protected array $devUsers = [
        ['email' => 'contact@azaber.com', 'first_name' => 'Alex', 'last_name' => 'Zab', 'role' => Role::SuperAdmin, 'tenant' => 'azaber.com'],
        ['email' => 'admin@acme.io', 'first_name' => 'Ada', 'last_name' => 'Minner', 'role' => Role::Admin, 'tenant' => 'acme.io'],
        ['email' => 'editor@acme.io', 'first_name' => 'Eddy', 'last_name' => 'Tor', 'role' => Role::Editor, 'tenant' => 'acme.io'],
        ['email' => 'author@acme.io', 'first_name' => 'Ari', 'last_name' => 'Thor', 'role' => Role::Author, 'tenant' => 'acme.io'],
        ['email' => 'admin@globex.net', 'first_name' => 'Gina', 'last_name' => 'Admin', 'role' => Role::Admin, 'tenant' => 'globex.net'],
        ['email' => 'editor@globex.net', 'first_name' => 'Eve', 'last_name' => 'Globex', 'role' => Role::Editor, 'tenant' => 'globex.net'],
        ['email' => 'author@globex.net', 'first_name' => 'Oli', 'last_name' => 'Vern', 'role' => Role::Author, 'tenant' => 'globex.net'],
    ];

    public function run(): void
    {
        $tenants = [];

        foreach ($this->devUsers as $devUser) {
            $tenants[$devUser['tenant']] ??= Tenant::updateOrCreate(
                ['domain' => $devUser['tenant']],
                [
                    'name' => $devUser['tenant'],
                    'plan' => Plan::Free,
                ]
            );

            User::updateOrCreate(
                ['email' => $devUser['email']],
                [
                    'name' => $devUser['first_name'].' '.$devUser['last_name'],
                    'first_name' => $devUser['first_name'],
                    'last_name' => $devUser['last_name'],
                    'password' => Hash::make('password'),
                    'role' => $devUser['role'],
                    'tenant_id' => $tenants[$devUser['tenant']]->id,
                ]
            );
        }
    }
}
