<?php

use App\Enums\DeletionReason;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('stores deletion provenance and casts the deletion reason', function () {
    $user = User::factory()->create([
        'deletion_reason' => DeletionReason::SelfClosed,
    ]);

    expect($user->deletion_reason)->toBe(DeletionReason::SelfClosed);

    $columns = DB::connection()->getDriverName() === 'sqlite'
        ? DB::select("select name as column_name from pragma_table_info('users') where name in ('deleted_by', 'deletion_reason')")
        : DB::select("select column_name from information_schema.columns where table_name = 'users' and column_name in ('deleted_by', 'deletion_reason')");

    expect($columns)->toHaveCount(2);
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
        ->toThrow(QueryException::class);
});

it('creates the PostgreSQL partial active email index', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL is not the active test connection.');
    }

    $index = DB::selectOne("select indexname, indexdef from pg_indexes where schemaname = current_schema() and tablename = 'users' and indexname = 'users_email_active_unique'");

    expect($index)->not->toBeNull();
    expect($index->indexname)->toBe('users_email_active_unique');
    expect($index->indexdef)->toMatch('/where\s+\(deleted_at\s+is\s+null\)/i');
});
