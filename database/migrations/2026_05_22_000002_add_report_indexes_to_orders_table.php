<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasIndex('orders', 'idx_orders_status')) {
                $table->index('order_status', 'idx_orders_status');
            }

            if (!Schema::hasIndex('orders', 'idx_orders_created_at')) {
                $table->index('created_at', 'idx_orders_created_at');
            }

            if (!Schema::hasIndex('orders', 'idx_orders_status_created')) {
                $table->index(['order_status', 'created_at'], 'idx_orders_status_created');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_status');
            $table->dropIndex('idx_orders_created_at');
            $table->dropIndex('idx_orders_status_created');
        });
    }
};
