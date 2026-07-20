<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('pending_plan_id')->nullable()->constrained('plans')->nullOnDelete()->after('plan_id');
            $table->timestamp('pending_plan_effective_at')->nullable()->after('pending_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['pending_plan_id', 'pending_plan_effective_at']);
        });
    }
};
