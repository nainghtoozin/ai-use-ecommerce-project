<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_infos', function (Blueprint $table) {
            $table->text('privacy_policy')->nullable()->after('footer_settings');
            $table->text('terms_conditions')->nullable()->after('privacy_policy');
            $table->text('shipping_policy')->nullable()->after('terms_conditions');
            $table->text('return_policy')->nullable()->after('shipping_policy');
            $table->text('refund_policy')->nullable()->after('return_policy');
        });
    }

    public function down(): void
    {
        Schema::table('website_infos', function (Blueprint $table) {
            $table->dropColumn([
                'privacy_policy',
                'terms_conditions',
                'shipping_policy',
                'return_policy',
                'refund_policy',
            ]);
        });
    }
};
