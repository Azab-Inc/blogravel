<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('deleted_by')->nullable()->after('deleted_at');
            $table->string('deletion_reason')->nullable()->after('deleted_by');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('alter table users drop constraint if exists users_email_unique');
        } else {
            DB::statement('drop index if exists users_email_unique');
        }

        DB::statement('create unique index users_email_active_unique on users (email) where deleted_at is null');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists users_email_active_unique');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('alter table users add constraint users_email_unique unique (email)');
        } else {
            DB::statement('create unique index users_email_unique on users (email)');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['deleted_by', 'deletion_reason']);
        });
    }
};
