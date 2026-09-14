<?php

use App\Enums\AccountRecoveryResult;
use App\Enums\DeletionReason;
use App\Enums\Plan;
use App\Enums\Role;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RecoverAccount;
use App\Filament\Pages\TenantSetup;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AccountRecoveredNotification;
use App\Services\AccountRecoveryService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('returns invalid credentials without restoring an account', function (): void {
    $user = User::factory()->create([
        'email' => 'invalid@example.com',
        'password' => Hash::make('password'),
        'deletion_reason' => DeletionReason::SelfClosed,
    ]);
    $user->delete();

    expect(app(AccountRecoveryService::class)->recover('invalid@example.com', 'wrong-password'))
        ->toBe(AccountRecoveryResult::InvalidCredentials)
        ->and(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
});

it('rejects recovery when an active account owns the email', function (): void {
    $deleted = User::factory()->create([
        'email' => 'reused@example.com',
        'password' => Hash::make('password'),
        'deletion_reason' => DeletionReason::SelfClosed,
    ]);
    $deleted->delete();
    User::factory()->create(['email' => 'reused@example.com']);

    expect(app(AccountRecoveryService::class)->recover('reused@example.com', 'password'))
        ->toBe(AccountRecoveryResult::ActiveEmailConflict);
});

it('restores a self-closed user with an active tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create([
        'email' => 'active-tenant@example.com',
        'password' => Hash::make('password'),
        'deletion_reason' => DeletionReason::SelfClosed,
    ]);
    $user->delete();

    expect(app(AccountRecoveryService::class)->recover('active-tenant@example.com', 'password'))
        ->toBe(AccountRecoveryResult::RestoredUser)
        ->and(User::find($user->id))->not->toBeNull()
        ->and(Tenant::find($tenant->id))->not->toBeNull();
});

it('restores a self-closed admin and tenant within both windows', function (): void {
    Notification::fake();
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create([
        'email' => 'recover@example.com',
        'password' => Hash::make('password'),
        'role' => Role::Admin,
        'deletion_reason' => DeletionReason::SelfClosed,
    ]);
    $user->delete();
    $tenant->delete();

    expect(app(AccountRecoveryService::class)->recover('recover@example.com', 'password'))
        ->toBe(AccountRecoveryResult::RestoredUserAndTenant);

    $restoredUser = User::find($user->id);

    expect($restoredUser)->not->toBeNull()
        ->and(Tenant::find($tenant->id))->not->toBeNull();
    Notification::assertSentTo($restoredUser, AccountRecoveredNotification::class);
});

it('restores an admin without its expired tenant and marks setup as required', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create([
        'email' => 'new-tenant@example.com',
        'password' => Hash::make('password'),
        'role' => Role::Admin,
        'deletion_reason' => DeletionReason::SelfClosed,
    ]);
    $user->delete();
    $tenant->delete();
    markDeletedAt($tenant, now()->subDays(31));

    expect(app(AccountRecoveryService::class)->recover('new-tenant@example.com', 'password'))
        ->toBe(AccountRecoveryResult::RestoredUserNeedsTenant)
        ->and(User::find($user->id)->tenant_id)->toBeNull()
        ->and(session('recovery.needs_tenant_setup'))->toBe($user->id);
});

it('blocks a non-admin whose tenant recovery window has expired', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create([
        'email' => 'author@example.com',
        'password' => Hash::make('password'),
        'role' => Role::Author,
        'deletion_reason' => DeletionReason::SelfClosed,
    ]);
    $user->delete();
    $tenant->delete();
    markDeletedAt($tenant, now()->subDays(31));

    expect(app(AccountRecoveryService::class)->recover('author@example.com', 'password'))
        ->toBe(AccountRecoveryResult::TenantClosureBlocked)
        ->and(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
});

it('blocks a non-admin from restoring a deleted tenant within its window', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create([
        'email' => 'deleted-author@example.com',
        'password' => Hash::make('password'),
        'role' => Role::Author,
        'deletion_reason' => DeletionReason::SelfClosed,
    ]);
    $user->delete();
    $tenant->delete();

    expect(app(AccountRecoveryService::class)->recover('deleted-author@example.com', 'password'))
        ->toBe(AccountRecoveryResult::TenantClosureBlocked)
        ->and(User::withTrashed()->find($user->id)->trashed())->toBeTrue()
        ->and(Tenant::withTrashed()->find($tenant->id)->trashed())->toBeTrue();
});

it('blocks an administrator-removed user even with valid credentials', function (): void {
    $user = User::factory()->create([
        'email' => 'removed@example.com',
        'password' => Hash::make('password'),
        'deletion_reason' => DeletionReason::AdminRemoved,
    ]);
    $user->delete();

    expect(app(AccountRecoveryService::class)->recover('removed@example.com', 'password'))
        ->toBe(AccountRecoveryResult::AdminRemovalBlocked);
});

it('blocks a user deleted by tenant closure', function (): void {
    $user = User::factory()->create([
        'email' => 'tenant-closed@example.com',
        'password' => Hash::make('password'),
        'deletion_reason' => DeletionReason::TenantClosed,
    ]);
    $user->delete();

    expect(app(AccountRecoveryService::class)->recover('tenant-closed@example.com', 'password'))
        ->toBe(AccountRecoveryResult::TenantClosureBlocked);
});

it('returns expired when the user recovery window has passed', function (): void {
    $user = User::factory()->create([
        'email' => 'expired@example.com',
        'password' => Hash::make('password'),
        'deletion_reason' => DeletionReason::SelfClosed,
    ]);
    $user->delete();
    markDeletedAt($user, now()->subDays(31));

    expect(app(AccountRecoveryService::class)->recover('expired@example.com', 'password'))
        ->toBe(AccountRecoveryResult::Expired);
});

it('restores a self-closed super admin without authenticating it', function (): void {
    $user = User::factory()->create([
        'email' => 'super-admin@example.com',
        'password' => Hash::make('password'),
        'role' => Role::SuperAdmin,
        'deletion_reason' => DeletionReason::SelfClosed,
    ]);
    $user->delete();

    expect(app(AccountRecoveryService::class)->recover('super-admin@example.com', 'password'))
        ->toBe(AccountRecoveryResult::RestoredUser)
        ->and(User::find($user->id))->not->toBeNull();
    expect(Auth::check())->toBeFalse();
});

it('registers the custom login, recovery, and tenant setup routes', function (): void {
    expect(Route::getRoutes()->getByName('filament.admin.auth.login')->getActionName())
        ->toContain(Login::class);

    expect(Route::getRoutes()->getByName('filament.admin.auth.recover-account'))
        ->not->toBeNull();
    expect(Route::getRoutes()->getByName('filament.admin.tenant-setup'))
        ->not->toBeNull();
});

it('renders an account recovery link on the login page', function (): void {
    $subheading = Livewire::test(Login::class)->instance()->getSubheading()->toHtml();

    expect($subheading)
        ->toContain(route('filament.admin.auth.recover-account'))
        ->toContain('Recover account');
});

it('recovers through the public page without calling Auth login', function (): void {
    Notification::fake();
    Auth::shouldReceive('login')->never();

    $user = User::factory()->create([
        'email' => 'page-recovery@example.com',
        'password' => Hash::make('password'),
        'deletion_reason' => DeletionReason::SelfClosed,
    ]);
    $user->delete();

    Livewire::test(RecoverAccount::class)
        ->set('data.email', 'page-recovery@example.com')
        ->set('data.password', 'password')
        ->call('recover')
        ->assertRedirect(route('filament.admin.auth.login'));

    Notification::assertSentTo($user, AccountRecoveredNotification::class);
});

it('creates a free tenant for a recovered detached admin and consumes the marker', function (): void {
    $user = User::factory()->create([
        'role' => Role::Admin,
        'tenant_id' => null,
    ]);
    $this->actingAs($user);
    session()->put('recovery.needs_tenant_setup', $user->id);

    Livewire::test(TenantSetup::class)
        ->fillForm(['name' => 'Recovered Tenant'])
        ->call('createTenant')
        ->assertHasNoFormErrors()
        ->assertRedirect(route('filament.admin.pages.dashboard'));

    $user->refresh();
    $tenant = Tenant::find($user->tenant_id);

    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Recovered Tenant')
        ->and($tenant->plan)->toBe(Plan::Free)
        ->and(session('recovery.needs_tenant_setup'))->toBeNull();
});

it('denies tenant setup without the recovery marker', function (): void {
    $user = User::factory()->create([
        'role' => Role::Admin,
        'tenant_id' => null,
    ]);
    $this->actingAs($user);

    $this->get(route('filament.admin.tenant-setup'))->assertForbidden();
});

function markDeletedAt(User|Tenant $model, DateTimeInterface $deletedAt): void
{
    $model->forceFill(['deleted_at' => $deletedAt])->saveQuietly();
}
