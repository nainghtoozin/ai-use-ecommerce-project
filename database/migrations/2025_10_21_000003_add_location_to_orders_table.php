<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'city_id')) {
                $table->foreignId('city_id')->nullable()->after('postal_code')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'township_id')) {
                $table->foreignId('township_id')->nullable()->after('city_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'delivery_fee')) {
                $table->decimal('delivery_fee', 10, 2)->nullable()->after('shipping_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropForeign(['township_id']);
            $table->dropColumn(['city_id', 'township_id', 'delivery_fee']);
        });
    }
};
