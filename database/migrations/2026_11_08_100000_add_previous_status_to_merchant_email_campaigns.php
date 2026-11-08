<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_email_campaigns', function (Blueprint $table) {
            $table->string('previous_status', 30)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_email_campaigns', function (Blueprint $table) {
            $table->dropColumn('previous_status');
        });
    }
};
