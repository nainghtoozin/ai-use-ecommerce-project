<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_product', function (Blueprint $table) {
            $table->foreignId('promotion_id')
                ->constrained('promotions')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->primary(['promotion_id', 'product_id']);

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_product');
    }
};
