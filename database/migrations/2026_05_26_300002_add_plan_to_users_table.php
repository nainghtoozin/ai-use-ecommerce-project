<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->timestamp('plan_started_at')->nullable()->after('plan_id');
            $table->timestamp('plan_expires_at')->nullable()->after('plan_started_at');
            $table->string('plan_status')->default('active')->after('plan_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['plan_id', 'plan_started_at', 'plan_expires_at', 'plan_status']);
        });
    }
};
