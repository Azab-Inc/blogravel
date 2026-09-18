<?php

use App\Enums\Role;
use App\Filament\Pages\Settings;
use App\Jobs\GenerateTenantExportJob;
use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\Backup;
use App\Models\BackupRule;
use App\Models\Invitation;
use App\Models\Media;
use App\Models\OutboundWebhook;
use App\Models\Post;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Webhook;
use App\Notifications\TenantExportReadyNotification;
use App\Services\DataExportService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

it('exports safe tenant data and excludes credentials', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create([
        'role' => Role::Admin,
        'password' => 'secret-password',
        'two_factor_secret' => encrypt('mfa-secret'),
        'app_authentication_recovery_codes' => encrypt(json_encode(['recovery-code'])),
    ]);
    $deletedUser = User::factory()->forTenant($tenant)->create([
        'email' => 'deleted@example.com',
    ]);
    $deletedUser->delete();
    Post::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $admin->id,
        'title' => 'Exported post',
    ]);
    Media::factory()->create([
        'tenant_id' => $tenant->id,
        'file_path' => 'media/exported.txt',
    ]);
    Storage::disk('public')->put('media/exported.txt', 'media-content');

    app(DataExportService::class)->generate($tenant, 'csv', 'exports/test.zip');

    Storage::disk('local')->assertExists('exports/test.zip');
    $archive = new ZipArchive;
    expect($archive->open(Storage::disk('local')->path('exports/test.zip')))->toBeTrue();
    expect($archive->getFromName('tenant.csv'))->toContain($tenant->name)
        ->and($archive->getFromName('users.csv'))->toContain($admin->email)
        ->and($archive->getFromName('users.csv'))->toContain($deletedUser->email)
        ->and($archive->getFromName('posts.csv'))->toContain('Exported post')
        ->and($archive->getFromName('media.csv'))->toContain('media/exported.txt')
        ->and($archive->getFromName('media-files/media/exported.txt'))->toBe('media-content')
        ->and($archive->getFromName('users.csv'))->not->toContain('password')
        ->and($archive->getFromName('users.csv'))->not->toContain('secret-password')
        ->and($archive->getFromName('users.csv'))->not->toContain('two_factor_secret')
        ->and($archive->getFromName('users.csv'))->not->toContain('mfa-secret')
        ->and($archive->getFromName('users.csv'))->not->toContain('recovery-code');
    $archive->close();
    Storage::disk('public')->delete('media/exported.txt');
});

it('writes one OpenSpout worksheet per allowlisted dataset', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    Post::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $admin->id,
        'title' => 'Workbook post',
    ]);

    app(DataExportService::class)->generate($tenant, 'xlsx', 'exports/test.xlsx');

    $archive = new ZipArchive;
    expect($archive->open(Storage::disk('local')->path('exports/test.xlsx')))->toBeTrue();
    $workbookPath = tempnam(sys_get_temp_dir(), 'tenant-export-');
    file_put_contents($workbookPath, $archive->getFromName('tenant-export.xlsx'));
    $archive->close();

    $reader = new Reader;
    $reader->open($workbookPath);
    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
        $sheets[$sheet->getName()] = $rows;
    }
    $reader->close();
    unlink($workbookPath);

    expect(array_keys($sheets))->toContain('tenant', 'users', 'posts', 'media', 'backups')
        ->and($sheets['posts'])->toContain(['id', 'tenant_id', 'author_id', 'title', 'slug', 'content', 'excerpt', 'status', 'published_at', 'created_at', 'updated_at'])
        ->and(collect($sheets['posts'])->flatten())->toContain('Workbook post');
});

it('excludes credential-bearing records and backup archive contents from the ZIP', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    ApiKey::factory()->create([
        'tenant_id' => $tenant->id,
        'token' => 'api-token-secret',
        'key_hash' => hash('sha256', 'api-token-secret'),
    ]);
    Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'token' => 'invitation-token-secret',
    ]);
    Subscriber::factory()->create([
        'tenant_id' => $tenant->id,
        'confirmation_token' => 'confirmation-token-secret',
        'unsubscribe_token' => 'unsubscribe-token-secret',
    ]);
    Webhook::factory()->create([
        'tenant_id' => $tenant->id,
        'secret' => 'webhook-secret',
    ]);
    OutboundWebhook::create([
        'tenant_id' => $tenant->id,
        'url' => 'https://example.com/webhook',
        'events' => ['post.published'],
        'secret' => 'outbound-webhook-secret',
        'is_active' => true,
    ]);
    AiProvider::factory()->create([
        'tenant_id' => $tenant->id,
        'api_key' => 'ai-provider-secret',
    ]);
    $backupRule = BackupRule::factory()->create([
        'tenant_id' => $tenant->id,
        'ftp_pass' => 'ftp-password-secret',
    ]);
    Backup::factory()->create([
        'tenant_id' => $tenant->id,
        'backup_rule_id' => $backupRule->id,
        'filename' => 'database-backup.tar.gz',
        'path' => 'backups/database-backup.tar.gz',
    ]);
    Storage::disk('local')->put('backups/database-backup.tar.gz', 'backup-archive-content-secret');
    DB::table('passkeys')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $admin->id,
        'name' => 'Laptop',
        'credential_id' => 'passkey-credential-id',
        'credential' => json_encode(['secret' => 'passkey-credential-secret']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(DataExportService::class)->generate($tenant, 'csv', 'exports/secrets.zip');

    $archive = new ZipArchive;
    expect($archive->open(Storage::disk('local')->path('exports/secrets.zip')))->toBeTrue();
    $archiveContents = '';
    for ($index = 0; $index < $archive->numFiles; $index++) {
        $archiveContents .= (string) $archive->getFromIndex($index);
    }
    $archive->close();

    expect($archiveContents)
        ->not->toContain('api-token-secret')
        ->not->toContain(hash('sha256', 'api-token-secret'))
        ->not->toContain('invitation-token-secret')
        ->not->toContain('confirmation-token-secret')
        ->not->toContain('unsubscribe-token-secret')
        ->not->toContain('webhook-secret')
        ->not->toContain('outbound-webhook-secret')
        ->not->toContain('ai-provider-secret')
        ->not->toContain('ftp-password-secret')
        ->not->toContain('passkey-credential-id')
        ->not->toContain('passkey-credential-secret')
        ->not->toContain('backup-archive-content-secret');
});

it('exports only the requested tenant records and media', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    $otherAdmin = User::factory()->forTenant($otherTenant)->create(['role' => Role::Admin]);
    Post::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $admin->id,
        'title' => 'Tenant A post',
    ]);
    Post::factory()->create([
        'tenant_id' => $otherTenant->id,
        'author_id' => $otherAdmin->id,
        'title' => 'Tenant B post',
    ]);
    Media::factory()->create([
        'tenant_id' => $tenant->id,
        'file_path' => 'media/tenant-a.txt',
    ]);
    Media::factory()->create([
        'tenant_id' => $otherTenant->id,
        'file_path' => 'media/tenant-b.txt',
    ]);
    Storage::disk('public')->put('media/tenant-a.txt', 'tenant-a-media');
    Storage::disk('public')->put('media/tenant-b.txt', 'tenant-b-media');

    app(DataExportService::class)->generate($tenant, 'csv', 'exports/tenant-a.zip');

    $archive = new ZipArchive;
    expect($archive->open(Storage::disk('local')->path('exports/tenant-a.zip')))->toBeTrue();
    expect($archive->getFromName('posts.csv'))->toContain('Tenant A post')->not->toContain('Tenant B post');
    expect($archive->getFromName('media.csv'))->toContain('media/tenant-a.txt')->not->toContain('media/tenant-b.txt');
    expect($archive->getFromName('media-files/media/tenant-a.txt'))->toBe('tenant-a-media');
    expect($archive->getFromName('media-files/media/tenant-b.txt'))->toBeFalse();
    $archive->close();
    Storage::disk('public')->delete(['media/tenant-a.txt', 'media/tenant-b.txt']);
});

it('queues an export job with an expiring private manifest', function () {
    Bus::fake();

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);

    $identifier = app(DataExportService::class)->queue($tenant, $admin, 'csv');

    Bus::assertDispatched(GenerateTenantExportJob::class, function (GenerateTenantExportJob $job) use ($tenant, $admin, $identifier): bool {
        return $job->tenantId === $tenant->id
            && $job->requestedById === $admin->id
            && $job->format === 'csv'
            && $job->outputPath === 'exports/'.$identifier.'.zip'
            && $job->expiresAt->between(now()->addHours(23), now()->addHours(25));
    });
});

it('generates a queued export for a recoverable soft-deleted tenant and notifies the requester', function () {
    Storage::fake('local');
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    $tenant->delete();
    $job = new GenerateTenantExportJob(
        $tenant->id,
        $admin->id,
        'csv',
        'exports/recoverable.zip',
        now()->addDay(),
    );

    $job->handle(app(DataExportService::class));

    Storage::disk('local')->assertExists('exports/recoverable.zip');
    Notification::assertSentTo($admin, TenantExportReadyNotification::class);
});

it('does not generate a queued export after an administrator tenant recovery window expires', function () {
    Storage::fake('local');
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    $tenant->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();
    $job = new GenerateTenantExportJob(
        $tenant->id,
        $admin->id,
        'csv',
        'exports/expired-admin-tenant.zip',
        now()->addDay(),
    );

    $job->handle(app(DataExportService::class));

    Storage::disk('local')->assertMissing('exports/expired-admin-tenant.zip');
    Notification::assertNothingSent();
});

it('does not generate a queued export after the tenant is purged', function () {
    Storage::fake('local');
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $tenant->forceDelete();
    $job = new GenerateTenantExportJob(
        $tenant->id,
        $superAdmin->id,
        'csv',
        'exports/purged-tenant.zip',
        now()->addDay(),
    );

    $job->handle(app(DataExportService::class));

    Storage::disk('local')->assertMissing('exports/purged-tenant.zip');
    Notification::assertNothingSent();
});

it('does not generate a queued export for a tenant outside the requester association', function () {
    Storage::fake('local');
    Notification::fake();

    $requesterTenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($requesterTenant)->create(['role' => Role::Admin]);
    $job = new GenerateTenantExportJob(
        $otherTenant->id,
        $admin->id,
        'csv',
        'exports/other-tenant.zip',
        now()->addDay(),
    );

    $job->handle(app(DataExportService::class));

    Storage::disk('local')->assertMissing('exports/other-tenant.zip');
    Notification::assertNothingSent();
});

it('allows only the initiating user or a super admin to download an unexpired export', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    $otherTenant = Tenant::factory()->create();
    $otherAdmin = User::factory()->forTenant($otherTenant)->create(['role' => Role::Admin]);
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $identifier = app(DataExportService::class)->queue($tenant, $admin, 'csv');

    $this->actingAs($admin)
        ->get('/admin/tenant-exports/'.$identifier)
        ->assertOk();

    $this->actingAs($otherAdmin)
        ->get('/admin/tenant-exports/'.$identifier)
        ->assertForbidden();

    $this->actingAs($superAdmin)
        ->get('/admin/tenant-exports/'.$identifier)
        ->assertOk();
});

it('denies downloads after the 24-hour expiry and removes the archive', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    $identifier = app(DataExportService::class)->queue($tenant, $admin, 'csv');

    $this->travel(25)->hours();

    $this->actingAs($admin)
        ->get('/admin/tenant-exports/'.$identifier)
        ->assertStatus(410);

    Storage::disk('local')->assertMissing('exports/'.$identifier.'.zip');
});

it('purges expired export archives through the retention command', function () {
    Storage::fake('local');
    Bus::fake();

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    $identifier = app(DataExportService::class)->queue($tenant, $admin, 'csv');
    Storage::disk('local')->put('exports/'.$identifier.'.zip', 'expired archive');
    $manifest = json_decode(Storage::disk('local')->get('exports/'.$identifier.'.json'), true);
    $manifest['expires_at'] = now()->subMinute()->toIso8601String();
    Storage::disk('local')->put('exports/'.$identifier.'.json', json_encode($manifest));

    $this->artisan('gdpr:purge-expired')
        ->assertSuccessful();

    Storage::disk('local')->assertMissing('exports/'.$identifier.'.zip');
    Storage::disk('local')->assertMissing('exports/'.$identifier.'.json');
});

it('registers one daily non-overlapping GDPR cleanup schedule', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'gdpr:purge-expired'));

    expect($events)->toHaveCount(1);
    $event = $events->first();
    expect($event->getExpression())->toBe('0 0 * * *');
});

it('lets an admin queue a current-tenant export from Settings and optionally exports before closure', function () {
    Bus::fake();

    $tenant = Tenant::factory()->create(['name' => 'Acme']);
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    Livewire::test(Settings::class)
        ->callAction(
            TestAction::make('exportTenantData')->schemaComponent(true),
            ['format' => 'csv'],
        )
        ->assertHasNoErrors();

    Bus::assertDispatched(GenerateTenantExportJob::class);

    Livewire::test(Settings::class)
        ->callAction(
            TestAction::make('closeAccount')->schemaComponent(true),
            ['tenant_confirmation' => 'Acme', 'export_format' => 'none'],
        )
        ->assertHasNoErrors();
});

it('offers the optional export choice when a non-last administrator closes their account', function () {
    Bus::fake();

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    Livewire::test(Settings::class)
        ->callAction(
            TestAction::make('closeAccount')->schemaComponent(true),
            ['export_format' => 'none'],
        )
        ->assertHasNoErrors();

    $this->assertSoftDeleted('users', ['id' => $admin->id]);
    $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'deleted_at' => null]);
});

it('lets a super admin export a recoverable soft-deleted tenant but not an unrelated tenant as an admin', function () {
    Bus::fake();

    $recoverableTenant = Tenant::factory()->create(['name' => 'Recoverable']);
    $otherTenant = Tenant::factory()->create(['name' => 'Other']);
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $recoverableTenant->delete();
    $this->actingAs($superAdmin);

    Livewire::test(Settings::class)
        ->callAction(
            TestAction::make('exportTenantData')->schemaComponent(true),
            ['tenant_id' => $recoverableTenant->id, 'format' => 'xlsx'],
        )
        ->assertHasNoErrors();

    Bus::assertDispatched(GenerateTenantExportJob::class, function (GenerateTenantExportJob $job) use ($recoverableTenant): bool {
        return $job->tenantId === $recoverableTenant->id && $job->format === 'xlsx';
    });

    $admin = User::factory()->forTenant($otherTenant)->create(['role' => Role::Admin]);
    expect(fn () => app(DataExportService::class)->queue($recoverableTenant, $admin, 'csv'))
        ->toThrow(AuthorizationException::class);
});
