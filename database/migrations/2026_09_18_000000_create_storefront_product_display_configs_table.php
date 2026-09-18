<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('storefront_product_display_configs')) {
            Schema::create('storefront_product_display_configs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('storefront_id')->constrained()->cascadeOnDelete();
                $table->json('configuration')->nullable();
                $table->timestamps();
                $table->unique('storefront_id');
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_product_display_configs');
    }
};
