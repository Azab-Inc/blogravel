<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_rules', function (Blueprint $table) {
            $table->string('email_recipient')->nullable()->after('destination');
        });
    }

    public function down(): void
    {
        Schema::table('backup_rules', function (Blueprint $table) {
            $table->dropColumn('email_recipient');
        });
    }
};
