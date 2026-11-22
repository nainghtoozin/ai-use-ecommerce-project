<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('refund_number', 30);
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('method', 30)->default('manual');
            $table->string('status', 20)->default('pending');
            $table->string('reference')->nullable();
            $table->string('evidence_path')->nullable();
            $table->text('notes')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('requested_by_type')->nullable();
            $table->unsignedBigInteger('requested_by_id')->nullable();
            $table->string('processed_by_type')->nullable();
            $table->unsignedBigInteger('processed_by_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'refund_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['order_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
    }
};
