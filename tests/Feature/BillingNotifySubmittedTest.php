<?php

namespace Tests\Feature;

use App\Data\Currency;
use App\Mail\Billing\PaymentReviewMail;
use App\Mail\Billing\PaymentSubmittedMail;
use App\Models\Account;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\BillingNotificationService;
use App\Services\Payment\Platform\ManualPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingNotifySubmittedTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createNotifySchema();
        Mail::fake();
    }

    public function test_submit_notifies_merchant_and_superadmin_separately(): void
    {
        [$tenant, $plan] = $this->makeStack('merchant-owner@example.com');
        $this->makeSuperadmin('superadmin-reviewer@example.com');

        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $tenant, plan: $plan, billingCycle: 'monthly',
            amount: 100.00, currency: Currency::fromCode('MMK'),
        );
        $manual->confirmPayment($intent);

        app(BillingNotificationService::class)->notifyPaymentSubmitted($intent->fresh());

        Mail::assertQueued(PaymentSubmittedMail::class, function ($mail) {
            return $mail->hasTo('merchant-owner@example.com');
        });
        Mail::assertQueued(PaymentReviewMail::class, function ($mail) {
            return $mail->hasTo('superadmin-reviewer@example.com');
        });
    }

    public function test_repeat_notify_does_not_resend(): void
    {
        [$tenant, $plan] = $this->makeStack('merchant-repeat@example.com');
        $this->makeSuperadmin('superadmin-repeat@example.com');

        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $tenant, plan: $plan, billingCycle: 'monthly',
            amount: 100.00, currency: Currency::fromCode('MMK'),
        );
        $manual->confirmPayment($intent);

        $service = app(BillingNotificationService::class);
        $service->notifyPaymentSubmitted($intent->fresh());
        $service->notifyPaymentSubmitted($intent->fresh());

        Mail::assertQueued(PaymentSubmittedMail::class, 1);
        Mail::assertQueued(PaymentReviewMail::class, 1);
    }

    public function test_superadmin_database_notification_is_created(): void
    {
        [$tenant, $plan] = $this->makeStack('merchant-dbnote@example.com');
        $this->makeSuperadmin('superadmin-dbnote@example.com');

        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $tenant, plan: $plan, billingCycle: 'monthly',
            amount: 100.00, currency: Currency::fromCode('MMK'),
        );
        $manual->confirmPayment($intent);

        $before = \Illuminate\Support\Facades\DB::table('notifications')
            ->where('notifiable_id', Account::where('email', 'superadmin-dbnote@example.com')->first()->id)
            ->count();

        app(BillingNotificationService::class)->notifyPaymentSubmitted($intent->fresh());

        $this->assertSame($before + 1, \Illuminate\Support\Facades\DB::table('notifications')
            ->where('notifiable_id', Account::where('email', 'superadmin-dbnote@example.com')->first()->id)
            ->count());
    }

    private function makeStack(string $ownerEmail): array
    {
        $tenant = Tenant::create([
            'slug' => 'notify-' . uniqid(),
            'name' => 'Notify Shop',
            'status' => 'active',
        ]);
        $plan = Plan::create([
            'name' => 'Numbered', 'slug' => 'numbered-' . uniqid(),
            'description' => 'Test plan', 'monthly_price' => 100,
            'yearly_price' => 1000, 'status' => 'active',
        ]);

        $account = Account::create([
            'name' => 'Owner',
            'email' => $ownerEmail,
            'password' => 'secret',
            'status' => 'active',
        ]);
        $role = \App\Models\Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id,
        ]);
        TenantMembership::create([
            'account_id' => $account->id,
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'is_owner' => true,
            'status' => 'active',
        ]);

        return [$tenant, $plan];
    }

    private function makeSuperadmin(string $email): void
    {
        $account = Account::create([
            'name' => 'Super Admin',
            'email' => $email,
            'password' => 'secret',
            'status' => 'active',
        ]);
        $role = \App\Models\Role::withoutTenantScope()->firstOrCreate([
            'name' => 'superadmin', 'guard_name' => 'web', 'tenant_id' => null,
        ]);
        \Illuminate\Support\Facades\DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => Account::class,
            'model_id' => $account->id,
        ]);
    }

    private function createNotifySchema(): void
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

        if (!Schema::hasTable('payment_intents')) {
            Schema::create('payment_intents', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('plan_id');
                $table->unsignedBigInteger('subscription_id')->nullable();
                $table->string('billing_cycle');
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3);
                $table->string('gateway');
                $table->string('status');
                $table->timestamp('expires_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->string('reference_number')->nullable()->unique();
                $table->string('idempotency_key', 64)->nullable();
                $table->timestamps();
                $table->index('tenant_id');
                $table->index('status');
            });
        }

        if (!Schema::hasTable('payment_timeline_events')) {
            Schema::create('payment_timeline_events', function ($table) {
                $table->id();
                $table->unsignedBigInteger('payment_intent_id');
                $table->string('type');
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();
                $table->index('payment_intent_id');
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

        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password')->nullable();
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
                $table->timestamps();
                $table->index(['tenant_id', 'account_id']);
            });
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function ($table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('reference_numbers')) {
            Schema::create('reference_numbers', function ($table) {
                $table->id();
                $table->string('prefix', 10);
                $table->date('date');
                $table->unsignedInteger('last_sequence')->default(0);
                $table->timestamps();
                $table->unique(['prefix', 'date']);
            });
        }
    }

    public function test_review_email_never_goes_to_merchant(): void
    {
        [$tenant, $plan] = $this->makeStack('merchant-only@example.com');
        $this->makeSuperadmin('superadmin-only@example.com');

        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $tenant, plan: $plan, billingCycle: 'monthly',
            amount: 100.00, currency: Currency::fromCode('MMK'),
        );
        $manual->confirmPayment($intent);

        app(BillingNotificationService::class)->notifyPaymentSubmitted($intent->fresh());

        foreach (Mail::queued(PaymentReviewMail::class) as $mail) {
            $this->assertFalse($mail->hasTo('merchant-only@example.com'));
        }
        Mail::assertQueued(PaymentReviewMail::class, function ($mail) {
            return $mail->hasTo('superadmin-only@example.com');
        });
    }

    public function test_lock_outage_does_not_suppress_review_email(): void
    {
        [$tenant, $plan] = $this->makeStack('merchant-lockout@example.com');
        $this->makeSuperadmin('superadmin-lockout@example.com');

        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $tenant, plan: $plan, billingCycle: 'monthly',
            amount: 100.00, currency: Currency::fromCode('MMK'),
        );
        $manual->confirmPayment($intent);

        Cache::shouldReceive('lock')->andThrow(new \Exception('locks unavailable'));

        app(BillingNotificationService::class)->notifyPaymentSubmitted($intent->fresh());

        Mail::assertQueued(PaymentSubmittedMail::class, function ($mail) {
            return $mail->hasTo('merchant-lockout@example.com');
        });
        Mail::assertQueued(PaymentReviewMail::class, function ($mail) {
            return $mail->hasTo('superadmin-lockout@example.com');
        });
    }
}
