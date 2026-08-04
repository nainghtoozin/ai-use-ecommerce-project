<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('category')->default('general');
            $table->string('question_en', 500);
            $table->string('question_my', 500)->nullable();
            $table->text('answer_en');
            $table->text('answer_my')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order']);
            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_faqs');
    }
};
