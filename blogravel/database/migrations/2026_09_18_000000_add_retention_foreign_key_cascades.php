<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ORPHAN_TABLE = 'retention_orphaned_rows';

    /**
     * @return list<array{table: string, column: string, referencedTable: string, action: string, keyColumns: list<string>}>
     */
    private function foreignKeys(): array
    {
        return [
            ['table' => 'posts', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'posts', 'column' => 'author_id', 'referencedTable' => 'users', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'pages', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'categories', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'tags', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'comments', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'comments', 'column' => 'post_id', 'referencedTable' => 'posts', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'media', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'subscribers', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'ai_providers', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'api_keys', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'settings', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'webhooks', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'invitations', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'subscriptions', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'post_category', 'column' => 'post_id', 'referencedTable' => 'posts', 'action' => 'cascade', 'keyColumns' => ['post_id', 'category_id']],
            ['table' => 'post_category', 'column' => 'category_id', 'referencedTable' => 'categories', 'action' => 'cascade', 'keyColumns' => ['post_id', 'category_id']],
            ['table' => 'post_tag', 'column' => 'post_id', 'referencedTable' => 'posts', 'action' => 'cascade', 'keyColumns' => ['post_id', 'tag_id']],
            ['table' => 'post_tag', 'column' => 'tag_id', 'referencedTable' => 'tags', 'action' => 'cascade', 'keyColumns' => ['post_id', 'tag_id']],
            ['table' => 'subscriber_category', 'column' => 'subscriber_id', 'referencedTable' => 'subscribers', 'action' => 'cascade', 'keyColumns' => ['subscriber_id', 'category_id']],
            ['table' => 'subscriber_category', 'column' => 'category_id', 'referencedTable' => 'categories', 'action' => 'cascade', 'keyColumns' => ['subscriber_id', 'category_id']],
            ['table' => 'passkeys', 'column' => 'user_id', 'referencedTable' => 'users', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'outbound_webhooks', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade', 'keyColumns' => ['id']],
            ['table' => 'webhook_deliveries', 'column' => 'outbound_webhook_id', 'referencedTable' => 'outbound_webhooks', 'action' => 'cascade', 'keyColumns' => ['id']],
        ];
    }

    public function up(): void
    {
        $this->createOrphanTable();
        $this->reconcileOrphans();

        foreach ($this->foreignKeys() as $foreignKey) {
            if ($this->foreignKeyMatches($foreignKey)) {
                continue;
            }

            if ($this->foreignKeyExists($foreignKey)) {
                Schema::table($foreignKey['table'], function (Blueprint $table) use ($foreignKey): void {
                    $table->dropForeign([$foreignKey['column']]);
                });
            }

            Schema::table($foreignKey['table'], function (Blueprint $table) use ($foreignKey): void {
                $constraint = $table->foreign($foreignKey['column'])
                    ->references('id')
                    ->on($foreignKey['referencedTable']);

                if ($foreignKey['action'] === 'cascade') {
                    $constraint->cascadeOnDelete();
                } else {
                    $constraint->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->foreignKeys() as $foreignKey) {
            if (! $this->foreignKeyExists($foreignKey)) {
                continue;
            }

            Schema::table($foreignKey['table'], function (Blueprint $table) use ($foreignKey): void {
                $table->dropForeign([$foreignKey['column']]);
            });
        }

        Schema::dropIfExists(self::ORPHAN_TABLE);
    }

    private function createOrphanTable(): void
    {
        if (Schema::hasTable(self::ORPHAN_TABLE)) {
            return;
        }

        Schema::create(self::ORPHAN_TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('source_table');
            $table->string('source_column');
            $table->string('source_key');
            $table->text('row_data');
            $table->timestamps();
            $table->unique(['source_table', 'source_column', 'source_key'], 'retention_orphaned_rows_unique');
        });
    }

    private function reconcileOrphans(): void
    {
        foreach ($this->foreignKeys() as $foreignKey) {
            $orphanedRows = DB::table($foreignKey['table'].' as child')
                ->select('child.*')
                ->whereNotNull('child.'.$foreignKey['column'])
                ->whereNotExists(function ($query) use ($foreignKey): void {
                    $query->selectRaw('1')
                        ->from($foreignKey['referencedTable'].' as parent')
                        ->whereColumn('parent.id', 'child.'.$foreignKey['column']);
                })
                ->get();

            foreach ($orphanedRows as $row) {
                $sourceKey = $this->sourceKey($row, $foreignKey['keyColumns']);
                $quarantineQuery = DB::table(self::ORPHAN_TABLE)
                    ->where('source_table', $foreignKey['table'])
                    ->where('source_column', $foreignKey['column'])
                    ->where('source_key', $sourceKey);

                if (! $quarantineQuery->exists()) {
                    DB::table(self::ORPHAN_TABLE)->insert([
                        'source_table' => $foreignKey['table'],
                        'source_column' => $foreignKey['column'],
                        'source_key' => $sourceKey,
                        'row_data' => json_encode((array) $row, JSON_THROW_ON_ERROR),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $deleteQuery = DB::table($foreignKey['table']);
                foreach ($foreignKey['keyColumns'] as $keyColumn) {
                    $deleteQuery->where($keyColumn, $row->{$keyColumn});
                }
                $deleteQuery->delete();
            }
        }
    }

    /**
     * @param  list<string>  $keyColumns
     */
    private function sourceKey(object $row, array $keyColumns): string
    {
        return implode('|', array_map(
            fn (string $keyColumn): string => (string) $row->{$keyColumn},
            $keyColumns,
        ));
    }

    /**
     * @param  array{table: string, column: string, referencedTable: string, action: string, keyColumns: list<string>}  $foreignKey
     */
    private function foreignKeyMatches(array $foreignKey): bool
    {
        $constraint = $this->foreignKeyDefinition($foreignKey);

        if ($constraint === null) {
            return false;
        }

        return match (DB::getDriverName()) {
            'sqlite' => strtolower($constraint->on_delete) === $foreignKey['action'],
            'pgsql' => $foreignKey['action'] === 'cascade'
                ? str_contains(strtolower($constraint->definition), 'on delete cascade')
                : ! str_contains(strtolower($constraint->definition), 'on delete cascade'),
            'mysql', 'mariadb' => strtolower($constraint->delete_rule) === $foreignKey['action'],
            default => false,
        };
    }

    /**
     * @param  array{table: string, column: string, referencedTable: string, action: string, keyColumns: list<string>}  $foreignKey
     */
    private function foreignKeyExists(array $foreignKey): bool
    {
        return $this->foreignKeyDefinition($foreignKey) !== null;
    }

    /**
     * @param  array{table: string, column: string, referencedTable: string, action: string, keyColumns: list<string>}  $foreignKey
     */
    private function foreignKeyDefinition(array $foreignKey): ?object
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (DB::select("PRAGMA foreign_key_list('{$foreignKey['table']}')") as $constraint) {
                if ($constraint->from === $foreignKey['column'] && $constraint->table === $foreignKey['referencedTable']) {
                    return $constraint;
                }
            }

            return null;
        }

        if (DB::getDriverName() === 'pgsql') {
            return DB::selectOne(
                'SELECT pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE conrelid = to_regclass(?) AND conname = ?',
                [$foreignKey['table'], $foreignKey['table'].'_'.$foreignKey['column'].'_foreign'],
            );
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return DB::selectOne(
                'SELECT rc.DELETE_RULE AS delete_rule FROM information_schema.REFERENTIAL_CONSTRAINTS rc INNER JOIN information_schema.KEY_COLUMN_USAGE kcu ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME AND kcu.TABLE_NAME = rc.TABLE_NAME WHERE kcu.CONSTRAINT_SCHEMA = ? AND kcu.TABLE_NAME = ? AND kcu.COLUMN_NAME = ? AND kcu.REFERENCED_TABLE_NAME = ? AND kcu.CONSTRAINT_NAME = ?',
                [DB::getDatabaseName(), $foreignKey['table'], $foreignKey['column'], $foreignKey['referencedTable'], $foreignKey['table'].'_'.$foreignKey['column'].'_foreign'],
            );
        }

        return null;
    }
};
