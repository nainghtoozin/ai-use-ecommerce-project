<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('merchant_recovery_claims')) {
            Schema::create('merchant_recovery_claims', function (Blueprint $table) {
                $table->id();
                $table->string('archive_id', 191)->unique();
                $table->unsignedBigInteger('source_tenant_id')->index();
                $table->unsignedBigInteger('target_tenant_id')->nullable()->index();
                $table->string('target_slug', 80);
                $table->string('archive_name', 191)->nullable();
                $table->string('actor_type', 191)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('status', 20)->default('claimed')->index();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_recovery_claims');
    }
};
