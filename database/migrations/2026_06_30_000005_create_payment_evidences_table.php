<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_evidences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_intent_id');
            $table->string('type');
            $table->string('file_path')->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('payment_intent_id');
            $table->foreign('payment_intent_id')
                ->references('id')
                ->on('payment_intents')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_evidences');
    }
};
