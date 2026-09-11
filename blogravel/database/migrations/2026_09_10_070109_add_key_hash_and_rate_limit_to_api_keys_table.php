<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('token')->nullable()->change();
            $table->string('key_hash', 64)->nullable()->after('token');
            $table->unsignedInteger('rate_limit_per_minute')->nullable()->after('key_hash');
        });

        // Data migration: hash existing plaintext tokens
        DB::table('api_keys')->orderBy('id')->each(function ($row) {
            if ($row->token && ! $row->key_hash) {
                DB::table('api_keys')
                    ->where('id', $row->id)
                    ->update(['key_hash' => hash('sha256', $row->token)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn(['key_hash', 'rate_limit_per_minute']);
        });
    }
};
