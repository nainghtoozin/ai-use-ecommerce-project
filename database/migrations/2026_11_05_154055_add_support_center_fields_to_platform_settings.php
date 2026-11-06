<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->boolean('support_center_enabled')->default(true);
            $table->string('telegram_url')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('viber_url')->nullable();
            $table->string('support_title')->nullable();
            $table->text('support_description')->nullable();
            $table->boolean('live_chat_enabled')->default(false);
            $table->string('business_hours')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['support_center_enabled','telegram_url','telegram_username','viber_url','support_title','support_description','live_chat_enabled','business_hours']);
        });
    }
};
