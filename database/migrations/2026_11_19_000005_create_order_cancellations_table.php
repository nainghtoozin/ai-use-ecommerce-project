<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('cancellation_number', 30);
            $table->string('requested_by_type')->nullable();
            $table->unsignedBigInteger('requested_by_id')->nullable();
            $table->string('requested_by_role', 20)->default('customer');
            $table->string('reason_code', 50)->nullable();
            $table->string('reason_label')->nullable();
            $table->text('customer_note')->nullable();
            $table->string('status', 20)->default('requested');
            $table->string('mode', 20)->default('instant');
            $table->string('decision_by_type')->nullable();
            $table->unsignedBigInteger('decision_by_id')->nullable();
            $table->text('decision_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('refund_required')->default(false);
            $table->foreignId('refund_id')->nullable()->constrained('order_refunds')->nullOnDelete();
            $table->timestamp('stock_restored_at')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('policy_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'cancellation_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['order_id', 'status']);
            $table->index(['tenant_id', 'requested_by_type', 'requested_by_id', 'status'], 'order_cancellations_requester_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_cancellations');
    }
};
