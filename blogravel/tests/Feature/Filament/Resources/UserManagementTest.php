<?php

use App\Enums\Role;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

test('super admin can create a super admin user', function () {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->forTenant($tenant)->create([
        'role' => Role::SuperAdmin,
    ]);

    $this->actingAs($superAdmin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'first_name' => 'New',
            'last_name' => 'Super Admin',
            'email' => 'new-super-admin@example.com',
            'password' => 'password',
            'role' => Role::SuperAdmin->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'new-super-admin@example.com')->value('role'))
        ->toBe(Role::SuperAdmin);
});

test('super admin can promote an existing user to super admin', function () {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->forTenant($tenant)->create([
        'role' => Role::SuperAdmin,
    ]);
    $user = User::factory()->forTenant($tenant)->create([
        'role' => Role::Admin,
    ]);

    $this->actingAs($superAdmin);

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => Role::SuperAdmin->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->role)->toBe(Role::SuperAdmin);
});

test('admin cannot assign the super admin role through the user form', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create([
        'role' => Role::Admin,
    ]);

    $this->actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'first_name' => 'Blocked',
            'last_name' => 'Super Admin',
            'email' => 'blocked-super-admin@example.com',
            'password' => 'password',
            'role' => Role::SuperAdmin->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['role']);

    expect(User::query()->where('email', 'blocked-super-admin@example.com')->exists())
        ->toBeFalse();
});

test('admin cannot promote an existing user to super admin through the edit user form', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create([
        'role' => Role::Admin,
    ]);
    $user = User::factory()->forTenant($tenant)->create([
        'role' => Role::Author,
    ]);

    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => Role::SuperAdmin->value,
        ])
        ->call('save')
        ->assertHasFormErrors(['role']);

    expect($user->refresh()->role)->toBe(Role::Author);
});

test('admin-created users stay in the admins tenant', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create([
        'role' => Role::Admin,
    ]);

    $this->actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'first_name' => 'Tenant',
            'last_name' => 'Author',
            'email' => 'tenant-author@example.com',
            'password' => 'password',
            'role' => Role::Author->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'tenant-author@example.com')->value('tenant_id'))
        ->toBe($tenant->id);
});

test('admin cannot edit a user from another tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create([
        'role' => Role::Admin,
    ]);
    $otherTenantUser = User::factory()->forTenant($otherTenant)->create();

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.users.edit', ['record' => $otherTenantUser]))
        ->assertNotFound();
});

test('super admin can delete a user through the edit user resource action', function () {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->forTenant($tenant)->create([
        'role' => Role::SuperAdmin,
    ]);
    $user = User::factory()->forTenant($tenant)->create();

    $this->actingAs($superAdmin);

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->callAction(DeleteAction::class);

    expect(User::query()->find($user->getKey()))->toBeNull();
});
