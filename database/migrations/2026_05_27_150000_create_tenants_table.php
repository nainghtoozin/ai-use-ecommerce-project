<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->unique()->nullable();
            $table->string('email')->nullable();
            $table->string('logo')->nullable();
            $table->string('status')->default('active');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        // Create default tenant for existing single-store data
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
        DB::table('tenants')->insert([
            'name' => 'Default Store',
            'slug' => 'default',
            'domain' => $host,
            'email' => config('app.name') . '@example.com',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
