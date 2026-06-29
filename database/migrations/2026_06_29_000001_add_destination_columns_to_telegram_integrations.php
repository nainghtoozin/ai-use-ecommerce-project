<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_integrations', function (Blueprint $table) {
            $table->string('default_destination', 20)->default('personal')->after('group_verified_at');
            $table->string('order_destination', 20)->nullable()->after('default_destination');
            $table->string('payment_destination', 20)->nullable()->after('order_destination');
            $table->string('inventory_destination', 20)->nullable()->after('payment_destination');
            $table->string('system_destination', 20)->nullable()->after('inventory_destination');
            $table->string('marketing_destination', 20)->nullable()->after('system_destination');
            $table->string('manual_destination', 20)->nullable()->after('marketing_destination');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_integrations', function (Blueprint $table) {
            $table->dropColumn([
                'default_destination',
                'order_destination',
                'payment_destination',
                'inventory_destination',
                'system_destination',
                'marketing_destination',
                'manual_destination',
            ]);
        });
    }
};
