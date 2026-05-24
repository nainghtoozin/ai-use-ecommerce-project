<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bot_name');
            $table->string('bot_username');
            $table->text('bot_token');
            $table->string('chat_id')->nullable();
            $table->string('parse_mode')->default('HTML');
            $table->boolean('is_enabled')->default(false);
            $table->string('webhook_secret', 64)->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->index('bot_username');
            $table->index('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_integrations');
    }
};
