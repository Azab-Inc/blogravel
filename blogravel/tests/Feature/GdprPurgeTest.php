<?php

use App\Enums\DeletionReason;
use App\Enums\Role;
use App\Models\Backup;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RetentionPurgeService;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('detaches users when purging a due tenant and retains users past the tenant boundary', function () {
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = ON');
    }

    $publicRoot = sys_get_temp_dir().'/blogravel-gdpr-public-'.Str::uuid();
    mkdir($publicRoot, 0777, true);
    Storage::set('public', Storage::build(['driver' => 'local', 'root' => $publicRoot]));
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create(['role' => Role::Author]);
    $media = Media::factory()->create([
        'tenant_id' => $tenant->id,
        'file_path' => 'media/tenant-file.txt',
    ]);
    $backup = Backup::factory()->create([
        'tenant_id' => $tenant->id,
        'path' => 'backups/tenant-backup.tar.gz',
        'disk' => 'local',
    ]);
    Post::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $user->id,
    ]);
    Storage::disk('public')->put($media->file_path, 'media');
    Storage::disk('local')->put($backup->path, 'backup');
    Storage::disk('local')->put('exports/tenant.zip', 'export');
    Storage::disk('local')->put('exports/tenant.json', json_encode([
        'tenant_id' => $tenant->id,
        'output_path' => 'exports/tenant.zip',
    ], JSON_THROW_ON_ERROR));

    DB::table('personal_access_tokens')->insert([
        'tokenable_type' => User::class,
        'tokenable_id' => $user->id,
        'name' => 'tenant-token',
        'token' => hash('sha256', 'tenant-token'),
        'abilities' => '["*"]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('sessions')->insert([
        'id' => 'tenant-session',
        'user_id' => $user->id,
        'payload' => 'payload',
        'last_activity' => now()->getTimestamp(),
    ]);
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'tenant-notification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['tenant_id' => $tenant->id, 'user_id' => $user->id], JSON_THROW_ON_ERROR),
        'attempts' => 0,
        'available_at' => now()->getTimestamp(),
        'created_at' => now()->getTimestamp(),
    ]);

    $tenant->forceFill(['deleted_at' => now()->subDays(30)])->saveQuietly();

    expect(app(RetentionPurgeService::class)->purgeDueTenants(now()))->toBe(1);
    expect(Tenant::withTrashed()->find($tenant->id))->toBeNull();
    if (DB::getDriverName() !== 'sqlite') {
        expect(Media::withoutGlobalScopes()->find($media->id))->toBeNull();
        expect(Backup::withoutGlobalScopes()->find($backup->id))->toBeNull();
        expect(Post::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists())->toBeFalse();
    }
    expect(Storage::disk('public')->exists($media->file_path))->toBeFalse();
    expect(Storage::disk('local')->exists($backup->path))->toBeFalse();
    expect(Storage::disk('local')->exists('exports/tenant.zip'))->toBeFalse();
    expect(Storage::disk('local')->exists('exports/tenant.json'))->toBeFalse();
    expect(DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->exists())->toBeFalse();
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
    expect(DB::table('notifications')->where('notifiable_id', $user->id)->exists())->toBeFalse();
    expect(DB::table('jobs')->where('payload', 'like', '%'.$tenant->id.'%')->exists())->toBeFalse();

    $detached = User::withoutGlobalScopes()->withTrashed()->findOrFail($user->id);
    expect($detached->tenant_id)->toBeNull()
        ->and($detached->deletion_reason)->toBe(DeletionReason::TenantClosed)
        ->and($detached->deleted_at)->not->toBeNull();
});

it('does not purge a tenant before its retention deadline', function () {
    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['deleted_at' => now()->subDays(29)])->saveQuietly();

    expect(app(RetentionPurgeService::class)->purgeDueTenants(now()))->toBe(0);
    expect(Tenant::withTrashed()->find($tenant->id))->not->toBeNull();
});

it('keeps a tenant pending when a matching export manifest has no safe output path', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['deleted_at' => now()->subDays(30)])->saveQuietly();
    Storage::disk('local')->put('exports/pending.zip', 'export');
    Storage::disk('local')->put('exports/pending.json', json_encode([
        'tenant_id' => $tenant->id,
    ], JSON_THROW_ON_ERROR));

    expect(fn () => app(RetentionPurgeService::class)->purgeDueTenants(now()))
        ->toThrow(RuntimeException::class);
    expect(Tenant::withTrashed()->find($tenant->id))->not->toBeNull();
    Storage::disk('local')->assertExists('exports/pending.zip');

    Storage::disk('local')->put('exports/pending.json', json_encode([
        'tenant_id' => $tenant->id,
        'output_path' => 'exports/pending.zip',
    ], JSON_THROW_ON_ERROR));

    expect(app(RetentionPurgeService::class)->purgeDueTenants(now()))->toBe(1);
    expect(Tenant::withTrashed()->find($tenant->id))->toBeNull();
    Storage::disk('local')->assertMissing('exports/pending.zip');
});

it('keeps a tenant pending when a matching export manifest has an unsafe output path', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['deleted_at' => now()->subDays(30)])->saveQuietly();
    Storage::disk('local')->put('exports/pending.zip', 'export');
    Storage::disk('local')->put('exports/pending.json', json_encode([
        'tenant_id' => $tenant->id,
        'output_path' => '../pending.zip',
    ], JSON_THROW_ON_ERROR));

    expect(fn () => app(RetentionPurgeService::class)->purgeDueTenants(now()))
        ->toThrow(InvalidArgumentException::class);
    expect(Tenant::withTrashed()->find($tenant->id))->not->toBeNull();
    Storage::disk('local')->assertExists('exports/pending.zip');
});

it('does not force-delete a user before its own deadline', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'deleted_at' => now()->subDays(29),
        'deletion_reason' => DeletionReason::TenantClosed,
    ])->saveQuietly();

    expect(app(RetentionPurgeService::class)->purgeDueUsers(now()))->toBe(0);
    expect(User::withoutGlobalScopes()->withTrashed()->find($user->id))->not->toBeNull();
});

it('force-deletes due users after cleaning credentials and authored posts', function () {
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = ON');
    }

    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $post = Post::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $user->id,
    ]);

    DB::table('personal_access_tokens')->insert([
        'tokenable_type' => User::class,
        'tokenable_id' => $user->id,
        'name' => 'user-token',
        'token' => hash('sha256', 'user-token'),
        'abilities' => '["*"]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('sessions')->insert([
        'id' => 'user-session',
        'user_id' => $user->id,
        'payload' => 'payload',
        'last_activity' => now()->getTimestamp(),
    ]);
    DB::table('password_reset_tokens')->insert([
        'email' => $user->email,
        'token' => 'reset-token',
        'created_at' => now(),
    ]);
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'user-notification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('passkeys')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Laptop',
        'credential_id' => 'user-credential',
        'credential' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['user_id' => $user->id], JSON_THROW_ON_ERROR),
        'attempts' => 0,
        'available_at' => now()->getTimestamp(),
        'created_at' => now()->getTimestamp(),
    ]);

    $user->forceFill([
        'deleted_at' => now()->subDays(30),
        'deletion_reason' => DeletionReason::SelfClosed,
    ])->saveQuietly();

    expect(app(RetentionPurgeService::class)->purgeDueUsers(now()))->toBe(1);
    expect(User::withoutGlobalScopes()->withTrashed()->find($user->id))->toBeNull();
    if (DB::getDriverName() !== 'sqlite') {
        expect(Post::withoutGlobalScopes()->find($post->id))->toBeNull();
    }
    expect(DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->exists())->toBeFalse();
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
    expect(DB::table('notifications')->where('notifiable_id', $user->id)->exists())->toBeFalse();
    expect(DB::table('passkeys')->where('user_id', $user->id)->exists())->toBeFalse();
    expect(DB::table('jobs')->where('payload', 'like', '%'.$user->id.'%')->exists())->toBeFalse();
});

it('reports purge counts through the existing GDPR command', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['deleted_at' => now()->subDays(30)])->saveQuietly();
    $user = User::factory()->create();
    $user->forceFill([
        'deleted_at' => now()->subDays(30),
        'deletion_reason' => DeletionReason::SelfClosed,
    ])->saveQuietly();

    $this->artisan('gdpr:purge-expired')
        ->expectsOutputToContain('Tenants purged: 1')
        ->expectsOutputToContain('Users purged: 1')
        ->assertSuccessful();
});

it('returns a retryable command status when a purge phase fails', function () {
    $retention = Mockery::mock(RetentionPurgeService::class);
    $retention->shouldReceive('purgeDueTenants')->once()->andThrow(new RuntimeException('temporary failure'));
    $retention->shouldReceive('purgeDueUsers')->once()->andReturn(0);
    app()->instance(RetentionPurgeService::class, $retention);

    $this->artisan('gdpr:purge-expired')
        ->expectsOutputToContain('Tenant purge requires retry: temporary failure')
        ->assertExitCode(Command::FAILURE);
});

it('retries a tenant after a file cleanup failure without losing the tenant', function () {
    $publicRoot = sys_get_temp_dir().'/blogravel-gdpr-retry-'.Str::uuid();
    mkdir($publicRoot, 0777, true);
    $realDisk = Storage::build(['driver' => 'local', 'root' => $publicRoot]);
    $tenant = Tenant::factory()->create();
    $media = Media::factory()->create([
        'tenant_id' => $tenant->id,
        'file_path' => 'media/retry.txt',
    ]);
    $realDisk->put($media->file_path, 'media');
    $tenant->forceFill(['deleted_at' => now()->subDays(30)])->saveQuietly();

    $failingDisk = Mockery::mock(Filesystem::class);
    $failingDisk->shouldReceive('exists')->with($media->file_path)->once()->andReturnTrue();
    $failingDisk->shouldReceive('delete')->with($media->file_path)->once()->andThrow(new RuntimeException('temporary file failure'));
    Storage::set('public', $failingDisk);
    Storage::fake('local');

    expect(fn () => app(RetentionPurgeService::class)->purgeDueTenants(now()))
        ->toThrow(RuntimeException::class, 'temporary file failure');
    expect(Tenant::withTrashed()->find($tenant->id))->not->toBeNull();

    Storage::set('public', $realDisk);

    expect(app(RetentionPurgeService::class)->purgeDueTenants(now()))->toBe(1);
    expect(Tenant::withTrashed()->find($tenant->id))->toBeNull();
    expect($realDisk->exists($media->file_path))->toBeFalse();
});

it('retries user purge after a user-reference cleanup failure', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $user->forceFill([
        'deleted_at' => now()->subDays(30),
        'deletion_reason' => DeletionReason::SelfClosed,
    ])->saveQuietly();

    DB::table('personal_access_tokens')->insert([
        'tokenable_type' => User::class,
        'tokenable_id' => $user->id,
        'name' => 'retry-token',
        'token' => hash('sha256', 'retry-token'),
        'abilities' => '["*"]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if (DB::getDriverName() === 'sqlite') {
        DB::statement('CREATE TRIGGER fail_user_reference_delete BEFORE DELETE ON personal_access_tokens BEGIN SELECT RAISE(IGNORE); END');
    } elseif (DB::getDriverName() === 'pgsql') {
        DB::statement('CREATE FUNCTION fail_user_reference_delete() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RETURN NULL; END; $$');
        DB::statement('CREATE TRIGGER fail_user_reference_delete BEFORE DELETE ON personal_access_tokens FOR EACH ROW EXECUTE FUNCTION fail_user_reference_delete()');
    } else {
        test()->markTestSkipped('The active database cannot create the reference-failure trigger.');
    }

    try {
        expect(fn () => app(RetentionPurgeService::class)->purgeDueUsers(now()))
            ->toThrow(RuntimeException::class, 'user personal access tokens');
    } finally {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER fail_user_reference_delete');
        } else {
            DB::statement('DROP TRIGGER fail_user_reference_delete ON personal_access_tokens');
            DB::statement('DROP FUNCTION fail_user_reference_delete()');
        }
    }

    expect(User::withoutGlobalScopes()->withTrashed()->find($user->id))->not->toBeNull();
    expect(Tenant::withTrashed()->find($tenant->id))->not->toBeNull();

    expect(app(RetentionPurgeService::class)->purgeDueUsers(now()))->toBe(1);
    expect(User::withoutGlobalScopes()->withTrashed()->find($user->id))->toBeNull();
    expect(Tenant::withTrashed()->find($tenant->id))->not->toBeNull();
});

it('uses PostgreSQL cascades for tenant content', function () {
    if (DB::getDriverName() !== 'pgsql') {
        test()->markTestSkipped('PostgreSQL is not the active test database.');
    }

    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $page = Page::factory()->create(['tenant_id' => $tenant->id]);
    $media = Media::factory()->create(['tenant_id' => $tenant->id]);
    $post = Post::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $user->id,
    ]);
    $tenant->forceFill(['deleted_at' => now()->subDays(30)])->saveQuietly();

    expect(app(RetentionPurgeService::class)->purgeDueTenants(now()))->toBe(1);
    expect(Tenant::withTrashed()->find($tenant->id))->toBeNull()
        ->and(Page::withoutGlobalScopes()->find($page->id))->toBeNull()
        ->and(Media::withoutGlobalScopes()->find($media->id))->toBeNull()
        ->and(Post::withoutGlobalScopes()->find($post->id))->toBeNull();
})->group('postgres');

it('uses PostgreSQL cascades for authored posts during user purge', function () {
    if (DB::getDriverName() !== 'pgsql') {
        test()->markTestSkipped('PostgreSQL is not the active test database.');
    }

    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $post = Post::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $user->id,
    ]);
    $user->forceFill([
        'deleted_at' => now()->subDays(30),
        'deletion_reason' => DeletionReason::SelfClosed,
    ])->saveQuietly();

    expect(app(RetentionPurgeService::class)->purgeDueUsers(now()))->toBe(1);
    expect(User::withoutGlobalScopes()->withTrashed()->find($user->id))->toBeNull()
        ->and(Post::withoutGlobalScopes()->find($post->id))->toBeNull();
})->group('postgres');

it('registers one daily non-overlapping GDPR cleanup schedule', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'gdpr:purge-expired'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->getExpression())->toBe('0 0 * * *')
        ->and($events->first()->withoutOverlapping)->toBeTrue()
        ->and($events->first()->onOneServer)->toBeTrue();
});
