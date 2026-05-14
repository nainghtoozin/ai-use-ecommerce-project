<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')
                ->constrained('promotions')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->timestamp('used_at')->useCurrent();

            $table->index('user_id');
            $table->index('order_id');
            $table->index(['promotion_id', 'user_id'], 'promo_user_usage_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_usages');
    }
};
