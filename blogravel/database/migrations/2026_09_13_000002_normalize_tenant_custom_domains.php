<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropUnique('tenants_custom_domain_unique');
        });

        DB::table('tenants')
            ->whereNotNull('custom_domain')
            ->get(['id', 'custom_domain'])
            ->each(function (object $tenant): void {
                DB::table('tenants')
                    ->where('id', $tenant->id)
                    ->update(['custom_domain' => strtolower(trim((string) $tenant->custom_domain))]);
            });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->unique('custom_domain');
        });
    }

    public function down(): void
    {
        // Normalization is intentionally irreversible; the unique constraint remains valid.
    }
};
