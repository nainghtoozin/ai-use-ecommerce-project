<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OnboardingStoreSetupTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createOnboardingSchema();
        PlatformSetting::clearCache();
    }

    protected function tearDown(): void
    {
        PlatformSetting::clearCache();

        parent::tearDown();
    }

    public function test_create_store_redirects_to_success_with_starter_trial(): void
    {
        Plan::updateOrCreate(['slug' => 'free'], [
            'name' => 'Starter', 'monthly_price' => 0, 'yearly_price' => 0, 'status' => 'active',
        ]);
        PlatformSetting::query()->delete();
        PlatformSetting::create(['trial_enabled' => true, 'trial_days' => 14]);
        PlatformSetting::clearCache();

        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $slug = 'teststore-' . uniqid();

        $response = $this->post('/onboarding/store-setup', [
            'store_name' => 'Test Store ' . uniqid(),
            'store_slug' => $slug,
            'business_email' => 'biz-' . uniqid() . '@test.com',
            'phone' => '09123456789',
            'language' => 'en',
            'currency' => 'MMK',
            'timezone' => 'Asia/Yangon',
            'country' => 'MM',
            'theme_color' => '#6366F1',
            'description' => 'A test store.',
        ]);

        $response->assertRedirect(route('onboarding.success', ['store_slug' => $slug]));

        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $subscription = $tenant->subscription()->firstOrFail();

        $this->assertSame('trialing', $subscription->status);
        $this->assertSame('free', $subscription->plan->slug);
        $this->assertSame(
            now()->addDays(14)->toDateString(),
            $subscription->trial_ends_at->toDateString()
        );
    }

    public function test_create_store_as_verified_account_redirects_to_success(): void
    {
        Plan::updateOrCreate(['slug' => 'free'], [
            'name' => 'Starter', 'monthly_price' => 0, 'yearly_price' => 0, 'status' => 'active',
        ]);
        PlatformSetting::query()->delete();
        PlatformSetting::create(['trial_enabled' => true, 'trial_days' => 14]);
        PlatformSetting::clearCache();

        $account = \App\Models\Account::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($account, 'accounts');

        $slug = 'acctest-' . uniqid();

        $response = $this->post('/onboarding/store-setup', [
            'store_name' => 'Acct Store ' . uniqid(),
            'store_slug' => $slug,
            'business_email' => 'biz-' . uniqid() . '@test.com',
            'phone' => '09123456789',
            'language' => 'en',
            'currency' => 'MMK',
            'timezone' => 'Asia/Yangon',
            'country' => 'MM',
            'theme_color' => '#6366F1',
            'description' => 'A test store.',
        ]);

        $response->assertRedirect(route('onboarding.success', ['store_slug' => $slug]));

        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->assertSame('trialing', $tenant->subscription()->firstOrFail()->status);
    }

    private function createOnboardingSchema(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('status')->default('active');
                $table->boolean('is_owner')->default(false);
                $table->boolean('is_admin')->default(false);
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function ($table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('status')->default('pending');
                $table->string('store_url')->nullable();
                $table->json('settings')->nullable();
                $table->unsignedBigInteger('subscription_plan_id')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('plans')) {
            Schema::create('plans', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->decimal('monthly_price', 10, 2)->nullable();
                $table->decimal('yearly_price', 10, 2)->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('billing_interval')->nullable();
                $table->string('status');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->integer('trial_renewals_count')->default(0);
                $table->unsignedBigInteger('pending_plan_id')->nullable();
                $table->timestamp('pending_plan_effective_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('extra_renewal_used_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index('tenant_id');
            });
        } elseif (!Schema::hasColumn('subscriptions', 'extra_renewal_used_at')) {
            Schema::table('subscriptions', function ($table) {
                $table->timestamp('extra_renewal_used_at')->nullable();
            });
        }

        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->timestamps();
                $table->unique(['name', 'guard_name', 'tenant_id']);
            });
        }

        if (!Schema::hasTable('website_infos')) {
            Schema::create('website_infos', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('site_name')->nullable();
                $table->text('site_description')->nullable();
                $table->string('theme_color')->nullable();
                $table->string('default_language')->nullable();
                $table->string('timezone')->nullable();
                $table->string('currency_code')->nullable();
                $table->string('country')->nullable();
                $table->string('contact_email')->nullable();
                $table->string('phone')->nullable();
                $table->boolean('allow_registration')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function ($table) {
                $table->id();
                $table->string('site_name')->nullable();
                $table->string('support_email')->nullable();
                $table->boolean('trial_enabled')->default(true);
                $table->integer('trial_days')->default(14);
                $table->boolean('allow_trial_renewal')->default(false);
                $table->integer('max_trial_renewals')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tenant_memberships')) {
            Schema::create('tenant_memberships', function ($table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('role_id')->nullable();
                $table->boolean('is_owner')->default(false);
                $table->string('status')->default('active');
                $table->timestamp('joined_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'account_id']);
            });
        }

        if (!Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function ($table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
            });
        }

        if (!Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('type')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('warehouses')) {
            Schema::create('warehouses', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('website_faqs')) {
            Schema::create('website_faqs', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('category')->nullable();
                $table->text('question_en')->nullable();
                $table->text('question_my')->nullable();
                $table->text('answer_en')->nullable();
                $table->text('answer_my')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('subscription_audit_logs')) {
            Schema::create('subscription_audit_logs', function ($table) {
                $table->id();
                $table->unsignedBigInteger('subscription_id');
                $table->unsignedBigInteger('tenant_id');
                $table->string('event');
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->unsignedBigInteger('old_plan_id')->nullable();
                $table->unsignedBigInteger('new_plan_id')->nullable();
                $table->string('old_status')->nullable();
                $table->string('new_status')->nullable();
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('storefront_product_display_configs')) {
            Schema::create('storefront_product_display_configs', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('storefront_id')->unique();
                $table->json('configuration')->nullable();
                $table->timestamps();
                $table->index('tenant_id');
            });
        }
    }

    public function test_store_creation_attaches_legacy_user_to_tenant(): void
    {
        $this->seedStarterPlan();

        $legacyUser = User::create([
            'name' => 'Legacy Merchant',
            'email' => 'legacy-owner-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->assertNull($legacyUser->tenant_id);

        $account = \App\Models\Account::where('email', $legacyUser->email)->firstOrFail();
        $this->actingAs($account, 'accounts');

        $slug = 'legacy-' . uniqid();
        $response = $this->post('/onboarding/store-setup', $this->storePayload($slug, 'Legacy Store'));

        $response->assertRedirect(route('onboarding.success', ['store_slug' => $slug]));

        $tenant = Tenant::where('slug', $slug)->firstOrFail();

        $this->assertSame($tenant->id, (int) $legacyUser->fresh()->tenant_id);
        $this->assertTrue(
            \App\Models\TenantMembership::where('tenant_id', $tenant->id)
                ->where('account_id', $account->id)
                ->where('is_owner', true)
                ->exists()
        );
    }

    public function test_owner_user_can_access_new_store(): void
    {
        $this->seedStarterPlan();

        $legacyUser = User::create([
            'name' => 'Store Owner',
            'email' => 'owner-access-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $account = \App\Models\Account::where('email', $legacyUser->email)->firstOrFail();

        $this->actingAs($legacyUser);
        $slug = 'access-' . uniqid();
        $this->post('/onboarding/store-setup', $this->storePayload($slug, 'Access Store'))
            ->assertRedirect(route('onboarding.success', ['store_slug' => $slug ]));

        $this->actingAs($legacyUser->fresh());

        $this->get("/store/{$slug}/login")->assertOk();

        $response = $this->get("/store/{$slug}");
        $this->assertNotSame(403, $response->getStatusCode());
    }

    public function test_unrelated_user_still_receives_403_on_store(): void
    {
        $this->seedStarterPlan();

        $owner = User::create([
            'name' => 'Real Owner',
            'email' => 'real-owner-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($owner);
        $slug = 'guarded-' . uniqid();
        $this->post('/onboarding/store-setup', $this->storePayload($slug, 'Guarded Store'))
            ->assertRedirect(route('onboarding.success', ['store_slug' => $slug]));

        $stranger = User::create([
            'name' => 'Stranger',
            'email' => 'stranger-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($stranger);

        $this->get("/store/{$slug}")->assertForbidden();
        $this->get("/store/{$slug}/login")->assertForbidden();
    }

    public function test_existing_tenant_mapping_is_never_overwritten(): void
    {
        $this->seedStarterPlan();

        $otherTenant = Tenant::create([
            'slug' => 'other-' . uniqid(), 'name' => 'Other', 'status' => 'active',
        ]);

        $legacyUser = User::create([
            'name' => 'Attached Merchant',
            'email' => 'attached-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
            'tenant_id' => $otherTenant->id,
        ]);

        $account = \App\Models\Account::where('email', $legacyUser->email)->firstOrFail();
        $this->actingAs($account, 'accounts');
        $slug = 'second-' . uniqid();
        $this->post('/onboarding/store-setup', $this->storePayload($slug, 'Second Store'))
            ->assertRedirect(route('onboarding.success', ['store_slug' => $slug]));

        $this->assertSame($otherTenant->id, (int) $legacyUser->fresh()->tenant_id);
    }

    private function seedStarterPlan(): void
    {
        Plan::updateOrCreate(['slug' => 'free'], [
            'name' => 'Starter', 'monthly_price' => 0, 'yearly_price' => 0, 'status' => 'active',
        ]);
        PlatformSetting::query()->delete();
        PlatformSetting::create(['trial_enabled' => true, 'trial_days' => 14]);
        PlatformSetting::clearCache();
    }

    private function storePayload(string $slug, string $name): array
    {
        return [
            'store_name' => $name . ' ' . uniqid(),
            'store_slug' => $slug,
            'business_email' => 'biz-' . uniqid() . '@test.com',
            'phone' => '09123456789',
            'language' => 'en',
            'currency' => 'MMK',
            'timezone' => 'Asia/Yangon',
            'country' => 'MM',
            'theme_color' => '#6366F1',
            'description' => 'A test store.',
        ];
    }
}
