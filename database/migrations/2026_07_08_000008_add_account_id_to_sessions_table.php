<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('user_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('current_tenant_membership_id')->nullable()->after('account_id')->constrained('tenant_memberships')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_tenant_membership_id');
            $table->dropConstrainedForeignId('account_id');
        });
    }
};
