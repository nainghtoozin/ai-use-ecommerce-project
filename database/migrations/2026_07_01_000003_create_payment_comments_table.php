<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_intent_id');
            $table->string('author_type');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('author_name');
            $table->text('body');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('payment_intent_id');
            $table->index('author_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_comments');
    }
};
