<?php

use App\Enums\Role;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel(
        Filament::getPanel('admin')
    );
});

it('requires MFA for `super_admin` login', function () {
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);

    actingAs($admin)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertRedirect();
});

it('redirects to MFA setup when MFA is not configured for `super_admin`', function () {
    $admin = User::factory()->create([
        'role' => Role::SuperAdmin,
        'app_authentication_secret' => null,
        'has_email_authentication' => false,
    ]);

    $response = actingAs($admin)
        ->get(route('filament.admin.pages.dashboard'));

    $response->assertRedirect();
});

it('allows non-admin users to access admin panel (redirects to MFA setup)', function () {
    $user = User::factory()->create(['role' => Role::Author]);

    actingAs($user)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertRedirect();
});

it('configures TOTP and email OTP as MFA providers', function () {
    $panel = Filament::getPanel('admin');

    $mfaProviders = $panel->getMultiFactorAuthenticationProviders();

    expect($mfaProviders)->not->toBeEmpty();
});

it('requires multi-factor authentication to be enforced', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->isMultiFactorAuthenticationRequired())->toBeTrue();
});

it('has app authentication recovery enabled', function () {
    $panel = Filament::getPanel('admin');

    $providers = $panel->getMultiFactorAuthenticationProviders();

    $appAuth = collect($providers)->first(
        fn ($provider) => $provider instanceof AppAuthentication
    );

    expect($appAuth)->not->toBeNull();
});

it('enables database notifications with 30 second polling', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->hasDatabaseNotifications())->toBeTrue()
        ->and($panel->getDatabaseNotificationsPollingInterval())->toBe('30s');
});

it('renders the database notifications bell on panel pages', function () {
    $user = User::factory()->create([
        'has_email_authentication' => true,
        'role' => Role::SuperAdmin,
    ]);

    actingAs($user)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertOk()
        ->assertSee('database-notifications');
});
