<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('orders_monthly_limit')->nullable()->after('storage_limit');
            $table->unsignedInteger('coupon_limit')->nullable()->after('orders_monthly_limit');
            $table->unsignedInteger('promotion_limit')->nullable()->after('coupon_limit');
            $table->unsignedInteger('flash_sale_limit')->nullable()->after('promotion_limit');
            $table->unsignedInteger('api_request_limit')->nullable()->after('flash_sale_limit');
            $table->unsignedInteger('image_limit')->nullable()->after('api_request_limit');
            $table->unsignedInteger('image_max_size_kb')->nullable()->after('image_limit');
            $table->unsignedInteger('branch_limit')->nullable()->after('image_max_size_kb');
            $table->unsignedInteger('warehouse_limit')->nullable()->after('branch_limit');
            $table->unsignedInteger('pos_device_limit')->nullable()->after('warehouse_limit');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'orders_monthly_limit',
                'coupon_limit',
                'promotion_limit',
                'flash_sale_limit',
                'api_request_limit',
                'image_limit',
                'image_max_size_kb',
                'branch_limit',
                'warehouse_limit',
                'pos_device_limit',
            ]);
        });
    }
};
