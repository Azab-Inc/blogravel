<?php

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
