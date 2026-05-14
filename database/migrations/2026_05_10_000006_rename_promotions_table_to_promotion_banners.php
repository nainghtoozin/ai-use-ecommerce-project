<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('promotions') && !Schema::hasTable('promotion_banners')) {
            Schema::rename('promotions', 'promotion_banners');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('promotion_banners') && !Schema::hasTable('promotions')) {
            Schema::rename('promotion_banners', 'promotions');
        }
    }
};
