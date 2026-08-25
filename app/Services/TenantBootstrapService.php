<?php

namespace App\Services;

use App\Events\TenantCreated;
use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WebsiteFaq;
use App\Services\SubscriptionAuditService;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TenantBootstrapService
{
    /**
     * Full tenant bootstrap.
     *
     * Creates roles, subscription, owner user/account, assigns permissions,
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
     * @return User|Account|null  The created owner user or account, or null
     */
    public function bootstrap(Tenant $tenant, array $options = []): User|Account|null
    {
        $steps = ['roles', 'subscription', 'owner', 'defaults'];

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

                $useAccounts = config('identity.use_accounts');

                if ($useAccounts) {
                    $owner = $this->createOwnerAccount($tenant, $options);
                } else {
                    $owner = $this->createOwner($tenant, $options);
                }

                $this->assignOwnerRole($owner, $tenant);

                $this->createDefaultPaymentMethods($tenant);
                $this->createDefaultWarehouse($tenant);
                $this->seedDefaultFaqs($tenant);

                if (Schema::hasTable('storefronts')) {
                    app(StorefrontConfigurationResolver::class)->provision($tenant);
                }

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
        $role = Role::withoutTenantScope()->firstOrCreate([
            'name' => 'customer',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);

        if ($role->wasRecentlyCreated) {
            $globalRole = Role::withoutTenantScope()
                ->where('name', 'customer')
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
        $role = Role::withoutTenantScope()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$role) {
            $role = new Role();
            $role->name = $roleName;
            $role->guard_name = 'web';
            $role->tenant_id = $tenant->id;
            $role->save();

            // Query global template without tenant scope to find tenant_id=NULL
            $globalRole = Role::withoutTenantScope()
                ->where('name', $roleName)
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
     * Create the owner account for a tenant (identity architecture).
     *
     * If an Account with the same email already exists, it is reused.
     * Only the TenantMembership is created (or updated to owner).
     */
    protected function createOwnerAccount(Tenant $tenant, array $options): Account
    {
        $existingAccount = Account::where('email', $options['owner_email'])->first();

        if ($existingAccount) {
            // Reuse existing Account — update name/password if provided
            $updateData = [
                'name' => $options['owner_name'] ?? $existingAccount->name,
                'status' => Account::STATUS_ACTIVE,
            ];

            if (!empty($options['owner_password'])) {
                $updateData['password'] = Hash::make($options['owner_password']);
            }

            $existingAccount->update($updateData);

            if (!empty($options['email_verified'])) {
                $existingAccount->markEmailAsVerified();
            }

            $owner = $existingAccount;
        } else {
            $ownerData = [
                'name' => $options['owner_name'],
                'email' => $options['owner_email'],
                'password' => Hash::make($options['owner_password']),
                'status' => Account::STATUS_ACTIVE,
            ];

            if (!empty($options['email_verified'])) {
                $ownerData['email_verified_at'] = now();
            }

            $owner = Account::create($ownerData);
        }

        $adminRole = Role::withoutTenantScope()
            ->where('name', 'admin')
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$adminRole) {
            throw new \RuntimeException(
                "Owner role 'admin' was not created for tenant '{$tenant->slug}'. Cannot create owner membership."
            );
        }

        // Create or update membership — set as owner
        TenantMembership::updateOrCreate(
            [
                'account_id' => $owner->id,
                'tenant_id' => $tenant->id,
            ],
            [
                'role_id' => $adminRole->id,
                'is_owner' => true,
                'status' => 'active',
                'joined_at' => now(),
            ]
        );

        return $owner;
    }

    /**
     * Assign the admin role to the owner.
     */
    protected function assignOwnerRole(User|Account $owner, Tenant $tenant): void
    {
        $adminRole = Role::withoutTenantScope()
            ->where('name', 'admin')
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$adminRole) {
            throw new \RuntimeException(
                "Owner role 'admin' was not created for tenant '{$tenant->slug}'. Cannot assign owner role."
            );
        }

        $owner->assignRole($adminRole);
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
        $existing = $tenant->subscription()->first();
        if ($existing) {
            Log::info('Subscription already exists for tenant, skipping creation', [
                'tenant_id' => $tenant->id,
                'existing_subscription_id' => $existing->id,
                'existing_status' => $existing->status,
            ]);
            return $existing;
        }

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

            // Update tenant with subscription details
            $tenant->update([
                'subscription_plan_id' => $plan->id,
                'expires_at' => $trialEndsAt,
                'status' => 'trialing',
                'activated_at' => now(),
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

            // Update tenant with subscription details
            $tenantUpdate = [
                'subscription_plan_id' => $plan->id,
                'status' => $status,
            ];

            if ($expiresAt) {
                $tenantUpdate['expires_at'] = $expiresAt;
            }

            if ($status === 'active') {
                $tenantUpdate['activated_at'] = now();
            }

            $tenant->update($tenantUpdate);
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

    protected function createDefaultWarehouse(Tenant $tenant): void
    {
        $existing = Warehouse::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('is_default', true)
            ->first();

        if (!$existing) {
            $warehouse = new Warehouse();
            $warehouse->tenant_id = $tenant->id;
            $warehouse->name = $tenant->name . ' - Main Warehouse';
            $warehouse->code = 'MAIN';
            $warehouse->description = 'Primary inventory location';
            $warehouse->is_default = true;
            $warehouse->is_active = true;
            $warehouse->save();
        }
    }

    protected function seedDefaultFaqs(Tenant $tenant): void
    {
        $existingCount = WebsiteFaq::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->count();

        if ($existingCount > 0) {
            return;
        }

        $faqs = [
            [
                'category' => 'getting_started',
                'question_en' => 'How do I place an order?',
                'question_my' => 'ကျွန်ုပ် ဘယ်လိုမှာယူရမလဲ?',
                'answer_en' => '<p>To place an order, browse our products, add items to your cart, and proceed to checkout. You can pay using various payment methods including cash on delivery, mobile banking, and bank transfer.</p>',
                'answer_my' => '<p>မှာယူရန် ကျွန်ုပ်တို့၏ ထုတ်ကုန်များကို ရှာဖွေပြီး သင့်စျေးခြင်းထဲသို့ ထည့်ပါ။ ထို့နောက် checkout သို့ သွားပါ။</p>',
                'sort_order' => 1,
            ],
            [
                'category' => 'billing',
                'question_en' => 'What payment methods do you accept?',
                'question_my' => 'ဘယ်လိုငွေပေးချေမှုနည်းလမ်းတွေကို လက်ခံပါသလဲ?',
                'answer_en' => '<p>We accept various payment methods including Cash on Delivery (COD), mobile banking (KBZPay, WavePay, AYA Pay), bank transfers, and more.</p>',
                'answer_my' => '<p>ကျွန်ုပ်တို့သည် ငွေသားဖြင့်ပေးချေမှု (COD)၊ မိုဘိုင်းဘဏ်၊ ဘဏ်လွှဲပြောင်းမှု အပါအဝင် ငွေပေးချေမှုနည်းလမ်းများစွာကို လက်ခံပါသည်။</p>',
                'sort_order' => 2,
            ],
            [
                'category' => 'shipping',
                'question_en' => 'How long does shipping take?',
                'question_my' => 'ပို့ဆောင်မှု ဘယ်လောက်ကြာသလဲ?',
                'answer_en' => '<p>Shipping times vary depending on your location. Typically, orders are delivered within 2-5 business days for major cities and 5-10 business days for remote areas.</p>',
                'answer_my' => '<p>ပို့ဆောင်ချိန်သည် သင့်တည်နေရာပေါ်မူတည်ပါသည်။ ပုံမှန်အားဖြင့် အဓိကမြို့ကြီးများအတွက် 2-5 လုပ်ငန်းရက်အတွင်း ပို့ဆောင်ပေးပါသည်။</p>',
                'sort_order' => 3,
            ],
            [
                'category' => 'returns',
                'question_en' => 'What is your return policy?',
                'question_my' => 'သင်တို့၏ ပြန်ပေးမူဝါဒက ဘာလဲ?',
                'answer_en' => '<p>We offer a 7-day return policy for most items. Products must be in their original condition with all tags attached.</p>',
                'answer_my' => '<p>ကျွန်ုပ်တို့သည် ပစ္စည်းအများစုအတွက် ရက် ၇ ပြန်ပေးမူဝါဒကို ပေးပါသည်။</p>',
                'sort_order' => 4,
            ],
            [
                'category' => 'support',
                'question_en' => 'How can I contact customer support?',
                'question_my' => 'ဖောက်သည်ပံ့ပိုးမှုကို ဘယ်လိုဆက်သွယ်ရမလဲ?',
                'answer_en' => '<p>You can reach our customer support team through the Contact Us page on our website. We typically respond within 24 hours during business days.</p>',
                'answer_my' => '<p>ကျွန်ုပ်တို့၏ ဖောက်သည်ပံ့ပိုးမှုအဖွဲ့ကို ကျွန်ုပ်တို့၏ ဝက်ဘ်ဆိုက်ရှိ ဆက်သွယ်ရန် စာမျက်နှာမှတစ်ဆင့် ဆက်သွယ်နိုင်ပါသည်။</p>',
                'sort_order' => 5,
            ],
        ];

        foreach ($faqs as $faqData) {
            WebsiteFaq::withoutTenantScope()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'question_en' => $faqData['question_en']],
                [
                    'category' => $faqData['category'],
                    'question_my' => $faqData['question_my'],
                    'answer_en' => $faqData['answer_en'],
                    'answer_my' => $faqData['answer_my'],
                    'sort_order' => $faqData['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
