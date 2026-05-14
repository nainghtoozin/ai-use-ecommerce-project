<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_category', function (Blueprint $table) {
            $table->foreignId('promotion_id')
                ->constrained('promotions')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->primary(['promotion_id', 'category_id']);

            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_category');
    }
};
