<?php

use App\Models\User;

test('fortify is installed and configured for headless', function () {
    expect(config('fortify.views'))->toBeFalse();
});

test('login route is registered as POST (no GET view route)', function () {
    $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('login.store');

    expect($route)->not->toBeNull();
    expect($route->methods())->toContain('POST');
});

test('registration route is registered as POST', function () {
    $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('register.store');

    expect($route)->not->toBeNull();
    expect($route->methods())->toContain('POST');
});

test('password reset routes are registered', function () {
    $emailRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('password.email');
    $updateRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('password.update');

    expect($emailRoute)->not->toBeNull();
    expect($updateRoute)->not->toBeNull();
});

test('two-factor authentication routes are registered', function () {
    $challengeRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('two-factor.login.store');
    $enableRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('two-factor.enable');

    expect($challengeRoute)->not->toBeNull();
    expect($enableRoute)->not->toBeNull();
});

test('logout route is registered', function () {
    $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('logout');

    expect($route)->not->toBeNull();
    expect($route->methods())->toContain('POST');
});

test('user profile and password update routes are registered', function () {
    $profileRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('user-profile-information.update');
    $passwordRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('user-password.update');

    expect($profileRoute)->not->toBeNull();
    expect($passwordRoute)->not->toBeNull();
});

test('view routes are not registered when views disabled', function () {
    $loginViewRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('login');
    $registerViewRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('register');
    $passwordRequestRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('password.request');

    expect($loginViewRoute)->toBeNull();
    expect($registerViewRoute)->toBeNull();
    expect($passwordRequestRoute)->toBeNull();
});

test('user can authenticate via API', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk();
});

test('user can register via API', function () {
    $response = $this->postJson('/register', [
        'name' => 'Test User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertCreated();
});
