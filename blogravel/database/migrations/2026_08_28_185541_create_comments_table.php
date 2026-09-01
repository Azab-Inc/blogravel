<?php

use App\Enums\CommentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('author_name');
            $table->string('author_email')->nullable();
            $table->longText('content');
            $table->string('status')->default(CommentStatus::Pending->value);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
