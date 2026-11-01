<?php

namespace Tests\Feature;

use App\Enums\Payment\TransactionStatus;
use App\Models\PaymentIntent;
use App\Models\PaymentReview;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAuditLog;
use App\Models\SubscriptionCancellationRequest;
use App\Models\Tenant;
use App\Services\TenantRetentionEligibilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TenantRetentionEligibilityTest extends TestCase
{
    use DatabaseTransactions;

    private TenantRetentionEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TenantRetentionEligibilityService::class);
    }

    public function test_paid_subscription_is_eligible_on_review_date(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_ELIGIBLE, $result['decision']);
        $this->assertTrue($result['eligible']);
        $this->assertSame([], $result['reason_codes']);
        $this->assertSame(TenantRetentionEligibilityService::SOURCE_PAID, $result['source']);
        $this->assertSame('2026-09-27', $result['subscription_ended_at']->toDateString());
        $this->assertSame('2026-10-04', $result['grace_ends_at']->toDateString());
        $this->assertSame('2026-10-19', $result['retention_review_at']->toDateString());
        $this->assertSame(TenantRetentionEligibilityService::HOLD_VERIFIED_NONE, $result['hold_status']);
    }

    public function test_paid_subscription_is_not_eligible_before_review_date(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-18 23:59:59'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_NOT_ELIGIBLE, $result['decision']);
        $this->assertFalse($result['eligible']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_RETENTION_PERIOD_NOT_ELAPSED,
            $result['reason_codes'],
        );
        $this->assertSame('2026-10-19', $result['retention_review_at']->toDateString());
    }

    public function test_trial_subscription_uses_trial_expiry_and_is_eligible_after_review_date(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'status' => 'expired',
            'starts_at' => Carbon::parse('2026-08-01 00:00:00'),
            'trial_ends_at' => Carbon::parse('2026-09-27 23:59:59'),
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_ELIGIBLE, $result['decision']);
        $this->assertSame(TenantRetentionEligibilityService::SOURCE_TRIAL, $result['source']);
        $this->assertSame('2026-10-19', $result['retention_review_at']->toDateString());
    }

    public function test_free_plan_is_never_eligible(): void
    {
        $tenant = $this->createTenant('active');
        $plan = Plan::create([
            'name' => 'Free Plan',
            'slug' => 'free-' . uniqid(),
            'monthly_price' => 0,
            'yearly_price' => 0,
            'status' => 'active',
        ]);

        $this->createSubscription($tenant, $plan, [
            'status' => 'active',
            'expires_at' => null,
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_NOT_ELIGIBLE, $result['decision']);
        $this->assertContains(TenantRetentionEligibilityService::REASON_FREE_PLAN, $result['reason_codes']);
    }

    public function test_missing_subscription_requires_manual_review(): void
    {
        $tenant = $this->createTenant();

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_MISSING_SUBSCRIPTION,
            $result['reason_codes'],
        );
    }

    public function test_pending_payment_requires_manual_review(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);
        $intent = $this->createPaymentIntent($tenant, $plan, TransactionStatus::WAITING_PAYMENT->value);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertNull($intent->subscription_id);
        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(TenantRetentionEligibilityService::REASON_PAYMENT_PENDING, $result['reason_codes']);
    }

    public function test_payment_waiting_review_requires_manual_review(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);
        $intent = $this->createPaymentIntent($tenant, $plan, TransactionStatus::WAITING_REVIEW->value);

        $this->assertSame(0, $intent->reviews()->count());

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_PAYMENT_REVIEW_UNRESOLVED,
            $result['reason_codes'],
        );
    }

    public function test_pending_renewal_payment_requires_manual_review(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);
        $this->createPaymentIntent(
            $tenant,
            $plan,
            TransactionStatus::WAITING_PAYMENT->value,
            ['payment_reason' => 'subscription_renewal'],
        );

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_RENEWAL_PAYMENT_PENDING,
            $result['reason_codes'],
        );
    }

    public function test_pending_plan_change_payment_requires_manual_review(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);
        $this->createPaymentIntent(
            $tenant,
            $plan,
            TransactionStatus::WAITING_PAYMENT->value,
            ['payment_reason' => 'plan_change'],
        );

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_PLAN_CHANGE_PAYMENT_PENDING,
            $result['reason_codes'],
        );
    }

    public function test_scheduled_plan_change_requires_manual_review(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $targetPlan = $this->createPaidPlan();

        $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
            'pending_plan_id' => $targetPlan->id,
            'pending_plan_effective_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_PLAN_CHANGE_PENDING,
            $result['reason_codes'],
        );
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_STALE_PLAN_CHANGE,
            $result['reason_codes'],
        );
    }

    public function test_scheduled_cancellation_requires_manual_review(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $subscription = $this->createSubscription($tenant, $plan, [
            'status' => 'active',
            'expires_at' => Carbon::parse('2026-11-27 23:59:59'),
        ]);

        SubscriptionCancellationRequest::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'reason' => 'other',
            'status' => SubscriptionCancellationRequest::STATUS_SCHEDULED,
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_CANCELLATION_PENDING,
            $result['reason_codes'],
        );
    }

    public function test_historical_scheduled_cancellation_does_not_block_retention(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $subscription = $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);

        SubscriptionCancellationRequest::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'reason' => 'other',
            'status' => SubscriptionCancellationRequest::STATUS_SCHEDULED,
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_ELIGIBLE, $result['decision']);
        $this->assertNotContains(
            TenantRetentionEligibilityService::REASON_CANCELLATION_PENDING,
            $result['reason_codes'],
        );
    }

    public function test_tenant_level_historical_cancellation_does_not_block_retention(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);

        SubscriptionCancellationRequest::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => null,
            'reason' => 'other',
            'status' => SubscriptionCancellationRequest::STATUS_SCHEDULED,
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_ELIGIBLE, $result['decision']);
        $this->assertNotContains(
            TenantRetentionEligibilityService::REASON_CANCELLATION_PENDING,
            $result['reason_codes'],
        );
    }

    public function test_active_hold_blocks_eligibility(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: true,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(TenantRetentionEligibilityService::REASON_ACTIVE_HOLD, $result['reason_codes']);
    }

    public function test_unverified_hold_status_blocks_eligibility(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_HOLD_STATUS_UNVERIFIED,
            $result['reason_codes'],
        );
        $this->assertSame(TenantRetentionEligibilityService::HOLD_UNVERIFIED, $result['hold_status']);
    }

    public function test_renewal_clears_previous_expiry_eligibility(): void
    {
        $tenant = $this->createTenant('active');
        $plan = $this->createPaidPlan();
        $subscription = $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);

        $beforeRenewal = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_ELIGIBLE, $beforeRenewal['decision']);

        $subscription->update([
            'status' => 'active',
            'expires_at' => Carbon::parse('2026-11-27 23:59:59'),
        ]);

        $afterRenewal = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_NOT_ELIGIBLE, $afterRenewal['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_SUBSCRIPTION_NOT_ENDED,
            $afterRenewal['reason_codes'],
        );
    }

    public function test_expired_subscription_with_future_expiry_requires_manual_review(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-10-30 23:59:59'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_INCONSISTENT_EXPIRY_DATE,
            $result['reason_codes'],
        );
    }

    public function test_tenant_status_conflict_requires_manual_review(): void
    {
        $tenant = $this->createTenant('suspended');
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'status' => 'active',
            'expires_at' => Carbon::parse('2026-11-27 23:59:59'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_TENANT_STATUS_CONFLICT,
            $result['reason_codes'],
        );
    }

    public function test_lifecycle_status_lag_does_not_block_expired_retention(): void
    {
        $tenant = $this->createTenant('active');
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_ELIGIBLE, $result['decision']);
        $this->assertNotContains(
            TenantRetentionEligibilityService::REASON_TENANT_STATUS_CONFLICT,
            $result['reason_codes'],
        );
    }

    public function test_active_subscription_is_not_eligible(): void
    {
        $tenant = $this->createTenant('active');
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'status' => 'active',
            'expires_at' => Carbon::parse('2026-11-27 23:59:59'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_NOT_ELIGIBLE, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_SUBSCRIPTION_NOT_ENDED,
            $result['reason_codes'],
        );
    }

    public function test_other_open_subscription_statuses_are_not_eligible(): void
    {
        foreach (['trialing', 'past_due'] as $status) {
            $tenant = $this->createTenant('active');
            $plan = $this->createPaidPlan();
            $this->createSubscription($tenant, $plan, [
                'status' => $status,
                'trial_ends_at' => $status === 'trialing'
                    ? Carbon::parse('2026-11-27 23:59:59')
                    : null,
                'expires_at' => Carbon::parse('2026-11-27 23:59:59'),
            ]);

            $result = $this->service->evaluate(
                $tenant,
                hasActiveHold: false,
                asOf: Carbon::parse('2026-10-19 09:00:00'),
            );

            $this->assertSame(TenantRetentionEligibilityService::DECISION_NOT_ELIGIBLE, $result['decision']);
            $this->assertContains(
                TenantRetentionEligibilityService::REASON_SUBSCRIPTION_NOT_ENDED,
                $result['reason_codes'],
            );
        }
    }

    public function test_missing_tenant_requires_manual_review(): void
    {
        $tenant = new Tenant();
        $tenant->id = 999999999;

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_TENANT_NOT_FOUND,
            $result['reason_codes'],
        );
    }

    public function test_active_subscription_with_past_expiry_requires_manual_review(): void
    {
        $tenant = $this->createTenant('active');
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'status' => 'active',
            'expires_at' => Carbon::parse('2026-10-01 00:00:00'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_STALE_OPEN_SUBSCRIPTION_EXPIRED,
            $result['reason_codes'],
        );
    }

    public function test_trialing_subscription_with_past_trial_expiry_requires_manual_review(): void
    {
        $tenant = $this->createTenant('trialing');
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'status' => 'trialing',
            'trial_ends_at' => Carbon::parse('2026-10-01 00:00:00'),
            'expires_at' => Carbon::parse('2026-10-01 00:00:00'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_STALE_OPEN_SUBSCRIPTION_EXPIRED,
            $result['reason_codes'],
        );
    }

    public function test_tenant_plan_mismatch_requires_manual_review(): void
    {
        $tenant = $this->createTenant();
        $tenantPlan = $this->createPaidPlan();
        $subscriptionPlan = $this->createPaidPlan();

        $tenant->update(['subscription_plan_id' => $tenantPlan->id]);

        $this->createSubscription($tenant, $subscriptionPlan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_TENANT_SUBSCRIPTION_PLAN_MISMATCH,
            $result['reason_codes'],
        );
    }

    public function test_tenant_expiry_status_mismatch_requires_manual_review(): void
    {
        $tenant = $this->createTenant('active');
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'status' => 'active',
            'expires_at' => Carbon::parse('2026-11-27 23:59:59'),
        ]);

        $tenant->update(['expires_at' => Carbon::parse('2026-10-01 00:00:00')]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_TENANT_EXPIRY_STATUS_MISMATCH,
            $result['reason_codes'],
        );
    }

    public function test_elapsed_retention_window_with_stale_lifecycle_requires_manual_review(): void
    {
        $tenant = $this->createTenant('trialing');
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'status' => 'active',
            'expires_at' => Carbon::parse('2026-09-01 00:00:00'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_STALE_OPEN_SUBSCRIPTION_EXPIRED,
            $result['reason_codes'],
        );
    }

    public function test_completed_renewal_payment_uses_updated_expiry_and_is_not_eligible(): void
    {
        $tenant = $this->createTenant('active');
        $plan = $this->createPaidPlan();
        $subscription = $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);

        $this->createPaymentIntent(
            $tenant,
            $plan,
            TransactionStatus::COMPLETED->value,
            [
                'payment_reason' => 'subscription_renewal',
                'subscription_applied' => '2026-10-01 00:00:00',
            ],
        );

        $subscription->update([
            'status' => 'active',
            'expires_at' => Carbon::parse('2026-11-27 23:59:59'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_NOT_ELIGIBLE, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_SUBSCRIPTION_NOT_ENDED,
            $result['reason_codes'],
        );
        $this->assertNotContains(
            TenantRetentionEligibilityService::REASON_RENEWAL_PAYMENT_PENDING,
            $result['reason_codes'],
        );
    }

    public function test_suspended_subscription_requires_manual_review(): void
    {
        $tenant = $this->createTenant('suspended');
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'status' => 'suspended',
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_AMBIGUOUS_SUBSCRIPTION_STATUS,
            $result['reason_codes'],
        );
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_SUSPENSION_ORIGIN_UNVERIFIED,
            $result['reason_codes'],
        );
    }

    public function test_auto_suspended_paid_subscription_is_eligible_after_review_date(): void
    {
        $tenant = $this->createTenant('active');
        $plan = $this->createPaidPlan();
        $subscription = $this->createSubscription($tenant, $plan, [
            'status' => 'suspended',
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
            'suspended_at' => Carbon::parse('2026-10-05 00:00:00'),
        ]);

        $this->recordLifecycleSuspension($subscription);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_ELIGIBLE, $result['decision']);
        $this->assertSame(TenantRetentionEligibilityService::SOURCE_PAID, $result['source']);
        $this->assertNotContains(
            TenantRetentionEligibilityService::REASON_AMBIGUOUS_SUBSCRIPTION_STATUS,
            $result['reason_codes'],
        );
    }

    public function test_auto_suspended_trial_subscription_is_eligible_after_review_date(): void
    {
        $tenant = $this->createTenant('trialing');
        $plan = $this->createPaidPlan();
        $subscription = $this->createSubscription($tenant, $plan, [
            'status' => 'suspended',
            'starts_at' => Carbon::parse('2026-08-01 00:00:00'),
            'trial_ends_at' => Carbon::parse('2026-09-27 23:59:59'),
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
            'suspended_at' => Carbon::parse('2026-09-28 00:00:00'),
        ]);

        $this->recordLifecycleSuspension($subscription);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_ELIGIBLE, $result['decision']);
        $this->assertSame(TenantRetentionEligibilityService::SOURCE_TRIAL, $result['source']);
    }

    public function test_manual_suspension_requires_manual_review(): void
    {
        $tenant = $this->createTenant('suspended');
        $plan = $this->createPaidPlan();
        $subscription = $this->createSubscription($tenant, $plan, [
            'status' => 'suspended',
            'expires_at' => Carbon::parse('2026-11-27 23:59:59'),
            'suspended_at' => Carbon::parse('2026-10-01 00:00:00'),
        ]);

        SubscriptionAuditLog::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
            'event' => 'suspended',
            'actor_type' => 'superadmin',
            'old_status' => 'active',
            'new_status' => 'suspended',
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_SUSPENSION_ORIGIN_UNVERIFIED,
            $result['reason_codes'],
        );
    }

    public function test_completed_payment_without_subscription_applied_blocks_retention(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $subscription = $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);

        $this->createPaymentIntent(
            $tenant,
            $plan,
            TransactionStatus::COMPLETED->value,
            ['payment_reason' => 'subscription_renewal'],
            ['subscription_id' => $subscription->id],
        );

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_COMPLETED_PAYMENT_UNAPPLIED,
            $result['reason_codes'],
        );
    }

    public function test_completed_payment_without_subscription_id_or_applied_marker_blocks_retention(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);

        $this->createPaymentIntent(
            $tenant,
            $plan,
            TransactionStatus::COMPLETED->value,
            ['payment_reason' => 'subscription_renewal'],
        );

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_COMPLETED_PAYMENT_UNAPPLIED,
            $result['reason_codes'],
        );
    }

    public function test_expired_waiting_payment_intent_does_not_block_retention(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);
        $this->createPaymentIntent(
            $tenant,
            $plan,
            TransactionStatus::WAITING_PAYMENT->value,
            ['payment_reason' => 'subscription_renewal'],
            ['expires_at' => Carbon::parse('2026-10-01 00:00:00')],
        );

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_ELIGIBLE, $result['decision']);
        $this->assertNotContains(
            TenantRetentionEligibilityService::REASON_PAYMENT_PENDING,
            $result['reason_codes'],
        );
    }

    public function test_resolved_payment_review_does_not_add_unresolved_reason(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ]);
        $intent = $this->createPaymentIntent(
            $tenant,
            $plan,
            TransactionStatus::WAITING_REVIEW->value,
        );
        PaymentReview::create([
            'payment_intent_id' => $intent->id,
            'action' => 'approved',
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(TenantRetentionEligibilityService::REASON_PAYMENT_PENDING, $result['reason_codes']);
        $this->assertNotContains(
            TenantRetentionEligibilityService::REASON_PAYMENT_REVIEW_UNRESOLVED,
            $result['reason_codes'],
        );
    }

    public function test_multiple_open_subscriptions_require_manual_review(): void
    {
        $tenant = $this->createTenant('active');
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'status' => 'active',
            'expires_at' => Carbon::parse('2026-11-27 23:59:59'),
        ]);
        $this->createSubscription($tenant, $plan, [
            'status' => 'active',
            'expires_at' => Carbon::parse('2026-12-27 23:59:59'),
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_CONFLICTING_SUBSCRIPTION_STATE,
            $result['reason_codes'],
        );
    }

    public function test_config_protected_tenant_requires_manual_review(): void
    {
        $tenant = $this->createTenant();
        config(['retention.protected_tenant_ids' => [$tenant->id]]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_PROTECTED_TENANT,
            $result['reason_codes'],
        );
    }

    public function test_tenant_setting_protected_flag_requires_manual_review(): void
    {
        $tenant = $this->createTenant();
        $tenant->update(['settings' => array_merge($tenant->settings ?? [], ['retention_protected' => true])]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_PROTECTED_TENANT,
            $result['reason_codes'],
        );
    }

    public function test_missing_expiry_date_requires_manual_review(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPaidPlan();
        $this->createSubscription($tenant, $plan, [
            'expires_at' => null,
        ]);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(
            TenantRetentionEligibilityService::REASON_MISSING_EXPIRY_DATE,
            $result['reason_codes'],
        );
    }

    public function test_default_tenant_requires_manual_review(): void
    {
        $tenant = Tenant::where('slug', 'default')->first()
            ?? $this->createTenant('suspended', ['slug' => 'default']);

        $result = $this->service->evaluate(
            $tenant,
            hasActiveHold: false,
            asOf: Carbon::parse('2026-10-19 09:00:00'),
        );

        $this->assertSame(TenantRetentionEligibilityService::DECISION_MANUAL_REVIEW, $result['decision']);
        $this->assertContains(TenantRetentionEligibilityService::REASON_DEFAULT_TENANT, $result['reason_codes']);
    }

    private function recordLifecycleSuspension(Subscription $subscription): SubscriptionAuditLog
    {
        return SubscriptionAuditLog::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'event' => 'suspended',
            'actor_type' => 'system',
            'old_status' => 'expired',
            'new_status' => 'suspended',
        ]);
    }

    private function createTenant(string $status = 'suspended', array $attributes = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => 'Retention Test Merchant',
            'slug' => 'retention-test-' . uniqid(),
            'status' => $status,
        ], $attributes));
    }

    private function createPaidPlan(): Plan
    {
        return Plan::create([
            'name' => 'Retention Paid Plan',
            'slug' => 'retention-paid-' . uniqid(),
            'monthly_price' => 10,
            'yearly_price' => 100,
            'status' => 'active',
        ]);
    }

    private function createSubscription(Tenant $tenant, Plan $plan, array $attributes = []): Subscription
    {
        $subscription = new Subscription();
        $subscription->tenant_id = $tenant->id;
        $subscription->fill(array_merge([
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'status' => 'expired',
            'starts_at' => Carbon::parse('2026-08-01 00:00:00'),
            'expires_at' => Carbon::parse('2026-09-27 23:59:59'),
        ], $attributes));
        $subscription->save();

        if ($tenant->subscription_plan_id === null) {
            $tenant->update(['subscription_plan_id' => $plan->id]);
        }

        return $subscription->fresh();
    }

    private function createPaymentIntent(
        Tenant $tenant,
        Plan $plan,
        string $status,
        array $metadata = [],
        array $attributes = [],
    ): PaymentIntent {
        return PaymentIntent::create(array_merge([
            'reference_number' => 'RET-' . uniqid(),
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'amount' => 10,
            'currency' => 'USD',
            'gateway' => 'manual',
            'status' => $status,
            'metadata' => $metadata,
        ], $attributes));
    }
}
