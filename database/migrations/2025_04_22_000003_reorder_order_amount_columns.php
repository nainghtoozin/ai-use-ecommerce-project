<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Reorder columns: subtotal → delivery_fee → total_amount
        // Using MODIFY COLUMN to reposition (MySQL specific)
        
        DB::statement("ALTER TABLE orders MODIFY subtotal DECIMAL(10,2) NULL AFTER id");
        DB::statement("ALTER TABLE orders MODIFY delivery_fee DECIMAL(10,2) NULL AFTER subtotal");
        DB::statement("ALTER TABLE orders MODIFY total_amount DECIMAL(10,2) NULL AFTER delivery_fee");
    }

    public function down(): void
    {
        // Revert to original order
        DB::statement("ALTER TABLE orders MODIFY total_amount DECIMAL(10,2) NOT NULL AFTER notes");
        DB::statement("ALTER TABLE orders MODIFY delivery_fee DECIMAL(10,2) NULL AFTER total_amount");
        DB::statement("ALTER TABLE orders MODIFY subtotal DECIMAL(10,2) NULL AFTER delivery_fee");
    }
};