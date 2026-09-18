<?php

use App\Enums\Role;
use App\Filament\Pages\Settings;
use App\Jobs\GenerateTenantExportJob;
use App\Models\Media;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantExportReadyNotification;
use App\Services\DataExportService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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
