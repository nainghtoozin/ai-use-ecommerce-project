<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('fee')->nullable();
            $table->unsignedInteger('min_days')->nullable();
            $table->unsignedInteger('max_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['delivery_service_id', 'city_id'], 'delivery_pricing_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_pricing');
    }
};
