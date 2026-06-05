<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label')->default('Home');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone', 20);
            $table->text('address_line');
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('township_id')->nullable()->constrained('townships')->nullOnDelete();
            $table->string('postal_code', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
