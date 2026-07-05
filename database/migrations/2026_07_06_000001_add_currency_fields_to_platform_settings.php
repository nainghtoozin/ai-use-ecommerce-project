<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('platform_currency_code')->default('MMK')->after('max_trial_renewals');
            $table->string('platform_currency_symbol')->default('Ks')->after('platform_currency_code');
            $table->string('platform_currency_position')->default('before')->after('platform_currency_symbol');
            $table->unsignedTinyInteger('platform_decimal_places')->default(0)->after('platform_currency_position');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['platform_currency_code', 'platform_currency_symbol', 'platform_currency_position', 'platform_decimal_places']);
        });
    }
};
