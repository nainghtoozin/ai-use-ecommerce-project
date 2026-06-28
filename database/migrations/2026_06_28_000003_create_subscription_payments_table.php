<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('gateway');
            $table->string('gateway_transaction_id')->nullable();
            $table->string('status');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('billing_interval');
            $table->json('gateway_response')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index('subscription_id');
            $table->index('tenant_id');
            $table->index('gateway');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
