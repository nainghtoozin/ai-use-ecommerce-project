<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->string('carrier_name', 100)->nullable()->after('shipping_responsibility');
            $table->string('tracking_number', 100)->nullable()->after('carrier_name');
            $table->string('tracking_url', 500)->nullable()->after('tracking_number');
            $table->timestamp('shipped_at')->nullable()->after('tracking_url');
            $table->string('tracking_note', 500)->nullable()->after('shipped_at');

            $table->index(['tenant_id', 'tracking_number']);
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'tracking_number']);
            $table->dropColumn(['carrier_name', 'tracking_number', 'tracking_url', 'shipped_at', 'tracking_note']);
        });
    }
};
