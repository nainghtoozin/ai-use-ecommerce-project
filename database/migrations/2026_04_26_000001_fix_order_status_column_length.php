<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders MODIFY order_status VARCHAR(20) NULL");
            DB::statement("ALTER TABLE orders MODIFY payment_status VARCHAR(20) NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders MODIFY order_status VARCHAR(10) NULL");
            DB::statement("ALTER TABLE orders MODIFY payment_status VARCHAR(10) NULL");
        }
    }
};