<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\SessionGuard;

test('session-based user resolution does not recurse into the tenant scope', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create(['role' => Role::Author]);

    // Simulate a real authenticated request: the session holds the guard's
    // login key and the guard must resolve the user via retrieveById, which
    // runs a User query that applies the TenantScope global scope.
    $guardKey = 'login_web_'.sha1(SessionGuard::class);

    $response = $this->withSession([$guardKey => $user->getAuthIdentifier()])
        ->get('/debug/session-check');

    $response->assertOk();
    $response->assertSeeText("user={$user->email}");
});

test('user resolution via a fresh session guard completes without stack overflow', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create(['role' => Role::SuperAdmin]);

    $session = app('session.store');
    $guard = new SessionGuard('web', auth()->getProvider(), $session, request());

    $session->put($guard->getName(), $user->getAuthIdentifier());

    $resolved = $guard->user();

    expect($resolved)->not->toBeNull()
        ->and($resolved->email)->toBe($user->email);
});
