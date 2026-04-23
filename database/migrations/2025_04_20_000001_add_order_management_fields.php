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
                $table->enum('payment_status', ['unpaid', 'paid', 'verified', 'rejected'])
                    ->default('unpaid')
                    ->after('payment_proof');
            }

            if (! Schema::hasColumn('orders', 'order_status')) {
                $table->enum('order_status', ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'])
                    ->default('pending')
                    ->after('payment_status');
            }

            if (! Schema::hasColumn('orders', 'delivery_fee')) {
                $table->decimal('delivery_fee', 10, 2)
                    ->default(0)
                    ->after('total_amount');
            }

            if (! Schema::hasColumn('orders', 'transaction_id')) {
                $table->string('transaction_id')
                    ->nullable()
                    ->after('payment_proof');
            }

            if (! Schema::hasColumn('orders', 'city_id')) {
                $table->foreignId('city_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'township_id')) {
                $table->foreignId('township_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'city_id')) {
                $table->dropForeign(['city_id']);
            }

            if (Schema::hasColumn('orders', 'township_id')) {
                $table->dropForeign(['township_id']);
            }

            $columnsToDrop = array_filter([
                Schema::hasColumn('orders', 'payment_status') ? 'payment_status' : null,
                Schema::hasColumn('orders', 'order_status') ? 'order_status' : null,
                Schema::hasColumn('orders', 'delivery_fee') ? 'delivery_fee' : null,
                Schema::hasColumn('orders', 'transaction_id') ? 'transaction_id' : null,
                Schema::hasColumn('orders', 'city_id') ? 'city_id' : null,
                Schema::hasColumn('orders', 'township_id') ? 'township_id' : null,
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
