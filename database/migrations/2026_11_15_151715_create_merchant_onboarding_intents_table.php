<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_onboarding_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('status', 32)->default('pending_verification');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_onboarding_intents');
    }
};
