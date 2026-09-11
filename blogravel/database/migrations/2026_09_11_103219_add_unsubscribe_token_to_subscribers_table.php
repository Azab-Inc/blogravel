<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('unsubscribe_token', 64)->nullable()->after('confirmation_token');
        });

        // Bootstrap unsubscribe tokens from existing confirmation tokens
        DB::table('subscribers')
            ->whereNull('unsubscribe_token')
            ->update(['unsubscribe_token' => Str::random(64)]);
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn('unsubscribe_token');
        });
    }
};
