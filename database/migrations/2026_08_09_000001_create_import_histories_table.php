<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_name');
            $table->string('file_type', 10)->default('xlsx');
            $table->string('import_type', 50)->default('products');
            $table->string('status', 30)->default('pending');
            $table->string('import_mode', 30)->nullable();
            $table->integer('total_rows')->default(0);
            $table->integer('total_products')->default(0);
            $table->integer('total_variants')->default(0);
            $table->integer('products_created')->default(0);
            $table->integer('products_skipped')->default(0);
            $table->integer('variants_created')->default(0);
            $table->integer('warning_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->string('error_report_path')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_histories');
    }
};
