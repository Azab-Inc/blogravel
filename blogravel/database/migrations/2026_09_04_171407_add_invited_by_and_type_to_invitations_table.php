<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->uuid('invited_by')->nullable()->after('tenant_id');
            $table->string('type')->default('email')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn(['invited_by', 'type']);
        });
    }
};
