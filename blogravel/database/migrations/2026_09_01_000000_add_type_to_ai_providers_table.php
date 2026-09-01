<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->string('type')->default('openai')->after('tenant_id');
            $table->string('model')->after('type');
            $table->decimal('temperature', 3, 2)->default(0.70)->after('model');
            $table->integer('max_tokens')->default(2048)->after('temperature');
            $table->text('custom_template')->nullable()->after('max_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn(['type', 'model', 'temperature', 'max_tokens', 'custom_template']);
        });
    }
};
