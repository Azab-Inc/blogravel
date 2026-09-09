<?php

use App\Models\Tenant;
use App\Models\User;

test('mfa columns are mass-assignable so filament multi-factor setup persists', function () {
    $user = User::factory()->forTenant(Tenant::factory()->create())->create();

    // Filament's AppAuthentication provider saves the secret and recovery codes
    // through update()/mass assignment. If these columns are not fillable the
    // write is silently discarded and MFA setup never persists.
    $user->update([
        'two_factor_secret' => 'KRSXG5CTMVRXEZLU',
        'two_factor_recovery_codes' => '["code-1","code-2"]',
        'two_factor_confirmed_at' => now(),
        'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        'app_authentication_recovery_codes' => '["rc-1","rc-2"]',
        'has_email_authentication' => true,
    ]);

    $user->refresh();

    expect($user->two_factor_secret)->toBe('KRSXG5CTMVRXEZLU')
        ->and($user->two_factor_recovery_codes)->toBe('["code-1","code-2"]')
        ->and($user->two_factor_confirmed_at)->not->toBeNull()
        ->and($user->app_authentication_secret)->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->app_authentication_recovery_codes)->toBe('["rc-1","rc-2"]')
        ->and($user->has_email_authentication)->toBeTrue();
});
