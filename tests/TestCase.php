<?php

namespace Tests;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Give a tenant the entitlement every provisioning path creates.
     *
     * Public storefront traffic fails closed when a tenant has no subscription
     * row, so a bare `Tenant::create()` fixture no longer represents a live
     * store. A free plan grants service unconditionally, which keeps existing
     * storefront/checkout/registration assertions meaningful without inventing
     * billing state.
     */
    protected function grantStorefrontEntitlement(Tenant $tenant): Subscription
    {
        $plan = Plan::firstOrCreate(
            ['slug' => 'test-storefront-entitlement'],
            [
                'name' => 'Test Storefront Entitlement',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'product_limit' => 1000,
                'staff_limit' => 100,
                'status' => 'active',
            ]
        );

        return Subscription::forceCreate([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'expires_at' => null,
        ]);
    }
}
