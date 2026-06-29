<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_integrations', function (Blueprint $table) {
            $table->string('personal_chat_id')->nullable()->after('chat_id');
            $table->string('personal_chat_username')->nullable()->after('personal_chat_id');
            $table->string('personal_chat_title')->nullable()->after('personal_chat_username');
            $table->timestamp('personal_verified_at')->nullable()->after('personal_chat_title');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_integrations', function (Blueprint $table) {
            $table->dropColumn(['personal_chat_id', 'personal_chat_username', 'personal_chat_title', 'personal_verified_at']);
        });
    }
};
