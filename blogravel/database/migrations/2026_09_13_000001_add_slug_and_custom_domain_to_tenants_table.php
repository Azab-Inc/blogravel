<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('domain');
            $table->string('custom_domain')->nullable()->after('slug');
        });

        $usedSlugs = [];
        $reservedLabels = array_map(
            static fn (mixed $reservedLabel): string => strtolower(trim((string) $reservedLabel)),
            config('tenancy.reserved_labels', []),
        );

        DB::table('tenants')
            ->select(['id', 'name'])
            ->orderBy('domain')
            ->orderBy('id')
            ->each(function (object $tenant) use (&$usedSlugs, $reservedLabels): void {
                $baseSlug = Str::slug($tenant->name) ?: 'tenant-'.$tenant->id;
                if (in_array(strtolower($baseSlug), $reservedLabels, true)) {
                    $baseSlug = 'tenant-'.$tenant->id;
                }
                $slug = $baseSlug;
                $suffix = 2;

                while (isset($usedSlugs[$slug])) {
                    $slug = $baseSlug.'-'.$suffix;
                    $suffix++;
                }

                $usedSlugs[$slug] = true;

                DB::table('tenants')
                    ->where('id', $tenant->id)
                    ->update(['slug' => $slug]);
            });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
            $table->unique('custom_domain');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_custom_domain_unique');
            DB::statement('DROP INDEX IF EXISTS tenants_custom_domain_unique');
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique('tenants_slug_unique');

            if (DB::getDriverName() !== 'pgsql') {
                $table->dropUnique('tenants_custom_domain_unique');
            }

            $table->dropColumn(['slug', 'custom_domain']);
        });
    }
};
