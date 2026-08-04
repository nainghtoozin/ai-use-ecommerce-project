<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('category')->default('general');
            $table->string('question_en');
            $table->string('question_my')->nullable();
            $table->text('answer_en');
            $table->text('answer_my')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_faqs');
    }
};
