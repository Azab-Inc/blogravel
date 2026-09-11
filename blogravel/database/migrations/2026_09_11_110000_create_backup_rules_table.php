<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('backup_content')->default('both');
            $table->string('schedule');
            $table->string('destination')->default('email');
            $table->string('ftp_host')->nullable();
            $table->integer('ftp_port')->nullable()->default(21);
            $table->string('ftp_user')->nullable();
            $table->string('ftp_pass')->nullable();
            $table->string('ftp_path')->nullable()->default('/');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_rules');
    }
};
