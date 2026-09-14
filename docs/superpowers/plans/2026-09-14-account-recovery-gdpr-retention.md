# Account Recovery and GDPR Retention Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a secure 30-day self-service account recovery flow, safe administrator data export, and retryable GDPR permanent deletion lifecycle for users and tenants.

**Architecture:** Keep Eloquent models responsible for persistence and global soft-delete behavior, and put security-critical lifecycle transitions in `AccountLifecycleService`, `AccountRecoveryService`, `DataExportService`, and `RetentionPurgeService`. Expose those services through thin Filament pages/actions, queued jobs, and one daily Artisan command. Use PostgreSQL’s partial unique index for active emails and preserve detached users until their own retention deadline.

**Tech Stack:** Laravel 13.29, PHP 8.5, PostgreSQL, Filament 5.7, Livewire 4.4, Pest 4.7, Playwright 1.62, Redis queues, private Laravel filesystem storage, OpenSpout 4.32.

**Spec:** `docs/superpowers/specs/2026-09-14-account-recovery-gdpr-retention-design.md`

## Global Constraints

- Recovery is web/Filament only; `/api/v1/login` continues rejecting soft-deleted users.
- Recovery requires email and password, uses a separate strict email/IP throttle, and redirects to normal login so existing MFA runs.
- Tenant ownership remains role-based; `admin` and `super_admin` are the administrator set.
- Administrator removal records provenance and permanently blocks self-service recovery.
- Active emails are unique through a PostgreSQL partial unique index where `deleted_at IS NULL`; soft-deleted historical rows retain their email.
- User and tenant recovery deadlines are each 30 days from their own `deleted_at` timestamp.
- If a tenant is purged before a user’s own deadline, the user is detached and retained until the user deadline; only eligible self-closed admins may recover into tenant setup.
- Tenant admins export only their tenant; super admins may select active or recoverable soft-deleted tenants.
- Every export is a private ZIP containing either per-dataset CSV files or an XLSX workbook plus uploaded media files; generated files expire after 24 hours.
- Exports use explicit safe field allowlists and never include passwords, tokens, secrets, MFA material, passkeys, or backup archives.
- Tenant closure requires typed tenant-name or slug confirmation, but export remains optional.
- Purge is daily, idempotent, retryable, and must complete external cleanup before force-deleting database records.
- Use the project’s Laravel 13 and Filament 5 APIs; do not rely on older examples.
- Use factories in tests and run the narrowest affected Pest test after every test change.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes.
- Leave unrelated existing changes in `blogravel/resources/themes/base/components/layouts/theme.blade.php` and `blogravel/tests/e2e/theme-pages.spec.ts` untouched.

---

## File Map

### Schema and domain

- Create `blogravel/app/Enums/DeletionReason.php` for controlled deletion reasons.
- Create `blogravel/database/migrations/*_add_deletion_metadata_and_active_email_index_to_users_table.php` for provenance columns and the active-email index.
- Modify `blogravel/app/Models/User.php` to cast deletion reason and expose lifecycle helpers without mass-assigning provenance.
- Modify `blogravel/composer.json` and `blogravel/composer.lock` to declare `openspout/openspout` directly.

### Lifecycle and authorization

- Create `blogravel/app/Services/AccountLifecycleService.php` for self-closure and administrator removal.
- Modify `blogravel/app/Filament/Pages/Settings.php` and `blogravel/app/Filament/Pages/Auth/EditProfile.php` to call the service and require typed confirmation for tenant closure.
- Modify `blogravel/app/Filament/Resources/UserResource/Pages/EditUser.php` and `blogravel/app/Filament/Resources/UserResource.php` so single and bulk administrator removals record provenance.
- Keep `blogravel/app/Policies/UserPolicy.php` unchanged; existing role boundaries authorize the lifecycle actions.
- Create `blogravel/tests/Feature/AccountLifecycleTest.php` for closure, removal, and email reuse.

### Recovery and tenant setup

- Create `blogravel/app/Enums/AccountRecoveryResult.php` for service outcomes.
- Create `blogravel/app/Services/AccountRecoveryService.php` for credential validation and recovery authorization.
- Create `blogravel/app/Filament/Pages/Auth/Login.php` extending Filament’s login page with the recovery link.
- Create `blogravel/app/Filament/Pages/Auth/RecoverAccount.php` for the unauthenticated recovery form.
- Create `blogravel/app/Filament/Pages/TenantSetup.php` for detached recovered admins.
- Modify `blogravel/app/Providers/Filament/AdminPanelProvider.php` to register the custom login and route groups.
- Create `blogravel/app/Notifications/AccountRecoveredNotification.php`.
- Create `blogravel/tests/Feature/AccountRecoveryTest.php`.

### Export

- Create `blogravel/app/Services/DataExportService.php` with explicit dataset and field definitions.
- Create `blogravel/app/Jobs/GenerateTenantExportJob.php`.
- Create `blogravel/app/Notifications/TenantExportReadyNotification.php`.
- Modify `blogravel/app/Filament/Pages/Settings.php` to add the tenant export action using the existing Settings/page action conventions.
- Modify `blogravel/app/Providers/Filament/AdminPanelProvider.php` to register the authenticated export download route.
- Create `blogravel/tests/Feature/GdprExportTest.php`.

### Purge and operations

- Create `blogravel/app/Services/RetentionPurgeService.php`.
- Create `blogravel/app/Console/Commands/PurgeExpiredDeletedDataCommand.php`.
- Modify `blogravel/routes/console.php` to register the daily command schedule.
- Modify `blogravel/compose.yaml` to add a scheduler runner alongside the queue worker.
- Create `blogravel/tests/Feature/GdprPurgeTest.php`.

### Browser coverage

- Create `blogravel/tests/e2e/account-recovery.spec.ts`.
- Create `blogravel/tests/e2e/gdpr-export.spec.ts`.

---

## Task 1: Schema, Deletion Reasons, and XLSX Dependency

**Files:**
- Create: `blogravel/app/Enums/DeletionReason.php`
- Create: `blogravel/database/migrations/*_add_deletion_metadata_and_active_email_index_to_users_table.php`
- Modify: `blogravel/app/Models/User.php`
- Modify: `blogravel/composer.json`
- Modify: `blogravel/composer.lock`
- Create: `blogravel/tests/Feature/AccountDeletionSchemaTest.php`

**Interfaces:**
- Produces `DeletionReason::SelfClosed`, `DeletionReason::AdminRemoved`, and `DeletionReason::TenantClosed` backed by strings.
- Produces nullable `users.deleted_by` and `users.deletion_reason` columns.
- Produces PostgreSQL index `users_email_active_unique` on `users.email` with predicate `deleted_at IS NULL`.
- Produces direct Composer availability for `OpenSpout` 4.x.

- [ ] **Step 1: Create the enum and migration skeleton with Artisan**

Run from `/home/swagoverlord/repos/blogravel/blogravel`:

```bash
php artisan make:migration add_deletion_metadata_and_active_email_index_to_users_table --table=users --no-interaction
```

Define the enum with a string backing type and TitleCase case names:

```php
enum DeletionReason: string
{
    case SelfClosed = 'self_closed';
    case AdminRemoved = 'admin_removed';
    case TenantClosed = 'tenant_closed';
}
```

- [ ] **Step 2: Write failing schema tests**

Add tests that assert the columns exist, the enum cast is active, a deleted email can be reused by an active user, and two active users cannot share an email:

```php
use App\Enums\DeletionReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('stores deletion provenance and casts the deletion reason', function () {
    $user = User::factory()->create([
        'deletion_reason' => DeletionReason::SelfClosed,
    ]);

    expect($user->deletion_reason)->toBe(DeletionReason::SelfClosed);
    expect(DB::select("select column_name from information_schema.columns where table_name = 'users' and column_name in ('deleted_by', 'deletion_reason')"))->toHaveCount(2);
});

it('allows an active user to reuse a soft-deleted email', function () {
    $deleted = User::factory()->create(['email' => 'reuse@example.com']);
    $deleted->delete();

    $active = User::factory()->create(['email' => 'reuse@example.com']);

    expect($active->email)->toBe('reuse@example.com');
});

it('rejects duplicate active emails', function () {
    User::factory()->create(['email' => 'duplicate@example.com']);

    expect(fn () => User::factory()->create(['email' => 'duplicate@example.com']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 3: Run the schema tests and confirm they fail for the missing index/columns**

Run:

```bash
php artisan test --compact tests/Feature/AccountDeletionSchemaTest.php
```

Expected: FAIL because the active partial index and provenance columns are not present.

- [ ] **Step 4: Implement the migration and model cast**

In the migration, add the nullable UUID value and deletion reason string, then replace the existing global email unique constraint with the PostgreSQL partial index:

```php
$table->uuid('deleted_by')->nullable()->after('deleted_at');
$table->string('deletion_reason')->nullable()->after('deleted_by');
```

Use PostgreSQL statements so the predicate is represented by the database:

```php
DB::statement('alter table users drop constraint if exists users_email_unique');
DB::statement('create unique index users_email_active_unique on users (email) where deleted_at is null');
```

The down migration must drop `users_email_active_unique`, restore the original unique constraint, and drop the provenance columns. In `User`, add `DeletionReason::class` to the existing casts array and use `forceFill()` from lifecycle services instead of adding provenance columns to the `Fillable` attribute.

- [ ] **Step 5: Declare OpenSpout directly**

Run:

```bash
composer require openspout/openspout:^4.32 --no-interaction
```

Keep the resulting `composer.json` and `composer.lock` changes. Do not add a second XLSX package.

- [ ] **Step 6: Run the schema tests and formatter**

Run:

```bash
php artisan test --compact tests/Feature/AccountDeletionSchemaTest.php
vendor/bin/pint --dirty --format agent
```

Expected: all schema tests PASS and Pint reports no remaining style changes.

- [ ] **Step 7: Commit the schema slice**

```bash
git add blogravel/app/Enums/DeletionReason.php blogravel/database/migrations blogravel/app/Models/User.php blogravel/composer.json blogravel/composer.lock blogravel/tests/Feature/AccountDeletionSchemaTest.php
git commit -m "Feature: add account deletion provenance and active email uniqueness"
```

---

## Task 2: Centralized Account Lifecycle and Administrator Removal

**Files:**
- Create: `blogravel/app/Services/AccountLifecycleService.php`
- Modify: `blogravel/app/Filament/Pages/Settings.php`
- Modify: `blogravel/app/Filament/Pages/Auth/EditProfile.php`
- Modify: `blogravel/app/Filament/Resources/UserResource/Pages/EditUser.php`
- Modify: `blogravel/app/Filament/Resources/UserResource.php`
- Keep: `blogravel/app/Scopes/TenantScope.php` behavior and add a regression test for soft-deleted tenant exclusion.
- Keep: `blogravel/app/Policies/UserPolicy.php` unchanged
- Create: `blogravel/tests/Feature/AccountLifecycleTest.php`

**Interfaces:**
- `AccountLifecycleService::close(User $user, ?string $tenantConfirmation = null): void`
- `AccountLifecycleService::remove(User $actor, User $target): void`
- `AccountLifecycleService::isLastAdministrator(User $user): bool`

- [ ] **Step 1: Write failing lifecycle tests**

Cover self-close metadata, last-admin tenant closure, non-last-admin tenant preservation, administrator-removal metadata, bulk removal, typed confirmation, and exclusion of soft-deleted tenants from tenant-scoped queries:

```php
it('records self closure and closes the tenant for the last admin', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme']);
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);

    app(AccountLifecycleService::class)->close($admin, 'Acme');

    expect(User::withTrashed()->find($admin->id)->deletion_reason)
        ->toBe(DeletionReason::SelfClosed);
    $this->assertSoftDeleted('users', ['id' => $admin->id]);
    $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
});

it('records an administrator removal and makes it non-self-recoverable', function () {
    $tenant = Tenant::factory()->create();
    $actor = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    $target = User::factory()->forTenant($tenant)->create(['role' => Role::Author]);

    app(AccountLifecycleService::class)->remove($actor, $target);

    $deleted = User::withTrashed()->findOrFail($target->id);
    expect($deleted->deletion_reason)->toBe(DeletionReason::AdminRemoved)
        ->and($deleted->deleted_by)->toBe($actor->id);
});

it('excludes soft-deleted tenants from tenant-scoped queries', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create(['role' => Role::Author]);
    $tenant->delete();

    $this->actingAs($user);

    expect(Post::where('tenant_id', $tenant->id)->get())->toBeEmpty();
});
```

- [ ] **Step 2: Run lifecycle tests to verify they fail**

Run:

```bash
php artisan test --compact tests/Feature/AccountLifecycleTest.php
```

Expected: FAIL because no service records provenance and existing actions call `delete()` directly.

- [ ] **Step 3: Implement the service transactionally**

Use `DB::transaction()` and explicit unscoped active-admin queries. Set provenance before `delete()`:

```php
$user->forceFill([
    'deleted_by' => $actor?->getKey(),
    'deletion_reason' => $reason,
])->saveQuietly();

$user->delete();
```

For self-close, lock the tenant and user rows where available, count active `admin` and `super_admin` users in the same tenant, soft-delete the tenant when the closing user is the last administrator, and leave all other tenant users untouched. For administrator removal, reject self-removal and rely on the existing policy authorization before recording `admin_removed`.

- [ ] **Step 4: Replace both account-closure implementations**

Inject `AccountLifecycleService` into the Settings and Edit Profile actions. Pass the typed tenant name/slug to `close()` when the current user is the last administrator. Preserve existing logout, notification, and redirect behavior after the service succeeds.

The action schema must validate the typed value against the current tenant’s name or slug and return a form error without deleting anything on mismatch.

- [ ] **Step 5: Replace direct admin deletion actions**

In `EditUser`, replace the default `DeleteAction` callback with an action that calls `remove(auth()->user(), $this->getRecord())`. In `UserResource`, replace the direct `DeleteBulkAction` callback with a loop over authorized records calling the same service. Keep policy checks and ensure a failed record does not cause unauthorized cross-tenant deletion.

Update confirmation copy to state that administrator removal blocks self-recovery and permits only a new account with the same email.

- [ ] **Step 6: Run lifecycle tests and formatter**

```bash
php artisan test --compact tests/Feature/AccountLifecycleTest.php tests/Feature/Filament/Pages/EditProfileTest.php tests/Feature/Filament/Resources/UserManagementTest.php
vendor/bin/pint --dirty --format agent
```

Expected: all affected tests PASS, including existing account closure and user-management tests.

- [ ] **Step 7: Commit the lifecycle slice**

```bash
git add blogravel/app/Services/AccountLifecycleService.php blogravel/app/Filament/Pages/Settings.php blogravel/app/Filament/Pages/Auth/EditProfile.php blogravel/app/Filament/Resources/UserResource/Pages/EditUser.php blogravel/app/Filament/Resources/UserResource.php blogravel/tests/Feature/AccountLifecycleTest.php
git commit -m "Feature: centralize account closure and administrator removal"
```

---

## Task 3: Recovery Service, Login Page, and Tenant Setup

**Files:**
- Create: `blogravel/app/Enums/AccountRecoveryResult.php`
- Create: `blogravel/app/Services/AccountRecoveryService.php`
- Create: `blogravel/app/Filament/Pages/Auth/Login.php`
- Create: `blogravel/app/Filament/Pages/Auth/RecoverAccount.php`
- Create: `blogravel/app/Filament/Pages/TenantSetup.php`
- Modify: `blogravel/app/Providers/Filament/AdminPanelProvider.php`
- Create: `blogravel/app/Notifications/AccountRecoveredNotification.php`
- Create: `blogravel/tests/Feature/AccountRecoveryTest.php`

**Interfaces:**
- `AccountRecoveryService::recover(string $email, string $password): AccountRecoveryResult`
- `AccountRecoveryResult` values: `RestoredUser`, `RestoredUserAndTenant`, `RestoredUserNeedsTenant`, `AdminRemovalBlocked`, `TenantClosureBlocked`, `Expired`, `ActiveEmailConflict`, `InvalidCredentials`.
- `TenantSetup::createTenant(string $name): void` or equivalent Livewire action that creates a tenant and assigns the authenticated tenantless admin.

- [ ] **Step 1: Write failing service tests for every authorization branch**

Test invalid credentials, active-email conflict, self-closed active tenant, self-closed recoverable tenant, expired tenant with user still recoverable, non-admin deleted tenant, admin removal, tenant closure, and super-admin recovery:

```php
it('restores a self-closed admin and tenant within both windows', function () {
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

    expect(User::find($user->id))->not->toBeNull();
    expect(Tenant::find($tenant->id))->not->toBeNull();
});

it('blocks an administrator-removed user even with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'removed@example.com',
        'password' => Hash::make('password'),
        'deletion_reason' => DeletionReason::AdminRemoved,
    ]);
    $user->delete();

    expect(app(AccountRecoveryService::class)->recover('removed@example.com', 'password'))
        ->toBe(AccountRecoveryResult::AdminRemovalBlocked);
});
```

- [ ] **Step 2: Run recovery tests to verify they fail**

```bash
php artisan test --compact tests/Feature/AccountRecoveryTest.php
```

Expected: FAIL because the service, result enum, and recovery route do not exist.

- [ ] **Step 3: Implement the recovery service**

Query `User::withTrashed()` by normalized email, order candidates by newest `deleted_at`, and validate `Hash::check($password, $user->password)`. Check an active `User::where('email', $email)->exists()` conflict before restore. Compare timestamps with `now()->subDays(30)`.

Restore inside transactions:

```php
DB::transaction(function () use ($user, $tenant): void {
    if ($tenant?->trashed()) {
        $tenant->restore();
    }

    $user->restore();
});
```

For a deleted tenant whose own deadline has passed, restore an eligible admin with `tenant_id = null` and return `RestoredUserNeedsTenant`. Do not restore a non-admin in that state. Dispatch `AccountRecoveredNotification` only after a successful restore.

- [ ] **Step 4: Add the custom Filament login link and public recovery page**

Extend `Filament\Auth\Pages\Login`, preserve its authentication/MFA implementation, and override the subheading to render a named recovery link. Register it with `->login(Login::class)`.

Implement `RecoverAccount` as an unauthenticated Filament/Livewire page registered through:

```php
->routes(function (): void {
    Route::get('/recover-account', RecoverAccount::class)
        ->name('auth.recover-account');
})
```

Use a password input, email validation, the separate strict rate limiter, and result-specific messages. Do not expose whether an email exists before password validation. Keep the page responsive using the existing Filament auth layout.

- [ ] **Step 5: Add detached-admin tenant setup**

Register an authenticated route:

```php
->authenticatedRoutes(function (): void {
    Route::get('/tenant-setup', TenantSetup::class)
        ->name('tenant-setup');
})
```

Allow only an authenticated `Role::Admin` with `tenant_id === null` and a session marker written by the recovery service. The marker contains the recovered user ID under `recovery.needs_tenant_setup` and is consumed after successful tenant creation. The form accepts tenant name only, creates `Tenant::create(['name' => $name, 'plan' => Plan::Free])`, updates the user’s `tenant_id`, and redirects to the panel. Do not use the user-created observer path because it is for new guest registrations.

- [ ] **Step 6: Test email reuse and normal MFA handoff**

Add coverage that a new active account can register with the email of an administrator-removed user, that the old account remains blocked, and that successful recovery does not call `Auth::login()`. Assert the next redirect is the normal login route. Use `Notification::fake()` for recovery notification assertions.

- [ ] **Step 7: Run affected tests and formatter**

```bash
php artisan test --compact tests/Feature/AccountRecoveryTest.php tests/Feature/FortifyHeadlessTest.php tests/Feature/Filament/Pages/EditProfileTest.php
vendor/bin/pint --dirty --format agent
```

Expected: all recovery and existing authentication tests PASS.

- [ ] **Step 8: Commit the recovery slice**

```bash
git add blogravel/app/Enums/AccountRecoveryResult.php blogravel/app/Services/AccountRecoveryService.php blogravel/app/Filament/Pages/Auth/Login.php blogravel/app/Filament/Pages/Auth/RecoverAccount.php blogravel/app/Filament/Pages/TenantSetup.php blogravel/app/Providers/Filament/AdminPanelProvider.php blogravel/app/Notifications/AccountRecoveredNotification.php blogravel/tests/Feature/AccountRecoveryTest.php
git commit -m "Feature: add self-service account recovery and tenant setup"
```

---

## Task 4: Safe Tenant Export

**Files:**
- Create: `blogravel/app/Services/DataExportService.php`
- Create: `blogravel/app/Jobs/GenerateTenantExportJob.php`
- Create: `blogravel/app/Notifications/TenantExportReadyNotification.php`
- Modify: `blogravel/app/Filament/Pages/Settings.php` to add the tenant export action
- Modify: `blogravel/app/Providers/Filament/AdminPanelProvider.php` to register the authenticated export download route
- Create: `blogravel/tests/Feature/GdprExportTest.php`

**Interfaces:**
- `DataExportService::queue(Tenant $tenant, User $requestedBy, string $format): string` returns an export identifier/path.
- `DataExportService::generate(Tenant $tenant, string $format, string $outputPath): void` writes a private ZIP.
- Export download authorization accepts only the initiating user or an authorized super admin and verifies the 24-hour expiry.

- [ ] **Step 1: Write failing export tests**

Cover tenant-admin authorization, super-admin access to a recoverable tenant, cross-tenant denial, CSV ZIP contents, XLSX workbook contents, media inclusion, soft-deleted users, and secret exclusion:

```php
it('exports safe tenant data and excludes credentials', function () {
    Storage::fake('local');
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    Post::factory()->create(['tenant_id' => $tenant->id, 'author_id' => $admin->id]);

    app(DataExportService::class)->generate($tenant, 'csv', 'exports/test.zip');

    Storage::disk('local')->assertExists('exports/test.zip');
    $archive = new ZipArchive;
    expect($archive->open(Storage::disk('local')->path('exports/test.zip')))->toBeTrue();
    expect($archive->getFromName('users.csv'))->toContain($admin->email);
    expect($archive->getFromName('users.csv'))->not->toContain('password');
    expect($archive->getFromName('users.csv'))->not->toContain('two_factor_secret');
    $archive->close();
});
```

- [ ] **Step 2: Run export tests to verify they fail**

```bash
php artisan test --compact tests/Feature/GdprExportTest.php
```

Expected: FAIL because no export service/job/page exists.

- [ ] **Step 3: Define explicit dataset allowlists**

Create one dataset definition per export file/sheet. Include safe fields for tenant metadata, users including soft-deleted rows, posts, pages, categories, tags, comments, subscribers, invitations, subscriptions/billing metadata, settings, media metadata, API/webhook metadata, and backup metadata.

Never use `toArray()` on entire models. Explicitly omit password, remember token, MFA secret/recovery fields, passkey credentials, API key values/hashes, invitation/subscriber tokens, webhook/FTP/AI secrets, access tokens, sessions, credential-bearing notifications, and backup archives. Use `withTrashed()` and `withoutGlobalScopes()` only within the service after verifying the requested tenant.

- [ ] **Step 4: Implement CSV and XLSX generation**

Use `fputcsv()` or League CSV for each CSV dataset. Use OpenSpout 4.32 for one worksheet per dataset in XLSX. Create a temporary working directory on the private `local` disk, write dataset outputs, copy media binary files from the configured public disk, and package all outputs into one ZIP via `ZipArchive`.

Use stable archive names such as `tenant.csv`, `users.csv`, `posts.csv`, `pages.csv`, `categories.csv`, `tags.csv`, `comments.csv`, `subscribers.csv`, `invitations.csv`, `subscriptions.csv`, `settings.csv`, `media.csv`, `api-keys.csv`, `webhooks.csv`, `backups.csv`, and `media-files/`.

- [ ] **Step 5: Add queued generation and private download**

Create `GenerateTenantExportJob` with the tenant ID, requester ID, format, output path, `ShouldQueue`, bounded retries, and a 24-hour expiry timestamp. The job must use `Tenant::withTrashed()` for authorized recoverable tenants and dispatch `TenantExportReadyNotification` after the ZIP exists.

Add an authenticated download endpoint that checks the requester/export ownership, super-admin authorization, expiry, and private disk path before returning `Storage::download()`. Delete the archive and temporary directory after 24 hours through the purge/cleanup command.

- [ ] **Step 6: Add the admin action and authorization**

Add the export action to Settings. Offer `csv` and `xlsx` choices, queue the job, show a Filament notification, and make export optional in the closure flow. Tenant admins may export only their current tenant. Super admins may select active or soft-deleted tenants still within the tenant window.

- [ ] **Step 7: Run export tests and formatter**

```bash
php artisan test --compact tests/Feature/GdprExportTest.php tests/Feature/Filament/Pages/SettingsTest.php
vendor/bin/pint --dirty --format agent
```

Expected: all export tests PASS, including archive secret scans and cross-tenant authorization.

- [ ] **Step 8: Commit the export slice**

```bash
git add blogravel/app/Services/DataExportService.php blogravel/app/Jobs/GenerateTenantExportJob.php blogravel/app/Notifications/TenantExportReadyNotification.php blogravel/app/Filament/Pages/Settings.php blogravel/app/Providers/Filament/AdminPanelProvider.php blogravel/tests/Feature/GdprExportTest.php
git commit -m "Feature: add safe queued tenant data exports"
```

---

## Task 5: Retryable GDPR Purge and Scheduler

**Files:**
- Create: `blogravel/app/Services/RetentionPurgeService.php`
- Create: `blogravel/app/Console/Commands/PurgeExpiredDeletedDataCommand.php`
- Modify: `blogravel/routes/console.php`
- Modify: `blogravel/compose.yaml`
- Create: `blogravel/tests/Feature/GdprPurgeTest.php`

**Interfaces:**
- `RetentionPurgeService::purgeDueTenants(CarbonImmutable $now): int`
- `RetentionPurgeService::purgeDueUsers(CarbonImmutable $now): int`
- `RetentionPurgeService::purgeTenant(Tenant $tenant): void`
- `RetentionPurgeService::purgeUser(User $user): void`
- Artisan command name: `gdpr:purge-expired`.

- [ ] **Step 1: Write failing purge tests**

Cover due/not-due records, tenant content deletion, media and backup file removal, user detachment, detached-user retention, individual user force deletion, authored-post cascade, token/session/notification cleanup, and retry behavior:

```php
it('detaches users when purging a due tenant and retains users past the tenant boundary', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create(['role' => Role::Author]);
    $tenant->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

    app(RetentionPurgeService::class)->purgeDueTenants(now());

    expect(Tenant::withTrashed()->find($tenant->id))->toBeNull();
    $detached = User::withTrashed()->findOrFail($user->id);
    expect($detached->tenant_id)->toBeNull()
        ->and($detached->deletion_reason)->toBe(DeletionReason::TenantClosed);
});

it('does not force-delete a user before its own deadline', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'deleted_at' => now()->subDays(29),
        'deletion_reason' => DeletionReason::TenantClosed,
    ])->saveQuietly();

    app(RetentionPurgeService::class)->purgeDueUsers(now());

    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});
```

- [ ] **Step 2: Run purge tests to verify they fail**

```bash
php artisan test --compact tests/Feature/GdprPurgeTest.php
```

Expected: FAIL because no purge service or command exists.

- [ ] **Step 3: Implement tenant purge ordering**

For a tenant with `deleted_at <= now()->subDays(30)`, use idempotent cleanup in this order:

1. Load all attached users with `withTrashed()` and all media/backups with tenant scope disabled.
2. Delete media files from the configured public disk and backup files from each recorded disk/path; tolerate missing files.
3. Delete export archives for the tenant, personal access tokens, sessions, notifications, and known queued references that contain the tenant/user identifiers.
4. For users that are not already soft-deleted, set `deleted_at = now()`, `deletion_reason = TenantClosed`, and `deleted_by = null`; for all users set `tenant_id = null` without triggering the tenant global scope.
5. Force-delete the tenant so tenant foreign-key cascades remove tenant-owned records.

Do not force-delete detached users in the tenant operation. If file or queued-reference cleanup throws, stop before force-deleting the tenant so the next daily run can retry safely.

- [ ] **Step 4: Implement user purge ordering**

For a soft-deleted user with `deleted_at <= now()->subDays(30)`, delete personal access tokens, sessions, database notifications, password reset tokens, and passkeys explicitly. Then call `forceDelete()` so authored posts follow the existing `ON DELETE CASCADE` relationship. Skip users whose tenant is still active only after confirming their own deadline has elapsed.

- [ ] **Step 5: Add the command and daily schedule**

Create the `gdpr:purge-expired` command to invoke both service methods, print counts, and return a non-zero exit code when a retryable purge fails. Register it in `routes/console.php`:

```php
Schedule::command('gdpr:purge-expired')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();
```

- [ ] **Step 6: Add the Compose scheduler runner**

Add a `scheduler` service using the existing `sail-8.5/app` image, project volume, network, and database/Redis dependencies. Run:

```bash
while true; do php artisan schedule:run --no-interaction; sleep 60; done
```

Keep the existing `queue` service unchanged except for shared dependency declarations if Compose requires them.

- [ ] **Step 7: Test retry behavior and schedule registration**

Fake storage and force one deletion failure. Assert the tenant/user remains present and the next invocation succeeds after the failure is removed. Assert `Schedule::registered()` contains `gdpr:purge-expired` with daily frequency and non-overlap. Run:

```bash
php artisan test --compact tests/Feature/GdprPurgeTest.php
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 8: Commit the purge slice**

```bash
git add blogravel/app/Services/RetentionPurgeService.php blogravel/app/Console/Commands/PurgeExpiredDeletedDataCommand.php blogravel/routes/console.php blogravel/compose.yaml blogravel/tests/Feature/GdprPurgeTest.php
git commit -m "Feature: add retryable GDPR retention purge"
```

---

## Task 6: Browser Verification and Full Integration Tests

**Files:**
- Create: `blogravel/tests/e2e/account-recovery.spec.ts`
- Create: `blogravel/tests/e2e/gdpr-export.spec.ts`
- Modify: `blogravel/tests/e2e/helpers.ts` only for reusable authenticated-role setup
- Keep: `blogravel/tests/e2e/login.setup.ts` unchanged; create test users through the feature fixtures or existing helpers.
- Modify: focused feature tests from Tasks 2–5 when browser-discovered defects require it

**Interfaces:**
- Browser tests use existing Playwright helpers and the current local login setup.
- No changes to unrelated theme tests.

- [ ] **Step 1: Add recovery browser coverage**

Test desktop and mobile viewport flows:

```ts
test('shows account recovery from the login page', async ({ page }) => {
  await page.goto('/admin/login');
  await expect(page.getByRole('link', { name: /recover account/i })).toBeVisible();
  await page.getByRole('link', { name: /recover account/i }).click();
  await expect(page.getByRole('heading', { name: /recover account/i })).toBeVisible();
});
```

Add tests for successful recovery, invalid credentials, administrator-removal denial, deleted-tenant message, and tenant setup redirect.

- [ ] **Step 2: Add export browser coverage**

Authenticate as tenant admin and super admin. Assert the format selector, queued notification, download authorization, and responsive layout. Assert a tenant admin cannot select another tenant and a super admin can select a recoverable tenant.

- [ ] **Step 3: Run Playwright tests**

Start the application using the project’s existing development command, then run:

```bash
npx playwright test tests/e2e/account-recovery.spec.ts tests/e2e/gdpr-export.spec.ts
```

Expected: all new browser tests PASS at desktop and mobile viewport sizes.

- [ ] **Step 4: Run the affected backend suite and frontend checks**

```bash
php artisan test --compact tests/Feature/AccountDeletionSchemaTest.php tests/Feature/AccountLifecycleTest.php tests/Feature/AccountRecoveryTest.php tests/Feature/GdprExportTest.php tests/Feature/GdprPurgeTest.php tests/Feature/FortifyHeadlessTest.php tests/Feature/Filament/Pages/EditProfileTest.php tests/Feature/Filament/Pages/SettingsTest.php tests/Feature/Filament/Resources/UserManagementTest.php
npm run build
vendor/bin/pint --dirty --format agent
```

Expected: all affected Pest tests, the build, and Pint PASS.

- [ ] **Step 5: Commit browser/integration coverage**

```bash
git add blogravel/tests/e2e/account-recovery.spec.ts blogravel/tests/e2e/gdpr-export.spec.ts blogravel/tests/e2e/helpers.ts blogravel/tests/e2e/login.setup.ts
git commit -m "Tests: verify account recovery export and purge flows"
```

---

## Task 7: Ticket Synchronization and Final Verification

**Files:**
- Modify: `tickets.md`
- Modify: GitHub issue/project #44 through `gh`

- [ ] **Step 1: Inspect the complete diff and worktree**

Run:

```bash
git status --short
git diff --check
git log --oneline -10
```

Do not stage or modify the unrelated existing theme/e2e changes.

- [ ] **Step 2: Run the full test command**

```bash
php artisan test --compact
```

Expected: the complete suite passes. If the environment cannot run the full suite, record the exact failure and still run every affected test file individually.

- [ ] **Step 3: Verify the scheduler and route surfaces**

```bash
php artisan schedule:list
php artisan route:list --path=admin
php artisan route:list --path=api/v1/login
```

Confirm the daily purge schedule, recovery route, tenant setup route, export download route, and unchanged API login route.

- [ ] **Step 4: Synchronize ticket status**

Update `tickets.md` from `Todo` to `Done` for #44. Use `gh` to close issue #44 and move it to the GitHub project’s `Done` status. Verify the local row and remote issue/project agree.

- [ ] **Step 5: Commit ticket synchronization**

```bash
git add tickets.md
git commit -m "Docs: sync ticket 44 status"
```

- [ ] **Step 6: Report evidence**

Report the final commit hashes, affected Pest command result, Playwright result, build result, scheduler/route verification, and any environmental limitation without claiming success for an unverified command.
