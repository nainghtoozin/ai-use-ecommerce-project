<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('activated_at');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('trial_renewals_count')->default(0)->after('trial_ends_at');
        });

        Schema::table('platform_settings', function (Blueprint $table) {
            $table->boolean('allow_trial_renewal')->default(true)->after('trial_days');
            $table->unsignedTinyInteger('max_trial_renewals')->default(0)->after('allow_trial_renewal');
        });

        // Backfill: lock existing expired/suspended tenants
        $tenantIds = DB::table('subscriptions')
            ->whereIn('status', ['expired', 'suspended'])
            ->pluck('tenant_id')
            ->unique()
            ->toArray();

        if (!empty($tenantIds)) {
            DB::table('tenants')
                ->whereIn('id', $tenantIds)
                ->whereNull('locked_at')
                ->update(['locked_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('locked_at');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('trial_renewals_count');
        });

        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['allow_trial_renewal', 'max_trial_renewals']);
        });
    }
};
