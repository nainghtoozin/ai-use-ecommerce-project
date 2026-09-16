<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingExtraRenewalTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRenewalSchema();
    }

    public function test_extra_renewal_is_available_when_eligible(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('paid-a', 100, 1000);
        $subscription = $this->makeSubscription($tenant, $plan, 'expired', now()->subDays(3));

        $this->assertFalse($subscription->hasUsedExtraRenewal());
        $this->assertNull($subscription->extra_renewal_used_at);
    }

    public function test_extra_renewal_extends_subscription_correctly(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('paid-b', 100, 1000);
        $subscription = $this->makeSubscription($tenant, $plan, 'expired', now()->subDays(3));

        $subscription->renewFromInterval('Self-service renewal by merchant.');
        $consumed = $subscription->consumeExtraRenewal();

        $subscription->refresh();

        $this->assertTrue($consumed);
        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->expires_at->isFuture());
        $this->assertTrue($subscription->hasUsedExtraRenewal());
    }

    public function test_extra_renewal_is_marked_consumed(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('paid-c', 100, 1000);
        $subscription = $this->makeSubscription($tenant, $plan, 'past_due', now()->subDays(2));

        $this->assertTrue($subscription->consumeExtraRenewal());
        $this->assertNotNull($subscription->fresh()->extra_renewal_used_at);
    }

    public function test_second_attempt_cannot_consume_again(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('paid-d', 100, 1000);
        $subscription = $this->makeSubscription($tenant, $plan, 'expired', now()->subDays(3));

        $this->assertTrue($subscription->consumeExtraRenewal());
        $firstUsedAt = $subscription->fresh()->extra_renewal_used_at;

        $this->assertFalse($subscription->consumeExtraRenewal());
        $this->assertEquals(
            $firstUsedAt->toDateTimeString(),
            $subscription->fresh()->extra_renewal_used_at->toDateTimeString()
        );
    }

    public function test_double_submission_consumes_only_once(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('paid-e', 100, 1000);
        $subscription = $this->makeSubscription($tenant, $plan, 'expired', now()->subDays(3));

        $results = [$subscription->consumeExtraRenewal(), $subscription->consumeExtraRenewal()];

        $this->assertSame([true, false], $results);
        $this->assertSame(1, Subscription::whereKey($subscription->id)->whereNotNull('extra_renewal_used_at')->count());
    }

    public function test_free_plan_does_not_consume_extra_renewal(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('free-x', 0, 0);
        $subscription = $this->makeSubscription($tenant, $plan, 'expired', now()->subDays(3));

        $this->assertFalse($subscription->consumeExtraRenewal());
        $this->assertNull($subscription->fresh()->extra_renewal_used_at);
    }

    public function test_normal_renewal_still_works_after_chance_consumed(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('paid-f', 100, 1000);
        $subscription = $this->makeSubscription($tenant, $plan, 'expired', now()->subDays(3));

        $subscription->consumeExtraRenewal();
        $usedAt = $subscription->fresh()->extra_renewal_used_at;

        $subscription->renewFromInterval('Second renewal.');
        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->expires_at->isFuture());
        $this->assertEquals($usedAt->toDateTimeString(), $subscription->extra_renewal_used_at->toDateTimeString());
    }

    public function test_past_due_grace_renewal_still_works_and_marks_chance(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('paid-g', 100, 1000);
        $subscription = $this->makeSubscription($tenant, $plan, 'past_due', now()->subDay());

        $subscription->renewFromInterval('Grace renewal.');
        $subscription->consumeExtraRenewal();
        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->hasUsedExtraRenewal());
    }

    private function makeTenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'slug' => 'extra-renewal-' . uniqid(),
            'name' => 'Extra Renewal',
            'status' => 'active',
        ], $overrides));
    }

    private function makePlan(string $slug, float $monthly, float $yearly): Plan
    {
        return Plan::create([
            'name' => ucfirst($slug),
            'slug' => $slug . '-' . uniqid(),
            'description' => 'Test plan',
            'monthly_price' => $monthly,
            'yearly_price' => $yearly,
            'status' => 'active',
        ]);
    }

    private function makeSubscription(Tenant $tenant, Plan $plan, string $status, $expiresAt): Subscription
    {
        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'status' => $status,
            'starts_at' => now()->subMonth(),
            'expires_at' => $expiresAt,
            'trial_renewals_count' => 0,
        ]);
        $subscription->tenant_id = $tenant->id;
        $subscription->save();

        return $subscription;
    }

    private function createRenewalSchema(): void
    {
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function ($table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('status')->default('active');
                $table->string('store_url')->nullable();
                $table->json('settings')->nullable();
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

        if (!Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->timestamps();
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

        if (!Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function ($table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
            });
        }
    }
}
