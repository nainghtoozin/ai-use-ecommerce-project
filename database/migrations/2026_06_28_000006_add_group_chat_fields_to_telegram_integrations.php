<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_integrations', function (Blueprint $table) {
            $table->string('group_chat_id')->nullable()->after('personal_verified_at');
            $table->string('group_chat_title')->nullable()->after('group_chat_id');
            $table->string('group_chat_username')->nullable()->after('group_chat_title');
            $table->string('group_chat_type', 20)->nullable()->after('group_chat_username');
            $table->timestamp('group_verified_at')->nullable()->after('group_chat_type');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_integrations', function (Blueprint $table) {
            $table->dropColumn(['group_chat_id', 'group_chat_title', 'group_chat_username', 'group_chat_type', 'group_verified_at']);
        });
    }
};
