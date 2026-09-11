<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'packaging_fee')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('packaging_fee', 10, 2)->nullable()->after('packaging_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'packaging_fee')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('packaging_fee');
            });
        }
    }
};