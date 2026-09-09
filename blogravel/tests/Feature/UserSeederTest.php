<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

test('UserSeeder creates the super admin and two admin/editor/author tenants', function () {
    app(UserSeeder::class)->run();

    expect(User::where('email', 'contact@azaber.com')->first()->role)->toBe(Role::SuperAdmin)
        ->and(Tenant::where('domain', 'azaber.com')->exists())->toBeTrue()
        ->and(Tenant::where('domain', 'acme.io')->exists())->toBeTrue()
        ->and(Tenant::where('domain', 'globex.net')->exists())->toBeTrue();

    $acme = Tenant::where('domain', 'acme.io')->first();
    $globex = Tenant::where('domain', 'globex.net')->first();

    expect(User::where('email', 'admin@acme.io')->first()->role)->toBe(Role::Admin)
        ->and(User::where('email', 'editor@acme.io')->first()->role)->toBe(Role::Editor)
        ->and(User::where('email', 'author@acme.io')->first()->role)->toBe(Role::Author)
        ->and(User::where('email', 'admin@globex.net')->first()->role)->toBe(Role::Admin)
        ->and(User::where('email', 'editor@globex.net')->first()->role)->toBe(Role::Editor)
        ->and(User::where('email', 'author@globex.net')->first()->role)->toBe(Role::Author);

    $acmeUsers = User::whereIn('email', ['admin@acme.io', 'editor@acme.io', 'author@acme.io'])->get();
    expect($acmeUsers)->toHaveCount(3)
        ->and($acmeUsers->every(fn ($u) => $u->tenant_id === $acme->id))->toBeTrue()
        ->and($acmeUsers->every(fn ($u) => Hash::check('password', $u->password)))->toBeTrue();
});

test('UserSeeder is idempotent', function () {
    app(UserSeeder::class)->run();
    app(UserSeeder::class)->run();

    expect(User::whereIn('email', [
        'contact@azaber.com',
        'admin@acme.io',
        'editor@acme.io',
        'author@acme.io',
        'admin@globex.net',
        'editor@globex.net',
        'author@globex.net',
    ])->count())->toBe(7)
        ->and(Tenant::count())->toBe(3);
});
