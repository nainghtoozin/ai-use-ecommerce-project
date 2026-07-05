<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_infos', function (Blueprint $table) {
            $table->string('currency_position')->default('before')->after('currency_symbol');
            $table->unsignedTinyInteger('decimal_places')->default(0)->after('currency_position');
        });
    }

    public function down(): void
    {
        Schema::table('website_infos', function (Blueprint $table) {
            $table->dropColumn(['currency_position', 'decimal_places']);
        });
    }
};
