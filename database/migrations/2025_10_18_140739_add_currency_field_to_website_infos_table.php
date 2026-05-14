<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('website_infos', function (Blueprint $table) {
            $column = $table->string('currency', 10)
                  ->nullable()
                  ->after('terms_service_description');

            if (config('database.default') !== 'sqlite') {
                $column->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_infos', function (Blueprint $table) {
            //
        });
    }
};
