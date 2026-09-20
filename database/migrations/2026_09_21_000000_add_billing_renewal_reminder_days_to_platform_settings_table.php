<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('platform_settings', 'billing_renewal_reminder_days')) {
            Schema::table('platform_settings', function (Blueprint $table) {
                $table->unsignedInteger('billing_renewal_reminder_days')->default(7)->after('max_trial_renewals');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('platform_settings', 'billing_renewal_reminder_days')) {
            Schema::table('platform_settings', function (Blueprint $table) {
                $table->dropColumn('billing_renewal_reminder_days');
            });
        }
    }
};
