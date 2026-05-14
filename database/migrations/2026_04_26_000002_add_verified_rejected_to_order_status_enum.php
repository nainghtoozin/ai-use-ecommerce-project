<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders MODIFY order_status ENUM('pending','paid','verified','rejected','confirmed','shipped','delivered','cancelled') NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders MODIFY order_status ENUM('pending','paid','confirmed','shipped','delivered','cancelled') NULL");
        }
    }
};