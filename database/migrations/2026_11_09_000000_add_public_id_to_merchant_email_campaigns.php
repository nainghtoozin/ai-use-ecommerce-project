<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_email_campaigns', function (Blueprint $table) {
            $table->string('public_id', 40)->nullable()->unique()->after('id');
        });

        $campaigns = \Illuminate\Support\Facades\DB::table('merchant_email_campaigns')->get();
        foreach ($campaigns as $campaign) {
            if (empty($campaign->public_id)) {
                \Illuminate\Support\Facades\DB::table('merchant_email_campaigns')
                    ->where('id', $campaign->id)
                    ->update(['public_id' => 'eml_' . (string) Str::ulid()]);
            }
        }

        Schema::table('merchant_email_campaigns', function (Blueprint $table) {
            $table->string('public_id', 40)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('merchant_email_campaigns', function (Blueprint $table) {
            $table->dropColumn('public_id');
        });
    }
};
