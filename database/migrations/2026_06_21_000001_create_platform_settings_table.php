<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name')->default('My Application');
            $table->string('site_logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('support_email')->nullable();
            $table->boolean('maintenance_mode')->default(false);
            $table->boolean('registration_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
