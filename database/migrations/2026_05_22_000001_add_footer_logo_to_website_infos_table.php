<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('website_infos', 'footer_logo')) {
            Schema::table('website_infos', function (Blueprint $table) {
                $table->string('footer_logo')->nullable()->after('footer_copyright');
            });
        }
    }

    public function down(): void
    {
        Schema::table('website_infos', function (Blueprint $table) {
            $table->dropColumn('footer_logo');
        });
    }
};
