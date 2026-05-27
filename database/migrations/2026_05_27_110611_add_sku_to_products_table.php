<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add SKU column to products table.
     *
     * SKU is optional during creation — auto-generated if left empty.
     * Format: PRD-{id} (e.g. PRD-1001)
     *
     * Indexed for fast lookups in POS, barcode scanning, and reporting.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku')->nullable()->unique()->after('name')
                  ->comment('Unique product identifier — auto-generated if empty');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sku']);
            $table->dropColumn('sku');
        });
    }
};
