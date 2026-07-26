<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('sender_account_number', 50)->nullable()->after('payer_name');
            $table->string('invoice_number', 30)->nullable()->after('id');
            $table->index('invoice_number');
        });

        // Backfill existing orders
        DB::table('orders')->whereNull('invoice_number')->orderBy('id')->each(function ($order) {
            $date = $order->created_at ? \Carbon\Carbon::parse($order->created_at)->format('Ymd') : date('Ymd');
            DB::table('orders')->where('id', $order->id)->update([
                'invoice_number' => 'ORD-' . $date . '-' . str_pad($order->id, 5, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('sender_account_number');
        });
    }
};
