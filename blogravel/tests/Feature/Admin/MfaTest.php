<?php

use App\Enums\Role;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel(
        Filament::getPanel('admin')
    );
});

it('allows super_admin to access admin panel without MFA', function () {
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);

    actingAs($admin)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertOk();
});

it('allows non-admin users to access admin panel', function () {
    $user = User::factory()->create(['role' => Role::Author]);

    actingAs($user)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertOk();
});

it('has MFA providers configured when flag is on', function () {
    config(['services.mfa.required' => true]);

    $panel = Filament::getPanel('admin');
    $mfaProviders = $panel->getMultiFactorAuthenticationProviders();

    expect($mfaProviders)->toHaveCount(2);
});

it('has no MFA providers when flag is off', function () {
    config(['services.mfa.required' => false]);

    $panel = Filament::getPanel('admin');
    $mfaProviders = $panel->getMultiFactorAuthenticationProviders();

    expect($mfaProviders)->toBeEmpty();
});

it('requires MFA when flag is on', function () {
    config(['services.mfa.required' => true]);

    $panel = Filament::getPanel('admin');
    expect($panel->isMultiFactorAuthenticationRequired())->toBeTrue();
});

it('does not require MFA when flag is off', function () {
    config(['services.mfa.required' => false]);

    $panel = Filament::getPanel('admin');
    expect($panel->isMultiFactorAuthenticationRequired())->toBeFalse();
});

it('enables database notifications with 30 second polling', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->hasDatabaseNotifications())->toBeTrue()
        ->and($panel->getDatabaseNotificationsPollingInterval())->toBe('30s');
});

it('renders the database notifications bell on panel pages', function () {
    $user = User::factory()->create([
        'role' => Role::SuperAdmin,
    ]);

    actingAs($user)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertOk()
        ->assertSee('database-notifications');
});

it('stores recovery codes as encrypted JSON on user', function () {
    $user = User::factory()->create([
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1', 'recovery-code-2'])),
    ]);

    $codes = decrypt($user->two_factor_recovery_codes);
    $decoded = json_decode($codes, true);

    expect($decoded)->toBeArray()
        ->toContain('recovery-code-1')
        ->toContain('recovery-code-2');
});
