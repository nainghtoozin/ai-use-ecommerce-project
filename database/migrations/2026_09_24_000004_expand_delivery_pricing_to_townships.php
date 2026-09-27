<?php

use App\Services\DeliveryPricingBackfillService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_pricing') && !Schema::hasColumn('delivery_pricing', 'township_id')) {
            Schema::table('delivery_pricing', function (Blueprint $table) {
                $table->foreignId('township_id')->nullable()->after('city_id')->constrained()->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('delivery_pricing')) {
            try {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE `delivery_pricing` MODIFY `city_id` BIGINT UNSIGNED NULL');
            } catch (\Throwable $e) {
            }
        }

        try {
            Schema::table('delivery_pricing', function (Blueprint $table) {
                $table->unique(['delivery_service_id', 'township_id'], 'delivery_pricing_service_township_unique');
            });
        } catch (\Throwable $e) {
        }

        app(DeliveryPricingBackfillService::class)->run();
    }

    public function down(): void
    {
        if (Schema::hasColumn('delivery_pricing', 'township_id')) {
            \Illuminate\Support\Facades\DB::table('delivery_pricing')
                ->whereNotNull('township_id')
                ->delete();
        }

        try {
            Schema::table('delivery_pricing', function (Blueprint $table) {
                $table->dropUnique('delivery_pricing_service_township_unique');
            });
        } catch (\Throwable $e) {
        }

        if (Schema::hasColumn('delivery_pricing', 'township_id')) {
            Schema::table('delivery_pricing', function (Blueprint $table) {
                $table->dropConstrainedForeignId('township_id');
            });
        }
    }
};
