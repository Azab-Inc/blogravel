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

        /**
         * When legacy values normalize to the same host, the lowest tenant ID
         * keeps the canonical value and later rows are cleared. This stable
         * policy prevents one host from resolving ambiguously; cleared values
         * must be reviewed and reassigned explicitly before becoming active.
         */
        $normalizedDomains = [];

        DB::table('tenants')
            ->whereNotNull('custom_domain')
            ->orderBy('id')
            ->get(['id', 'custom_domain'])
            ->each(function (object $tenant) use (&$normalizedDomains): void {
                $normalizedDomain = strtolower(trim((string) $tenant->custom_domain));

                if ($normalizedDomain === '' || isset($normalizedDomains[$normalizedDomain])) {
                    DB::table('tenants')
                        ->where('id', $tenant->id)
                        ->update(['custom_domain' => null]);

                    return;
                }

                $normalizedDomains[$normalizedDomain] = true;
                DB::table('tenants')
                    ->where('id', $tenant->id)
                    ->update(['custom_domain' => $normalizedDomain]);
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
