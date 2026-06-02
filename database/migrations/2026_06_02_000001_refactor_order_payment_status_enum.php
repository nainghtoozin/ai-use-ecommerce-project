<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- payment_status data migration ---
        // unpaid  → pending
        // verified → paid
        // rejected → failed
        DB::table('orders')
            ->where('payment_status', 'unpaid')
            ->update(['payment_status' => 'pending']);

        DB::table('orders')
            ->where('payment_status', 'verified')
            ->update(['payment_status' => 'paid']);

        DB::table('orders')
            ->where('payment_status', 'rejected')
            ->update(['payment_status' => 'failed']);

        // --- order_status data migration ---
        // paid     → confirmed
        // verified → confirmed
        // rejected → cancelled
        DB::table('orders')
            ->where('order_status', 'paid')
            ->update(['order_status' => 'confirmed']);

        DB::table('orders')
            ->where('order_status', 'verified')
            ->update(['order_status' => 'confirmed']);

        DB::table('orders')
            ->where('order_status', 'rejected')
            ->update(['order_status' => 'cancelled']);

        // --- payment_methods.type column ---
        if (!Schema::hasColumn('payment_methods', 'type')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->string('type', 20)->nullable()->after('name');
            });
        }

        // Set type based on existing names
        DB::table('payment_methods')
            ->where('name', 'LIKE', '%COD%')
            ->orWhere('name', 'LIKE', '%Cash on Delivery%')
            ->orWhere('name', 'LIKE', '%cod%')
            ->update(['type' => 'cod']);

        DB::table('payment_methods')
            ->whereNull('type')
            ->update(['type' => 'bank_transfer']);
    }

    public function down(): void
    {
        // Reverse payment_status
        DB::table('orders')
            ->where('payment_status', 'pending')
            ->whereNotIn('payment_method_id', function ($q) {
                $q->select('id')->from('payment_methods')->where('type', 'cod');
            })
            ->update(['payment_status' => 'unpaid']);

        DB::table('orders')
            ->where('payment_status', 'paid')
            ->where('payment_verified_at', '!=', null)
            ->update(['payment_status' => 'verified']);

        DB::table('orders')
            ->where('payment_status', 'failed')
            ->update(['payment_status' => 'rejected']);

        // Reverse order_status
        DB::table('orders')
            ->where('order_status', 'confirmed')
            ->where('payment_verified_at', '!=', null)
            ->update(['order_status' => 'verified']);

        DB::table('orders')
            ->where('order_status', 'cancelled')
            ->where('payment_status', 'failed')
            ->update(['order_status' => 'rejected']);

        // Remove payment_methods.type column
        if (Schema::hasColumn('payment_methods', 'type')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
