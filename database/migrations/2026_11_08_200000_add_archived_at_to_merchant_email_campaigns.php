<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_email_campaigns', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('completed_at');
        });

        // Backfill archived status into archived_at
        \Illuminate\Support\Facades\DB::table('merchant_email_campaigns')
            ->where('status', 'archived')
            ->update(['archived_at' => now(), 'status' => \Illuminate\Support\Facades\DB::raw("COALESCE(previous_status, 'completed')")]);
    }

    public function down(): void
    {
        Schema::table('merchant_email_campaigns', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
