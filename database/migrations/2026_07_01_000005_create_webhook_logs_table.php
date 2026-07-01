<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            $table->string('event_type')->nullable();
            $table->string('gateway_event_id')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->string('status');
            $table->json('request_headers')->nullable();
            $table->longText('request_payload')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('gateway');
            $table->index('gateway_event_id');
            $table->index('status');
            $table->index('payment_intent_id');
            $table->index(['gateway', 'gateway_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
