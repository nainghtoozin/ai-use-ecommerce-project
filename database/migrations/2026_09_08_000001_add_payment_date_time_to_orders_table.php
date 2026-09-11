<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('transaction_id');
            }
            if (!Schema::hasColumn('orders', 'payment_time')) {
                $table->string('payment_time', 10)->nullable()->after('payment_date');
            }
            if (!Schema::hasColumn('orders', 'payment_note')) {
                $table->text('payment_note')->nullable()->after('payment_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = array_filter(['payment_date', 'payment_time', 'payment_note'], fn($c) => Schema::hasColumn('orders', $c));
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};