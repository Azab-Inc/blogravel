<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('user model has createToken method', function () {
    $user = User::factory()->create();
    expect(method_exists($user, 'createToken'))->toBeTrue();
});

test('user can create personal access token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');
    expect($token->accessToken)->not->toBeNull();
    expect($token->accessToken->name)->toBe('test-token');
});

test('personal_access_tokens table exists', function () {
    expect(Schema::hasTable('personal_access_tokens'))->toBeTrue();
});
