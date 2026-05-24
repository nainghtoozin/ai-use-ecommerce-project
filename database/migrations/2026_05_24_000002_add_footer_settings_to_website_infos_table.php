<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_infos', function (Blueprint $table) {
            $table->json('footer_settings')->nullable()->after('footer_copyright');
        });

        DB::table('website_infos')->orderBy('id')->each(function ($row) {
            $settings = [
                'description' => $row->footer_description ?? '',
                'extra_text' => '',
                'show_contact_button' => true,
                'show_social_icons' => true,
                'compact_mode' => true,
            ];

            DB::table('website_infos')
                ->where('id', $row->id)
                ->update(['footer_settings' => json_encode($settings)]);
        });
    }

    public function down(): void
    {
        Schema::table('website_infos', function (Blueprint $table) {
            $table->dropColumn('footer_settings');
        });
    }
};
