<?php

use App\Enums\Role;
use App\Models\User;

test('super_admin can access admin-only route via session auth', function () {
    $user = User::factory()->create(['role' => Role::SuperAdmin]);
    $this->actingAs($user)->get('/admin/secret')->assertOk();
});

test('editor is blocked from admin-only route via session auth', function () {
    $user = User::factory()->create(['role' => Role::Editor]);
    $this->actingAs($user)->get('/admin/secret')->assertForbidden();
});

test('author is blocked from admin-only route via session auth', function () {
    $user = User::factory()->create(['role' => Role::Author]);
    $this->actingAs($user)->get('/admin/secret')->assertForbidden();
});

test('user can login and receive sanctum token', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'role']]);
});

test('user receives correct role in login response', function () {
    $user = User::factory()->create(['role' => Role::SuperAdmin]);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.role', Role::SuperAdmin->value);
});

test('unauthenticated user cannot access protected api routes', function () {
    $this->postJson('/api/v1/logout')
        ->assertUnauthorized();
});

test('user can logout with sanctum token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('logout-token')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/logout')
        ->assertNoContent();
});
