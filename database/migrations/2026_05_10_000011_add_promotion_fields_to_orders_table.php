<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'promotion_id')) {
                $table->foreignId('promotion_id')
                    ->nullable()
                    ->constrained('promotions')
                    ->nullOnDelete()
                    ->after('delivery_fee');
            }

            if (!Schema::hasColumn('orders', 'promotion_code')) {
                $table->string('promotion_code', 50)
                    ->nullable()
                    ->index()
                    ->after('promotion_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['promotion_id']);
            $table->dropColumn(['promotion_id', 'promotion_code']);
        });
    }
};
