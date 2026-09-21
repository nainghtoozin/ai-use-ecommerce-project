<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('platform_settings', 'notification_retention_days')) {
            Schema::table('platform_settings', function (Blueprint $table) {
                $table->unsignedInteger('notification_retention_days')->default(90)->after('audit_retention_days');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('platform_settings', 'notification_retention_days')) {
            Schema::table('platform_settings', function (Blueprint $table) {
                $table->dropColumn('notification_retention_days');
            });
        }
    }
};
