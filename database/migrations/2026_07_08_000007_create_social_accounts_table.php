<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('provider_id', 255);
            $table->string('provider_email', 255)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->text('token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id'], 'sa_provider_provider_id_unique');
            $table->index('account_id', 'sa_account_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
