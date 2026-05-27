<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the product_variants table for variable products.
     * Each variant represents a unique combination of options (size, color, etc.)
     * with its own pricing, stock, and SKU.
     *
     * Architecture notes:
     * - variants only exist when parent product.type = 'variable'
     * - parent product's price/stock fields serve as default/fallback values
     * - attributes_json stores flexible option key-value pairs
     * - image field stores variant-specific product photo
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();

            // Parent product reference
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();

            // Identification
            $table->string('sku')->nullable()->comment('Unique SKU for this variant');
            $table->string('barcode')->nullable()->comment('ISBN, UPC, or GTIN for this variant');

            // Pricing (decimal for precision)
            $table->decimal('price', 10, 2)->nullable()
                  ->comment('Selling price. Falls back to parent product price if null');
            $table->decimal('compare_price', 10, 2)->nullable()
                  ->comment('Original/compare-at price for discount display');
            $table->decimal('cost_price', 10, 2)->nullable()
                  ->comment('Cost per item — not visible to customers');

            // Inventory
            $table->integer('stock')->default(0)
                  ->comment('Available quantity for this specific variant');
            $table->integer('low_stock_threshold')->default(5)
                  ->comment('Alert threshold for this variant');

            // Media
            $table->string('image')->nullable()
                  ->comment('Variant-specific product image');

            // Variant attributes as flexible JSON
            // Example: {"size": "XL", "color": "Black", "material": "Cotton"}
            $table->json('attributes')->nullable()
                  ->comment('Flexible option key-value pairs for variant identification');

            // Status
            $table->enum('status', ['active', 'inactive', 'draft'])->default('active')
                  ->comment('Variant availability status');

            $table->timestamps();

            // Indexes for common queries
            $table->index('product_id');
            $table->index('sku');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
