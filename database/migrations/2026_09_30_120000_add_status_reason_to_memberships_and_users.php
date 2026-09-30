<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->text('status_reason')->nullable()->after('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('status_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->dropColumn('status_reason');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status_reason');
        });
    }
};
