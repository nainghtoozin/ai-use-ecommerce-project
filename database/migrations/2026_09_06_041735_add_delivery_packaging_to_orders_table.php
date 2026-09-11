<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('packaging_id')->nullable()->after('township_id');
            $table->foreignId('delivery_service_id')->nullable()->after('packaging_id')->constrained('delivery_services')->nullOnDelete();
            $table->unsignedInteger('delivery_days_min')->nullable()->after('delivery_service_id');
            $table->unsignedInteger('delivery_days_max')->nullable()->after('delivery_days_min');
            $table->decimal('cod_fee', 8, 2)->nullable()->after('delivery_days_max');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['delivery_service_id']);
            $table->dropColumn(['packaging_id', 'delivery_service_id', 'delivery_days_min', 'delivery_days_max', 'cod_fee']);
        });
    }
};
