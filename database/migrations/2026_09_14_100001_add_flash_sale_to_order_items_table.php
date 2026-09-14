<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('flash_sale_id')->nullable()->after('variant_id')->constrained('flash_sales')->nullOnDelete();
            $table->decimal('original_price', 12, 2)->nullable()->after('flash_sale_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['flash_sale_id']);
            $table->dropColumn(['flash_sale_id', 'original_price']);
        });
    }
};
