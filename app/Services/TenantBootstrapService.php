<?php

namespace App\Services;

use App\Events\TenantCreated;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
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
        $plan = $planId ? Plan::find($planId) : Plan::free();

        if (!$plan) {
            Log::warning('No plan found during tenant bootstrap', [
                'tenant_id' => $tenant->id,
                'plan_id' => $planId,
            ]);
            return null;
        }

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

        \App\Services\FeatureGate::clearCache($plan);

        return $subscription;
    }
}
