<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status', 'subscriptions_status_index');
            $table->index(['status', 'expires_at'], 'subscriptions_status_expires_at_index');
        });

        $this->backfillExistingTenants();
    }

    private function ensureFreePlanExists(): int
    {
        $existing = DB::table('plans')->where('slug', 'free')->value('id');

        if ($existing) {
            return $existing;
        }

        return DB::table('plans')->insertGetId([
            'name' => 'Free',
            'slug' => 'free',
            'description' => 'Free plan — basic features to get started',
            'monthly_price' => null,
            'yearly_price' => null,
            'product_limit' => 10,
            'staff_limit' => 1,
            'storage_limit' => 100,
            'analytics_enabled' => false,
            'custom_domain_enabled' => false,
            'status' => 'active',
            'price' => 0,
            'currency' => 'USD',
            'interval' => 'monthly',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function backfillExistingTenants(): void
    {
        $freePlanId = $this->ensureFreePlanExists();

        $tenants = DB::table('tenants')
            ->leftJoin('subscriptions', 'tenants.id', '=', 'subscriptions.tenant_id')
            ->whereNull('subscriptions.id')
            ->select('tenants.*')
            ->get();

        foreach ($tenants as $tenant) {
            $planId = $tenant->subscription_plan_id ?? $freePlanId;

            if ($tenant->status === 'trialing') {
                $status = 'trialing';
            } elseif ($tenant->expires_at && Carbon::parse($tenant->expires_at)->isPast()) {
                $status = 'expired';
            } else {
                $status = 'active';
            }

            DB::table('subscriptions')->insert([
                'tenant_id' => $tenant->id,
                'plan_id' => $planId,
                'status' => $status,
                'starts_at' => $tenant->created_at ?? now(),
                'expires_at' => $tenant->expires_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
