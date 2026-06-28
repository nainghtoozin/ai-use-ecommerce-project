<?php

namespace App\Services;

use App\Events\TenantCreated;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\SubscriptionAuditService;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class TenantBootstrapService
{
    /**
     * Full tenant bootstrap.
     *
     * Creates roles, subscription, owner user, assigns permissions,
     * and dispatches TenantCreated event.
     *
     * @param Tenant $tenant  The newly created tenant (must have id)
     * @param array $options  {
     *     @type string  $owner_name      Required to create owner
     *     @type string  $owner_email     Required to create owner
     *     @type string  $owner_password  Required to create owner
     *     @type int     $plan_id         Plan override (default: free plan)
     *     @type string  $status          Subscription status (pending|active)
     *     @type bool    $email_verified  Pre-verify owner email (default: false)
     *     @type bool    $create_owner    Create owner user (default: true)
     * }
     * @return User|null  The created owner user, or null if create_owner is false
     */
    public function bootstrap(Tenant $tenant, array $options = []): ?User
    {
        $steps = ['roles', 'subscription', 'owner'];

        try {
            return DB::transaction(function () use ($tenant, $options) {
                $this->createRoles($tenant);

                $this->createSubscription(
                    $tenant,
                    $options['plan_id'] ?? null,
                    $options['status'] ?? 'pending'
                );

                $createOwner = $options['create_owner'] ?? true;

                if (!$createOwner) {
                    return null;
                }

                $owner = $this->createOwner($tenant, $options);

                $this->assignOwnerRole($owner, $tenant);
                $this->assignOwnerPermissions($owner);

                $this->createDefaultUnits($tenant);
                $this->createDefaultCategories($tenant);
                $this->createDefaultBrands($tenant);
                $this->createDefaultPaymentMethods($tenant);

                TenantCreated::dispatch($tenant, $owner);

                return $owner;
            });
        } catch (\Throwable $e) {
            Log::error('TenantBootstrap failed', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'step' => $steps,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Ensure a customer role exists for a tenant.
     */
    public function ensureCustomerRole(Tenant $tenant): Role
    {
        $role = Role::firstOrCreate([
            'name' => 'customer',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);

        if ($role->wasRecentlyCreated) {
            $globalRole = Role::where('name', 'customer')
                ->whereNull('tenant_id')
                ->first();

            if ($globalRole) {
                $role->syncPermissions($globalRole->permissions);
            }
        }

        return $role;
    }

    /**
     * Create tenant-scoped roles (admin, customer).
     */
    protected function createRoles(Tenant $tenant): void
    {
        foreach (['admin', 'customer'] as $roleName) {
            $this->createRole($tenant, $roleName);
        }
    }

    /**
     * Create a single tenant-scoped role with permissions from the global template.
     */
    protected function createRole(Tenant $tenant, string $roleName): Role
    {
        $role = Role::where('name', $roleName)
            ->where('guard_name', 'web')
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$role) {
            $role = new Role();
            $role->name = $roleName;
            $role->guard_name = 'web';
            $role->tenant_id = $tenant->id;
            $role->save();

            $globalRole = Role::where('name', $roleName)
                ->whereNull('tenant_id')
                ->first();

            if ($globalRole) {
                $role->syncPermissions($globalRole->permissions);
            }
        }

        return $role;
    }

    /**
     * Create the owner user for a tenant.
     */
    protected function createOwner(Tenant $tenant, array $options): User
    {
        $ownerData = [
            'name' => $options['owner_name'],
            'email' => $options['owner_email'],
            'password' => Hash::make($options['owner_password']),
            'status' => User::STATUS_ACTIVE,
        ];

        if (!empty($options['email_verified'])) {
            $ownerData['email_verified_at'] = now();
        }

        $owner = User::create($ownerData);
        $owner->tenant_id = $tenant->id;
        $owner->is_owner = true;
        $owner->save();

        return $owner;
    }

    /**
     * Assign the admin role to the owner.
     */
    protected function assignOwnerRole(User $owner, Tenant $tenant): void
    {
        $adminRole = Role::where('name', 'admin')
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($adminRole) {
            $owner->assignRole($adminRole);
        }
    }

    /**
     * Sync all permissions to the owner.
     */
    protected function assignOwnerPermissions(User $owner): void
    {
        $owner->syncPermissions(Permission::all());
    }

    /**
     * Create a subscription for the tenant.
     */
    protected function createSubscription(Tenant $tenant, ?int $planId = null, string $status = 'pending'): ?Subscription
    {
        $settings = PlatformSetting::current();

        $plan = $this->resolvePlan($planId, $settings);

        if (!$plan) {
            Log::warning('No plan found during tenant bootstrap', [
                'tenant_id' => $tenant->id,
                'plan_id' => $planId,
            ]);
            return null;
        }

        $trialEnabled = $settings->trial_enabled && !$plan->isFree();

        if ($trialEnabled) {
            $trialDays = max(1, $settings->trial_days ?? 14);
            $trialEndsAt = now()->addDays($trialDays);

            $subscription = $tenant->subscription()->create([
                'plan_id' => $plan->id,
                'billing_interval' => $plan->defaultInterval(),
                'status' => 'trialing',
                'starts_at' => now(),
                'trial_ends_at' => $trialEndsAt,
                'expires_at' => $trialEndsAt,
            ]);

            SubscriptionAuditService::log($subscription, 'trial_started', [
                'new_plan_id' => $plan->id,
                'old_status' => null,
            ]);
        } else {
            $startsAt = $status === 'active' ? now() : null;
            $expiresAt = $status === 'active'
                ? $plan->calculateExpiryDate(now(), $plan->defaultInterval())
                : null;

            $subscription = $tenant->subscription()->create([
                'plan_id' => $plan->id,
                'billing_interval' => $plan->defaultInterval(),
                'status' => $status,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
            ]);
        }

        FeatureGate::clearCache($plan);

        return $subscription;
    }

    private function resolvePlan(?int $planId, PlatformSetting $settings): ?Plan
    {
        if ($planId) {
            return Plan::find($planId);
        }

        if ($settings->trial_enabled) {
            return Plan::where('status', 'active')
                ->where('monthly_price', '>', 0)
                ->orderBy('monthly_price')
                ->first() ?? Plan::free();
        }

        return Plan::free();
    }

    protected function createDefaultUnits(Tenant $tenant): void
    {
        $units = [
            ['name' => 'Piece', 'short_name' => 'pcs'],
            ['name' => 'Box', 'short_name' => 'box'],
            ['name' => 'Pack', 'short_name' => 'pk'],
            ['name' => 'Kg', 'short_name' => 'kg'],
            ['name' => 'Gram', 'short_name' => 'g'],
            ['name' => 'Liter', 'short_name' => 'L'],
            ['name' => 'Meter', 'short_name' => 'm'],
        ];

        foreach ($units as $data) {
            $existing = Unit::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->where('name', $data['name'])
                ->first();

            if (!$existing) {
                $unit = new Unit();
                $unit->tenant_id = $tenant->id;
                $unit->name = $data['name'];
                $unit->short_name = $data['short_name'];
                $unit->is_active = true;
                $unit->save();
            }
        }
    }

    protected function createDefaultCategories(Tenant $tenant): void
    {
        $categories = [
            'General', 'Fashion', 'Electronics', 'Beauty',
            'Home & Living', 'Food & Grocery', 'Sports', 'Other',
        ];

        foreach ($categories as $name) {
            $existing = Category::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->where('name', $name)
                ->first();

            if (!$existing) {
                $category = new Category();
                $category->tenant_id = $tenant->id;
                $category->name = $name;
                $category->save();
            }
        }
    }

    protected function createDefaultBrands(Tenant $tenant): void
    {
        $brands = ['Local Made', 'No Brand', 'Imported', 'Custom Brand'];

        foreach ($brands as $name) {
            $existing = Brand::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->where('name', $name)
                ->first();

            if (!$existing) {
                $brand = new Brand();
                $brand->tenant_id = $tenant->id;
                $brand->name = $name;
                $brand->is_active = true;
                $brand->save();
            }
        }
    }

    protected function createDefaultPaymentMethods(Tenant $tenant): void
    {
        $methods = [
            ['name' => 'Cash', 'type' => 'cash'],
            ['name' => 'Cash On Delivery', 'type' => 'cod'],
        ];

        foreach ($methods as $data) {
            $existing = PaymentMethod::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->where('name', $data['name'])
                ->first();

            if (!$existing) {
                $method = new PaymentMethod();
                $method->tenant_id = $tenant->id;
                $method->name = $data['name'];
                $method->type = $data['type'];
                $method->is_active = true;
                $method->save();
            }
        }
    }
}
