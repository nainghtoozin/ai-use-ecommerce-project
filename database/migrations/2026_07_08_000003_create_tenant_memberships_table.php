<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->boolean('is_owner')->default(false);
            $table->string('status', 50)->default('active');
            $table->foreignId('invited_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['account_id', 'tenant_id'], 'tm_account_id_tenant_id_unique');
            $table->index(['tenant_id', 'account_id'], 'tm_tenant_id_account_id_index');
            $table->index(['tenant_id', 'is_owner'], 'tm_tenant_id_is_owner_index');
            $table->index('account_id', 'tm_account_id_index');
            $table->index(['tenant_id', 'status'], 'tm_tenant_id_status_index');
            $table->index('role_id', 'tm_role_id_index');
            $table->index('invited_by', 'tm_invited_by_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_memberships');
    }
};
