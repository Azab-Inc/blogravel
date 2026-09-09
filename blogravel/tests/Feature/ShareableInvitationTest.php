<?php

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;

test('shareable invitation link opens without signature', function () {
    $tenant = Tenant::factory()->create();
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => 'shareable',
        'email' => null,
        'token' => Invitation::generateToken(),
    ]);

    $this->get(route('invitations.accept', ['token' => $invitation->token]))
        ->assertOk()
        ->assertSee('You are Invited!');
});

test('shareable invitation can be accepted with a new email and account', function () {
    $tenant = Tenant::factory()->create();
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => 'shareable',
        'email' => null,
        'role' => Role::Author,
        'token' => Invitation::generateToken(),
    ]);

    $this->post(route('invitations.accept.post', ['token' => $invitation->token]), [
        'first_name' => 'Sam',
        'last_name' => 'Share',
        'email' => 'sam@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])
        ->assertRedirect(route('filament.admin.pages.dashboard'));

    $user = User::where('email', 'sam@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->tenant_id)->toBe($tenant->id)
        ->and($user->role)->toBe(Role::Author);

    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('shareable invitation accept validates email format and uniqueness', function () {
    $tenant = Tenant::factory()->create();
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => 'shareable',
        'email' => null,
        'token' => Invitation::generateToken(),
    ]);

    $this->post(route('invitations.accept.post', ['token' => $invitation->token]), [
        'first_name' => 'Sam',
        'last_name' => 'Share',
        'email' => 'not-an-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('email');
});

test('email invitation accept keeps using invited email and does not allow overriding it', function () {
    $tenant = Tenant::factory()->create();
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => 'email',
        'email' => 'invited@example.com',
        'role' => Role::Editor,
        'token' => Invitation::generateToken(),
    ]);

    $this->post(route('invitations.accept.post', ['token' => $invitation->token]), [
        'first_name' => 'Ed',
        'last_name' => 'Invitee',
        'email' => 'evil@attacker.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('filament.admin.pages.dashboard'));

    $user = User::where('email', 'invited@example.com')->first();
    expect($user)->not->toBeNull()
        ->and(User::where('email', 'evil@attacker.com')->exists())->toBeFalse();
});
