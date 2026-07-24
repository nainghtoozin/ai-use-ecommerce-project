<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // opening_stock, purchase, sale, return, adjustment, transfer
            $table->decimal('quantity', 12, 2); // positive = stock in, negative = stock out
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->nullableMorphs('reference');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'type']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
