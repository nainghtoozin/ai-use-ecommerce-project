<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_cleanups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('status', 20)->default('grace');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('restricted_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $table->json('reminders_sent')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('reason')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('grace_ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_cleanups');
    }
};
