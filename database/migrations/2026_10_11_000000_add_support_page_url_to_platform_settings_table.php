<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('platform_settings', 'support_page_url')) {
            Schema::table('platform_settings', function (Blueprint $table) {
                $table->string('support_page_url', 2048)->nullable()->after('support_email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('platform_settings', 'support_page_url')) {
            Schema::table('platform_settings', function (Blueprint $table) {
                $table->dropColumn('support_page_url');
            });
        }
    }
};
