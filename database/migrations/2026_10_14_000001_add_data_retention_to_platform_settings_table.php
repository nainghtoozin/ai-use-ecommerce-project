<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('platform_settings', 'tenant_data_retention_days')) {
                $table->unsignedInteger('tenant_data_retention_days')->default(15)->after('notification_retention_days');
            }
            if (!Schema::hasColumn('platform_settings', 'tenant_auto_delete_enabled')) {
                $table->boolean('tenant_auto_delete_enabled')->default(false)->after('tenant_data_retention_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            if (Schema::hasColumn('platform_settings', 'tenant_auto_delete_enabled')) {
                $table->dropColumn('tenant_auto_delete_enabled');
            }
            if (Schema::hasColumn('platform_settings', 'tenant_data_retention_days')) {
                $table->dropColumn('tenant_data_retention_days');
            }
        });
    }
};
