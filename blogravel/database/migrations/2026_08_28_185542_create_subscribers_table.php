<?php

use App\Enums\SubscriberStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status')->default(SubscriberStatus::Subscribed->value);
            $table->timestamps();

            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
