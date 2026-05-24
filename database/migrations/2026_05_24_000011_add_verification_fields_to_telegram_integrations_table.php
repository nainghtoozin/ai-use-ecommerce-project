<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_integrations', function (Blueprint $table) {
            $table->string('verification_status', 30)->default('pending_verification')->after('webhook_secret');
            $table->string('chat_type', 20)->nullable()->after('chat_id');
            $table->string('group_title')->nullable()->after('chat_type');
            $table->string('chat_username')->nullable()->after('group_title');

            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_integrations', function (Blueprint $table) {
            $table->dropColumn(['verification_status', 'chat_type', 'group_title', 'chat_username']);
        });
    }
};
