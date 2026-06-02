<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'payment_method')) {
                $table->dropColumn('payment_method');
            }

            if (Schema::hasColumn('orders', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payment_status')) {
                $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])
                    ->default('pending')
                    ->after('payment_proof');
            }

            if (! Schema::hasColumn('orders', 'order_status')) {
                $table->enum('order_status', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'])
                    ->default('pending')
                    ->after('payment_status');
            }

            if (! Schema::hasColumn('orders', 'transaction_id')) {
                $table->string('transaction_id')
                    ->nullable()
                    ->after('payment_proof');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                Schema::hasColumn('orders', 'payment_status') ? 'payment_status' : null,
                Schema::hasColumn('orders', 'order_status') ? 'order_status' : null,
                Schema::hasColumn('orders', 'delivery_fee') ? 'delivery_fee' : null,
                Schema::hasColumn('orders', 'transaction_id') ? 'transaction_id' : null,
            ]);

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }

            if (! Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method')->default('cash');
            }

            if (! Schema::hasColumn('orders', 'status')) {
                $table->string('status')->default('pending');
            }
        });
    }
};
