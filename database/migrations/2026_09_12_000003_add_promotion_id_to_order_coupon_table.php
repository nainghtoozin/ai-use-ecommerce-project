<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_coupon', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('coupon_id')->constrained('promotions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_coupon', function (Blueprint $table) {
            $table->dropForeign(['promotion_id']);
            $table->dropColumn('promotion_id');
        });
    }
};
