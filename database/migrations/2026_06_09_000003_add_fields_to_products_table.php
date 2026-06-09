<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->string('barcode', 100)->nullable()->after('sku');
            $table->string('short_description', 500)->nullable()->after('description');
            $table->decimal('cost_price', 10, 2)->nullable()->after('base_price');
            $table->integer('low_stock_alert')->default(5)->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['slug', 'barcode', 'short_description', 'cost_price', 'low_stock_alert']);
        });
    }
};
