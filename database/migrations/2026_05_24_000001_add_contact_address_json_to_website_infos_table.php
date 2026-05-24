<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_infos', function (Blueprint $table) {
            $table->json('contact_info')->nullable()->after('google_maps_embed_url');
            $table->json('address_info')->nullable()->after('contact_info');
        });

        DB::table('website_infos')->orderBy('id')->each(function ($row) {
            $contactInfo = [
                'primary_phone' => $row->phone ?? '',
                'secondary_phone' => '',
                'support_email' => $row->support_email ?? '',
                'sales_email' => '',
                'contact_email' => $row->contact_email ?? '',
                'whatsapp_number' => $row->whatsapp_number ?? '',
                'telegram_username' => '',
            ];

            $addressInfo = [
                'address_line_1' => $row->address ?? '',
                'address_line_2' => '',
                'city' => '',
                'state_region' => '',
                'postal_code' => '',
                'country' => $row->country ?? '',
                'google_maps_link' => $row->google_maps_embed_url ?? '',
            ];

            DB::table('website_infos')
                ->where('id', $row->id)
                ->update([
                    'contact_info' => json_encode($contactInfo),
                    'address_info' => json_encode($addressInfo),
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('website_infos', function (Blueprint $table) {
            $table->dropColumn(['contact_info', 'address_info']);
        });
    }
};
