<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_intent_id');
            $table->string('action');
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->string('reviewer_name')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('payment_intent_id');
            $table->index('action');
            $table->foreign('payment_intent_id')
                ->references('id')
                ->on('payment_intents')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reviews');
    }
};
