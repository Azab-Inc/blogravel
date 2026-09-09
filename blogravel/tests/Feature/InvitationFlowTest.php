<?php

use App\Enums\Role;
use App\Jobs\SendInvitationJob;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('invitation model generates valid token', function () {
    $token = Invitation::generateToken();
    expect($token)->toHaveLength(64)->toBeString();
});

test('invitation is valid when not accepted and not expired', function () {
    $invitation = Invitation::factory()->create([
        'expires_at' => now()->addDays(7),
        'accepted_at' => null,
    ]);
    expect($invitation->isValid())->toBeTrue();
});

test('invitation is invalid when accepted', function () {
    $invitation = Invitation::factory()->accepted()->create();
    expect($invitation->isValid())->toBeFalse();
    expect($invitation->isAccepted())->toBeTrue();
});

test('invitation is invalid when expired', function () {
    $invitation = Invitation::factory()->expired()->create();
    expect($invitation->isValid())->toBeFalse();
    expect($invitation->isExpired())->toBeTrue();
});

test('invitation belongs to tenant', function () {
    $tenant = Tenant::factory()->create();
    $invitation = Invitation::factory()->create(['tenant_id' => $tenant->id]);
    expect($invitation->tenant->id)->toBe($tenant->id);
});

test('invitation can have an inviter', function () {
    $inviter = User::factory()->create();
    $invitation = Invitation::factory()->create(['invited_by' => $inviter->id]);
    expect($invitation->inviter->id)->toBe($inviter->id);
});

test('accept invitation with existing user', function () {
    $tenant = Tenant::factory()->create();
    $inviter = User::factory()->create(['tenant_id' => $tenant->id]);
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'existing@example.com',
        'role' => Role::Editor,
        'invited_by' => $inviter->id,
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->post(route('invitations.accept.post', ['token' => $invitation->token]));

    $existingUser->refresh();
    expect($existingUser->tenant_id)->toBe($tenant->id);
    expect($existingUser->role)->toBe(Role::Editor);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
    $response->assertRedirect('/admin');
});

test('accept invitation with new user creates account', function () {
    $tenant = Tenant::factory()->create();
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'newuser@example.com',
        'role' => Role::Author,
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->post(route('invitations.accept.post', ['token' => $invitation->token]), [
        'first_name' => 'New',
        'last_name' => 'User',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::where('email', 'newuser@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->tenant_id)->toBe($tenant->id);
    expect($user->role)->toBe(Role::Author);
    expect($user->first_name)->toBe('New');
    expect($user->last_name)->toBe('User');
    $response->assertRedirect('/admin');
});

test('reject expired invitation', function () {
    $invitation = Invitation::factory()->expired()->create();

    $response = $this->post(route('invitations.accept.post', ['token' => $invitation->token]));

    $response->assertRedirect(route('home'));
});

test('send invitation job dispatches to queue', function () {
    Queue::fake();
    $invitation = Invitation::factory()->create();

    SendInvitationJob::dispatch($invitation);

    Queue::assertPushed(SendInvitationJob::class);
});

test('user can invite capability check', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $admin = User::factory()->create(['role' => Role::Admin]);
    $editor = User::factory()->create(['role' => Role::Editor, 'can_invite' => false]);
    $editorWithInvite = User::factory()->create(['role' => Role::Editor, 'can_invite' => true]);
    $author = User::factory()->create(['role' => Role::Author]);

    expect($superAdmin->canInvite())->toBeTrue();
    expect($admin->canInvite())->toBeTrue();
    expect($editor->canInvite())->toBeFalse();
    expect($editorWithInvite->canInvite())->toBeTrue();
    expect($author->canInvite())->toBeFalse();
});
