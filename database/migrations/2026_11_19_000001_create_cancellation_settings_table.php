<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('customer_cancel_enabled')->default(true);
            $table->boolean('merchant_cancel_enabled')->default(true);
            $table->json('status_rules')->nullable();
            $table->unsignedInteger('instant_cancel_window_hours')->nullable()->default(24);
            $table->unsignedInteger('max_cancellations')->nullable()->default(3);
            $table->unsignedInteger('limit_period_days')->default(30);
            $table->string('limit_exceeded_action', 20)->default('require_approval');
            $table->boolean('allow_request_after_limit')->default(true);
            $table->boolean('require_reason')->default(true);
            $table->boolean('require_note')->default(false);
            $table->json('reasons')->nullable();
            $table->boolean('refund_shipping_fee')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_settings');
    }
};
