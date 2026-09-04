<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('notifications', function ($table) {
                $table->dropIndex('notifications_notifiable_type_notifiable_id_index');
                $table->dropColumn('notifiable_id');
                $table->uuid('notifiable_id')->after('notifiable_type');
                $table->index(['notifiable_type', 'notifiable_id']);
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('notifications', function ($table) {
                $table->dropIndex('notifications_notifiable_type_notifiable_id_index');
                $table->dropColumn('notifiable_id');
                $table->unsignedBigInteger('notifiable_id')->after('notifiable_type');
                $table->index(['notifiable_type', 'notifiable_id']);
            });
        }
    }
};
