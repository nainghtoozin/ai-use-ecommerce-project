<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('type')->default('bank_transfer');
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('branch')->nullable();
            $table->string('qr_image')->nullable();
            $table->text('instructions')->nullable();
            $table->string('currency', 3)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_manual_payment')->default(true);
            $table->boolean('supports_gateway')->default(false);
            $table->string('gateway_code')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payment_methods');
    }
};
