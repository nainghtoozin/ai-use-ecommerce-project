<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('rma_number', 30);
            $table->string('requested_by_type')->nullable();
            $table->unsignedBigInteger('requested_by_id')->nullable();
            $table->string('status', 20)->default('requested');
            $table->string('resolution', 20)->default('refund');
            $table->string('reason_code', 50)->nullable();
            $table->string('reason_label')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('merchant_note')->nullable();
            $table->json('evidence')->nullable();
            $table->string('shipping_responsibility', 20)->default('customer');
            $table->decimal('shipping_fee_amount', 10, 2)->default(0);
            $table->boolean('refund_shipping_fee')->default(false);
            $table->json('policy_snapshot')->nullable();
            $table->string('decision_by_type')->nullable();
            $table->unsignedBigInteger('decision_by_id')->nullable();
            $table->text('decision_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('refund_id')->nullable()->constrained('order_refunds')->nullOnDelete();
            $table->unsignedBigInteger('replacement_order_id')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'rma_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['order_id', 'status']);
            $table->index('replacement_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_returns');
    }
};
