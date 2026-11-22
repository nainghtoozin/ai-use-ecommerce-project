<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('returns_enabled')->default(true);
            $table->unsignedInteger('return_window_days')->default(7);
            $table->boolean('allow_full_return')->default(true);
            $table->boolean('allow_partial_return')->default(true);
            $table->boolean('require_reason')->default(true);
            $table->boolean('require_note')->default(false);
            $table->boolean('require_evidence')->default(false);
            $table->boolean('allow_refund')->default(true);
            $table->boolean('allow_exchange')->default(false);
            $table->boolean('allow_replacement')->default(false);
            $table->string('return_shipping_responsibility', 20)->default('customer');
            $table->json('shipping_responsibility_by_reason')->nullable();
            $table->boolean('refund_shipping_fee')->default(false);
            $table->json('reasons')->nullable();
            $table->text('instructions')->nullable();
            $table->text('policy_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_settings');
    }
};
