<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->boolean('trial_enabled')->default(true)->after('registration_enabled');
            $table->unsignedTinyInteger('trial_days')->default(14)->after('trial_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['trial_enabled', 'trial_days']);
        });
    }
};
