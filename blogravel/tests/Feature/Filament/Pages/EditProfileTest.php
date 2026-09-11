<?php

use App\Enums\Role;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('profile settings page renders for authenticated user', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Author,
    ]);
    $this->actingAs($user);

    $response = $this->get('/admin/profile');
    $response->assertStatus(200);
});

it('profile settings page renders for admin', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    $response = $this->get('/admin/profile');
    $response->assertStatus(200);
});

it('profile settings page renders for superadmin', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::SuperAdmin,
    ]);
    $this->actingAs($user);

    $response = $this->get('/admin/profile');
    $response->assertStatus(200);
});

it('loads current user profile data', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Author,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
    ]);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertSet('data.first_name', 'John')
        ->assertSet('data.last_name', 'Doe')
        ->assertSet('data.email', 'john@example.com');
});

it('can update profile information', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Author,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
    ]);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.first_name', 'Jane')
        ->set('data.last_name', 'Smith')
        ->set('data.email', 'jane@example.com')
        ->set('data.currentPassword', 'password')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->first_name)->toBe('Jane');
    expect($user->last_name)->toBe('Smith');
    expect($user->email)->toBe('jane@example.com');
});

it('validates required fields on save', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Author,
    ]);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.first_name', '')
        ->set('data.last_name', '')
        ->set('data.email', '')
        ->call('save')
        ->assertHasErrors(['data.first_name', 'data.last_name', 'data.email']);
});

it('validates email uniqueness on save', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Author,
        'email' => 'john@example.com',
    ]);
    User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'jane@example.com',
    ]);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.email', 'jane@example.com')
        ->call('save')
        ->assertHasErrors(['data.email']);
});

it('can change password with valid current password', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Author,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
    ]);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.first_name', 'John')
        ->set('data.last_name', 'Doe')
        ->set('data.email', 'john@example.com')
        ->set('data.password', 'new-password-123')
        ->set('data.passwordConfirmation', 'new-password-123')
        ->set('data.currentPassword', 'password')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect(Hash::check('new-password-123', $user->password))->toBeTrue();
});

it('rejects password change with wrong current password', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Author,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
    ]);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.first_name', 'John')
        ->set('data.last_name', 'Doe')
        ->set('data.email', 'john@example.com')
        ->set('data.password', 'new-password-123')
        ->set('data.passwordConfirmation', 'new-password-123')
        ->set('data.currentPassword', 'wrong-password')
        ->call('save')
        ->assertHasErrors(['data.currentPassword']);

    $user->refresh();
    expect(Hash::check('new-password-123', $user->password))->toBeFalse();
});

it('does not change password when fields are empty', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Author,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
    ]);
    $originalPassword = $user->password;
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.first_name', 'John')
        ->set('data.last_name', 'Doe')
        ->set('data.email', 'john@example.com')
        ->call('save');

    $user->refresh();
    expect($user->password)->toBe($originalPassword);
});

it('can close own account', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Author,
    ]);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->call('closeAccount');

    $this->assertSoftDeleted('users', ['id' => $user->id]);
});

it('closes tenant when last admin closes account', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->call('closeAccount');

    $this->assertSoftDeleted('users', ['id' => $user->id]);
    $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
});

it('does not close tenant when non-last admin closes account', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->call('closeAccount');

    $this->assertSoftDeleted('users', ['id' => $user->id]);
    expect(Tenant::withTrashed()->find($tenant->id)->deleted_at)->toBeNull();
});
