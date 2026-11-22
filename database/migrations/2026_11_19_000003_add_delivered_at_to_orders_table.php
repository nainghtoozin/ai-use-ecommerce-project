<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'delivered_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('delivered_at')->nullable()->after('stock_reduced');
            });
        }

        DB::table('orders')
            ->where('order_status', 'delivered')
            ->whereNull('delivered_at')
            ->update(['delivered_at' => DB::raw('COALESCE(updated_at, created_at)')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'delivered_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('delivered_at');
            });
        }
    }
};
