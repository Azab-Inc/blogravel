<?php

use App\Filament\Pages\Auth\Register;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(
        Filament::getPanel('admin')
    );
});

test('register page can be rendered', function () {
    $response = $this->get(route('filament.admin.auth.register'));

    $response->assertOk();
});

test('user can register with valid data', function () {
    Livewire::test(Register::class)
        ->fillForm([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'name' => 'John Doe',
    ]);
});

test('registration fails with duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    Livewire::test(Register::class)
        ->fillForm([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'existing@example.com',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ])
        ->call('register')
        ->assertHasFormErrors(['email']);
});

test('registration fails with missing required fields', function () {
    Livewire::test(Register::class)
        ->fillForm([])
        ->call('register')
        ->assertHasFormErrors(['first_name', 'last_name', 'email', 'password']);
});

test('registration fails with invalid email', function () {
    Livewire::test(Register::class)
        ->fillForm([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'not-an-email',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ])
        ->call('register')
        ->assertHasFormErrors(['email']);
});

test('registration fails with short password', function () {
    Livewire::test(Register::class)
        ->fillForm([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'short',
            'passwordConfirmation' => 'short',
        ])
        ->call('register')
        ->assertHasFormErrors(['password']);
});
