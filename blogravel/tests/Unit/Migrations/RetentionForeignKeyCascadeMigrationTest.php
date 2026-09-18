<?php

use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

it('quarantines orphaned cascade rows before installing foreign keys', function () {
    Artisan::call('migrate:fresh', ['--force' => true]);

    $migration = require database_path('migrations/2026_09_18_000000_add_retention_foreign_key_cascades.php');
    Schema::withoutForeignKeyConstraints(fn (): mixed => $migration->down());

    $postId = (string) Str::uuid();
    $orphanTenantId = (string) Str::uuid();
    $userId = (string) Str::uuid();
    DB::table('users')->insert([
        'id' => $userId,
        'name' => 'Migration test user',
        'email' => 'migration-test@example.test',
        'password' => 'password',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('posts')->insert([
        'id' => $postId,
        'tenant_id' => $orphanTenantId,
        'author_id' => $userId,
        'title' => 'Orphaned post for reconciliation',
        'slug' => 'orphaned-post-for-reconciliation',
        'content' => 'Content',
        'status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();
    $migration->up();

    expect(DB::table('posts')->where('id', $postId)->exists())->toBeFalse();
    expect(DB::table('retention_orphaned_rows')
        ->where('source_table', 'posts')
        ->where('source_column', 'tenant_id')
        ->where('source_key', $postId)
        ->value('row_data'))->toContain('Orphaned post for reconciliation');
});

it('quarantines dependents before deleting orphaned parents', function () {
    Artisan::call('migrate:fresh', ['--force' => true]);

    $migration = require database_path('migrations/2026_09_18_000000_add_retention_foreign_key_cascades.php');
    Schema::withoutForeignKeyConstraints(fn (): mixed => $migration->down());

    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $post = Post::factory()->create([
        'tenant_id' => (string) Str::uuid(),
        'author_id' => $user->id,
    ]);
    $categoryId = (string) Str::uuid();
    $commentId = (string) Str::uuid();
    $webhookId = (string) Str::uuid();
    $deliveryId = (string) Str::uuid();

    DB::table('categories')->insert([
        'id' => $categoryId,
        'tenant_id' => $tenant->id,
        'name' => 'Migration category',
        'slug' => 'migration-category',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('comments')->insert([
        'id' => $commentId,
        'tenant_id' => $tenant->id,
        'post_id' => $post->id,
        'author_name' => 'Migration commenter',
        'content' => 'Dependent comment',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('post_category')->insert([
        'post_id' => $post->id,
        'category_id' => $categoryId,
    ]);
    DB::table('outbound_webhooks')->insert([
        'id' => $webhookId,
        'tenant_id' => (string) Str::uuid(),
        'url' => 'https://example.test/webhook',
        'events' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('webhook_deliveries')->insert([
        'id' => $deliveryId,
        'outbound_webhook_id' => $webhookId,
        'event' => 'post.created',
        'payload' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Schema::table('comments', function ($table): void {
        $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
    });
    Schema::table('post_category', function ($table): void {
        $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
    });
    Schema::table('webhook_deliveries', function ($table): void {
        $table->foreign('outbound_webhook_id')->references('id')->on('outbound_webhooks')->cascadeOnDelete();
    });

    $migration->up();

    expect(DB::table('comments')->where('id', $commentId)->exists())->toBeFalse();
    expect(DB::table('post_category')->where('post_id', $post->id)->exists())->toBeFalse();
    expect(DB::table('webhook_deliveries')->where('id', $deliveryId)->exists())->toBeFalse();
    expect(DB::table('retention_orphaned_rows')
        ->where('source_table', 'comments')
        ->where('source_column', 'post_id')
        ->where('source_key', $commentId)
        ->value('row_data') ?? '')->toContain('Dependent comment');
    expect(DB::table('retention_orphaned_rows')
        ->where('source_table', 'post_category')
        ->where('source_column', 'post_id')
        ->where('source_key', $post->id.'|'.$categoryId)
        ->exists())->toBeTrue();
    expect(DB::table('retention_orphaned_rows')
        ->where('source_table', 'webhook_deliveries')
        ->where('source_column', 'outbound_webhook_id')
        ->where('source_key', $deliveryId)
        ->exists())->toBeTrue();
});

it('quarantines each orphan relationship for a row with multiple missing parents', function () {
    Artisan::call('migrate:fresh', ['--force' => true]);

    $migration = require database_path('migrations/2026_09_18_000000_add_retention_foreign_key_cascades.php');
    Schema::withoutForeignKeyConstraints(fn (): mixed => $migration->down());

    $postId = (string) Str::uuid();
    $orphanTenantId = (string) Str::uuid();
    $orphanAuthorId = (string) Str::uuid();
    DB::table('posts')->insert([
        'id' => $postId,
        'tenant_id' => $orphanTenantId,
        'author_id' => $orphanAuthorId,
        'title' => 'Multi-relationship orphan',
        'slug' => 'multi-relationship-orphan',
        'content' => 'Content',
        'status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    expect(DB::table('retention_orphaned_rows')
        ->where('source_table', 'posts')
        ->where('source_column', 'tenant_id')
        ->where('source_key', $postId)
        ->exists())->toBeTrue();
    expect(DB::table('retention_orphaned_rows')
        ->where('source_table', 'posts')
        ->where('source_column', 'author_id')
        ->where('source_key', $postId)
        ->exists())->toBeTrue();
});

it('does not delete a quarantined row repaired before the delete recheck', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('This race regression uses a PostgreSQL trigger.');
    }

    Artisan::call('migrate:fresh', ['--force' => true]);

    $migration = require database_path('migrations/2026_09_18_000000_add_retention_foreign_key_cascades.php');
    Schema::withoutForeignKeyConstraints(fn (): mixed => $migration->down());

    $user = User::factory()->create();
    $orphanTenantId = (string) Str::uuid();
    $post = Post::factory()->create([
        'tenant_id' => $orphanTenantId,
        'author_id' => $user->id,
    ]);

    Schema::create('retention_orphaned_rows', function ($table): void {
        $table->id();
        $table->string('source_table');
        $table->string('source_column');
        $table->string('source_key');
        $table->text('row_data');
        $table->timestamps();
        $table->unique(['source_table', 'source_column', 'source_key'], 'retention_orphaned_rows_unique');
    });

    $quotedTenantId = DB::getPdo()->quote($orphanTenantId);
    DB::statement("CREATE OR REPLACE FUNCTION repair_retention_orphan() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.source_table = 'posts' AND NEW.source_column = 'tenant_id' THEN INSERT INTO tenants (id, domain, name, plan, slug, custom_domain, created_at, updated_at) VALUES ({$quotedTenantId}, 'race-repaired.example.test', 'Race Repaired', 'free', 'race-repaired', NULL, NOW(), NOW()) ON CONFLICT (id) DO NOTHING; END IF; RETURN NEW; END; $$");
    DB::statement('CREATE TRIGGER repair_retention_orphan AFTER INSERT ON retention_orphaned_rows FOR EACH ROW EXECUTE FUNCTION repair_retention_orphan()');

    try {
        $migration->up();
    } finally {
        DB::statement('DROP TRIGGER IF EXISTS repair_retention_orphan ON retention_orphaned_rows');
        DB::statement('DROP FUNCTION IF EXISTS repair_retention_orphan()');
    }

    expect(DB::table('posts')->where('id', $post->id)->exists())->toBeTrue();
    expect(DB::table('tenants')->where('id', $orphanTenantId)->exists())->toBeTrue();
});

it('serializes PostgreSQL parent repair against orphan deletion', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('This serialization regression uses PostgreSQL locks.');
    }

    Artisan::call('migrate:fresh', ['--force' => true]);

    $migration = require database_path('migrations/2026_09_18_000000_add_retention_foreign_key_cascades.php');
    Schema::withoutForeignKeyConstraints(fn (): mixed => $migration->down());

    $user = User::factory()->create();
    $orphanTenantId = (string) Str::uuid();
    Post::factory()->create([
        'tenant_id' => $orphanTenantId,
        'author_id' => $user->id,
    ]);

    $connection = config('database.connections.pgsql');
    $repairConnection = new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', $connection['host'], $connection['port'], $connection['database']),
        $connection['username'],
        $connection['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $repairConnection->exec("SET lock_timeout = '100ms'");
    $repairWasBlocked = false;

    DB::listen(function (QueryExecuted $query) use (&$repairWasBlocked, $repairConnection, $orphanTenantId): void {
        if (! str_starts_with(strtoupper(trim($query->sql)), 'LOCK TABLE')) {
            return;
        }

        try {
            $statement = $repairConnection->prepare('INSERT INTO tenants (id, domain, name, plan, slug, custom_domain, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $statement->execute([$orphanTenantId, 'blocked-repair.example.test', 'Blocked Repair', 'free', 'blocked-repair', null]);
        } catch (PDOException) {
            $repairWasBlocked = true;
        }
    });

    $migration->up();

    expect($repairWasBlocked)->toBeTrue();
});
