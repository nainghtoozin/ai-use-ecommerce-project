<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_infos', function (Blueprint $table) {
            if (!Schema::hasColumn('website_infos', 'about_image')) {
                $table->string('about_image')->nullable()->after('about_description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('website_infos', function (Blueprint $table) {
            if (Schema::hasColumn('website_infos', 'about_image')) {
                $table->dropColumn('about_image');
            }
        });
    }
};
