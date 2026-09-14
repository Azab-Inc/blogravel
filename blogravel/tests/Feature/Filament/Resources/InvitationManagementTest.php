<?php

use App\Enums\Role;
use App\Filament\Resources\InvitationResource\Pages\CreateInvitation;
use App\Filament\Resources\InvitationResource\Pages\EditInvitation;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

test('a persisted super admin invitation cannot be viewed', function () {
    $invitation = Invitation::factory()->create([
        'role' => Role::SuperAdmin,
    ]);

    $this->get(route('invitations.accept', ['token' => $invitation->token]))
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors(['invitation' => 'This invitation is no longer valid.']);
});

test('admin cannot create a super admin invitation through the invitation form', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create([
        'role' => Role::Admin,
    ]);

    $this->actingAs($admin);

    Livewire::test(CreateInvitation::class)
        ->fillForm([
            'type' => 'email',
            'email' => 'blocked-super-admin@example.com',
            'role' => Role::SuperAdmin->value,
            'expires_at' => '2030-01-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['role']);

    expect(Invitation::query()->where('email', 'blocked-super-admin@example.com')->exists())
        ->toBeFalse();
});

test('admin cannot edit an invitation to the super admin role through the invitation form', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create([
        'role' => Role::Admin,
    ]);
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Author,
    ]);

    $this->actingAs($admin);

    Livewire::test(EditInvitation::class, ['record' => $invitation->getKey()])
        ->fillForm([
            'type' => $invitation->type,
            'email' => $invitation->email,
            'role' => Role::SuperAdmin->value,
            'expires_at' => '2030-01-01',
        ])
        ->call('save')
        ->assertHasFormErrors(['role']);

    expect($invitation->refresh()->role)->toBe(Role::Author);
});

test('a super admin invitation cannot escalate an existing user when accepted', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create([
        'email' => 'existing@example.com',
        'role' => Role::Author,
    ]);
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => $user->email,
        'role' => Role::SuperAdmin,
    ]);

    $response = $this->post(route('invitations.accept.post', ['token' => $invitation->token]));

    $response->assertRedirect(route('home'))
        ->assertSessionHasErrors(['invitation' => 'This invitation is no longer valid.']);

    expect($user->refresh()->role)->toBe(Role::Author)
        ->and($invitation->fresh()->accepted_at)->toBeNull();
});

test('a super admin invitation cannot create a super admin user when accepted', function () {
    $invitation = Invitation::factory()->create([
        'email' => 'new-super-admin@example.com',
        'role' => Role::SuperAdmin,
    ]);

    $response = $this->post(route('invitations.accept.post', ['token' => $invitation->token]), [
        'first_name' => 'New',
        'last_name' => 'Super Admin',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('home'))
        ->assertSessionHasErrors(['invitation' => 'This invitation is no longer valid.']);

    expect(User::query()->where('email', 'new-super-admin@example.com')->exists())
        ->toBeFalse();
});
