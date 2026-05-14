<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique()->nullable()->index();
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed', 'free_shipping'])->default('percentage');
            $table->decimal('value', 10, 2)->default(0);
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->decimal('minimum_order_amount', 10, 2)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable()->index();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_automatic')->default(false)->index();
            $table->enum('applies_to', ['all', 'products', 'categories'])->default('all');
            $table->integer('priority')->default(0);
            $table->boolean('stackable')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at'], 'promotions_active_range_idx');
            $table->index(['is_automatic', 'is_active'], 'promotions_auto_active_idx');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
