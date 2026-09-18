<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return list<array{table: string, column: string, referencedTable: string, action: string}>
     */
    private function foreignKeys(): array
    {
        return [
            ['table' => 'posts', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade'],
            ['table' => 'posts', 'column' => 'author_id', 'referencedTable' => 'users', 'action' => 'cascade'],
            ['table' => 'pages', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade'],
            ['table' => 'categories', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade'],
            ['table' => 'tags', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade'],
            ['table' => 'comments', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade'],
            ['table' => 'comments', 'column' => 'post_id', 'referencedTable' => 'posts', 'action' => 'cascade'],
            ['table' => 'media', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade'],
            ['table' => 'subscribers', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade'],
            ['table' => 'ai_providers', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade'],
            ['table' => 'api_keys', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade'],
            ['table' => 'settings', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade'],
            ['table' => 'webhooks', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade'],
            ['table' => 'invitations', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade'],
            ['table' => 'subscriptions', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade'],
            ['table' => 'post_category', 'column' => 'post_id', 'referencedTable' => 'posts', 'action' => 'cascade'],
            ['table' => 'post_category', 'column' => 'category_id', 'referencedTable' => 'categories', 'action' => 'cascade'],
            ['table' => 'post_tag', 'column' => 'post_id', 'referencedTable' => 'posts', 'action' => 'cascade'],
            ['table' => 'post_tag', 'column' => 'tag_id', 'referencedTable' => 'tags', 'action' => 'cascade'],
            ['table' => 'subscriber_category', 'column' => 'subscriber_id', 'referencedTable' => 'subscribers', 'action' => 'cascade'],
            ['table' => 'subscriber_category', 'column' => 'category_id', 'referencedTable' => 'categories', 'action' => 'cascade'],
            ['table' => 'passkeys', 'column' => 'user_id', 'referencedTable' => 'users', 'action' => 'cascade'],
            ['table' => 'outbound_webhooks', 'column' => 'tenant_id', 'referencedTable' => 'tenants', 'action' => 'cascade'],
            ['table' => 'webhook_deliveries', 'column' => 'outbound_webhook_id', 'referencedTable' => 'outbound_webhooks', 'action' => 'cascade'],
        ];
    }

    public function up(): void
    {
        foreach ($this->foreignKeys() as $foreignKey) {
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
            Schema::table($foreignKey['table'], function (Blueprint $table) use ($foreignKey): void {
                $table->dropForeign([$foreignKey['column']]);
            });
        }
    }
};
