<?php

use App\Models\User;

test('authenticated user can logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/logout');

    $response->assertNoContent();
});

test('unauthenticated user cannot logout', function () {
    $response = $this->postJson('/api/v1/logout');

    $response->assertUnauthorized();
});
