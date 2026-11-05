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
            $table->string('maintenance_title')->nullable();
            $table->text('maintenance_message')->nullable();
            $table->timestamp('maintenance_return_time')->nullable();
            $table->string('support_phone')->nullable();
            $table->string('support_button_text')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['maintenance_title', 'maintenance_message', 'maintenance_return_time', 'support_phone', 'support_button_text']);
        });
    }
};
