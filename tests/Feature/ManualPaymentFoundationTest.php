<?php

namespace Tests\Feature;

use App\Data\Currency;
use App\Enums\Payment\TransactionStatus;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Payment\Platform\ManualPaymentService;
use App\Services\Payment\Platform\PaymentEvidenceService;
use App\Services\Payment\Platform\PaymentReviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ManualPaymentFoundationTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Plan $plan;
    private ManualPaymentService $manual;
    private PaymentEvidenceService $evidence;
    private PaymentReviewService $reviews;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'slug' => 'foundation-test',
            'name' => 'Foundation Test',
            'status' => 'active',
        ]);

        $this->plan = Plan::create([
            'name' => 'Starter', 'slug' => 'starter-foundation',
            'description' => 'Starter plan',
            'monthly_price' => 29, 'yearly_price' => 290, 'status' => 'active',
            'analytics_enabled' => true, 'custom_domain_enabled' => true,
            'product_limit' => 100, 'staff_limit' => 5, 'storage_limit' => 1024,
            'orders_monthly_limit' => 500, 'coupon_limit' => 20,
            'promotion_limit' => 10, 'flash_sale_limit' => 5,
            'branch_limit' => 3, 'warehouse_limit' => 2, 'pos_device_limit' => 3,
        ]);

        $subscription = new Subscription();
        $subscription->forceFill([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'billing_interval' => 'monthly',
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ])->save();

        $this->manual = $this->app->make(ManualPaymentService::class);
        $this->evidence = $this->app->make(PaymentEvidenceService::class);
        $this->reviews = $this->app->make(PaymentReviewService::class);
    }

    private static function usd(): Currency
    {
        return Currency::fromCode('USD');
    }

    private function createIntentAtWaitingReview(): PaymentIntent
    {
        $intent = $this->manual->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        return $this->manual->confirmPayment($intent);
    }

    /* ── Payment Evidence ── */

    public function test_evidence_store_screenshot(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $evidence = $this->evidence->store(
            intent: $intent,
            type: 'screenshot',
            filePath: 'uploads/evidence/screenshot_001.jpg',
            note: 'Bank transfer screenshot',
        );

        $this->assertSame($intent->id, $evidence->payment_intent_id);
        $this->assertSame('screenshot', $evidence->type);
        $this->assertSame('uploads/evidence/screenshot_001.jpg', $evidence->file_path);
        $this->assertSame('Bank transfer screenshot', $evidence->note);
    }

    public function test_evidence_store_transaction_number(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $evidence = $this->evidence->store(
            intent: $intent,
            type: 'transaction_number',
            note: 'TRX-20260701-001',
        );

        $this->assertSame('transaction_number', $evidence->type);
        $this->assertNull($evidence->file_path);
        $this->assertSame('TRX-20260701-001', $evidence->note);
    }

    public function test_evidence_store_bank_reference(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $evidence = $this->evidence->store(
            intent: $intent,
            type: 'bank_reference',
            note: 'KBZ Bank Ref: 1234567890',
            metadata: ['bank_name' => 'KBZ Bank', 'account_number' => '1234567890'],
        );

        $this->assertSame('bank_reference', $evidence->type);
        $this->assertSame('KBZ Bank', $evidence->metadata['bank_name']);
    }

    public function test_evidence_store_receipt(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $evidence = $this->evidence->store(
            intent: $intent,
            type: 'receipt',
            filePath: 'uploads/evidence/receipt_001.pdf',
        );

        $this->assertSame('receipt', $evidence->type);
        $this->assertSame('uploads/evidence/receipt_001.pdf', $evidence->file_path);
    }

    public function test_evidence_store_merchant_note(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $evidence = $this->evidence->store(
            intent: $intent,
            type: 'merchant_note',
            note: 'Payment made via mobile banking app',
        );

        $this->assertSame('merchant_note', $evidence->type);
        $this->assertSame('Payment made via mobile banking app', $evidence->note);
    }

    public function test_evidence_get_for_intent(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $this->evidence->store($intent, 'screenshot', 'img.jpg');
        $this->evidence->store($intent, 'transaction_number', null, 'TRX-001');

        $evidences = $this->evidence->getForIntent($intent);

        $this->assertCount(2, $evidences);
    }

    public function test_evidence_remove(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $evidence = $this->evidence->store($intent, 'screenshot', 'img.jpg');

        $this->assertTrue($this->evidence->hasEvidence($intent));

        $this->evidence->remove($evidence->id);

        $this->assertFalse($this->evidence->hasEvidence($intent));
    }

    public function test_evidence_count(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $this->evidence->store($intent, 'screenshot', 'img1.jpg');
        $this->evidence->store($intent, 'receipt', 'rec1.pdf');

        $this->assertSame(2, $this->evidence->count($intent));
    }

    /* ── Payment Review Service ── */

    public function test_review_approve_creates_review_record(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $review = $this->reviews->approve(
            intent: $intent,
            reviewerId: 1,
            reviewerName: 'Super Admin',
        );

        $this->assertSame($intent->id, $review->payment_intent_id);
        $this->assertSame('approved', $review->action);
        $this->assertSame(1, $review->reviewer_id);
        $this->assertSame('Super Admin', $review->reviewer_name);

        $fresh = $intent->fresh();
        $this->assertSame(TransactionStatus::COMPLETED->value, $fresh->status);
    }

    public function test_review_reject_creates_review_record(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $review = $this->reviews->reject(
            intent: $intent,
            reason: 'Evidence does not match transaction amount',
            reviewerId: 2,
            reviewerName: 'Finance Admin',
        );

        $this->assertSame($intent->id, $review->payment_intent_id);
        $this->assertSame('rejected', $review->action);
        $this->assertSame('Evidence does not match transaction amount', $review->reason);
        $this->assertSame(2, $review->reviewer_id);
        $this->assertSame('Finance Admin', $review->reviewer_name);

        $fresh = $intent->fresh();
        $this->assertSame(TransactionStatus::REJECTED->value, $fresh->status);
        $this->assertNotNull($fresh->rejected_at);
    }

    public function test_review_approve_fails_on_non_review_intent(): void
    {
        $intent = $this->manual->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $this->expectException(\InvalidArgumentException::class);

        $this->reviews->approve($intent, 1, 'Admin');
    }

    public function test_review_reject_fails_on_non_review_intent(): void
    {
        $intent = $this->manual->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $this->expectException(\InvalidArgumentException::class);

        $this->reviews->reject($intent, 'Bad payment', 1, 'Admin');
    }

    public function test_get_pending_reviews_returns_waiting_review_intents(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $pending = $this->reviews->getPendingReviews();

        $this->assertCount(1, $pending);
        $this->assertSame($intent->id, $pending->first()->id);
    }

    public function test_get_pending_reviews_excludes_approved(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->approve($intent, 1, 'Admin');

        $pending = $this->reviews->getPendingReviews();

        $this->assertCount(0, $pending);
    }

    public function test_get_review_history_returns_reviews_for_intent(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $this->reviews->approve($intent, 1, 'Admin');

        $history = $this->reviews->getReviewHistory($intent);

        $this->assertCount(1, $history);
        $this->assertSame('approved', $history->first()->action);
    }

    public function test_get_review_history_returns_all_reviews_after_reject_then_approve(): void
    {
        $first = $this->createIntentAtWaitingReview();
        $this->reviews->reject($first, 'Wrong amount', 1, 'Admin');

        $resubmitted = $this->manual->resubmitPayment($first->fresh());
        $this->assertSame(TransactionStatus::WAITING_PAYMENT->value, $resubmitted->status);

        $confirmed = $this->manual->confirmPayment($resubmitted);
        $this->reviews->approve($confirmed, 1, 'Admin');

        $history = $this->reviews->getReviewHistory($first);

        $this->assertCount(2, $history);
    }

    /* ── Resubmit Flow ── */

    public function test_resubmit_after_rejection_transitions_to_waiting_payment(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $this->reviews->reject($intent, 'Invalid screenshot', 1, 'Admin');

        $resubmitted = $this->manual->resubmitPayment($intent->fresh());

        $this->assertSame(TransactionStatus::WAITING_PAYMENT->value, $resubmitted->status);
        $this->assertNull($resubmitted->metadata['rejection_reason'] ?? null);
    }

    public function test_resubmit_fails_on_non_rejected_intent(): void
    {
        $intent = $this->manual->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $this->expectException(\InvalidArgumentException::class);

        $this->manual->resubmitPayment($intent);
    }

    /* ── Cancel Payment ── */

    public function test_cancel_payment_fails_on_terminal_intent(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $completed = $this->manual->approvePayment($intent);

        $this->expectException(\InvalidArgumentException::class);

        $this->manual->cancelPayment($completed->fresh());
    }

    /* ── Reject fails on non-review state ── */

    public function test_reject_fails_from_waiting_payment(): void
    {
        $intent = $this->manual->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be in waiting_review');

        $this->manual->rejectPayment($intent, 'Not allowed from here');
    }

    /* ── Full lifecycle: initiate → confirm → reject → resubmit → confirm → approve ── */

    public function test_full_lifecycle_with_rejection_and_resubmit(): void
    {
        $intent = $this->manual->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'yearly',
            amount: 290.00,
            currency: self::usd(),
        );

        $this->assertSame(TransactionStatus::WAITING_PAYMENT->value, $intent->status);

        $confirmed = $this->manual->confirmPayment($intent);
        $this->assertSame(TransactionStatus::WAITING_REVIEW->value, $confirmed->status);

        $this->evidence->store($confirmed, 'screenshot', 'uploads/ss.jpg');
        $this->assertTrue($this->evidence->hasEvidence($confirmed));

        $this->reviews->reject($confirmed, 'Blurry screenshot', 1, 'Admin');

        $fresh = $confirmed->fresh();
        $this->assertSame(TransactionStatus::REJECTED->value, $fresh->status);

        $this->evidence->store($fresh, 'screenshot', 'uploads/ss_v2.jpg');

        $resubmitted = $this->manual->resubmitPayment($fresh);
        $this->assertSame(TransactionStatus::WAITING_PAYMENT->value, $resubmitted->status);

        $reConfirmed = $this->manual->confirmPayment($resubmitted);
        $this->assertSame(TransactionStatus::WAITING_REVIEW->value, $reConfirmed->status);

        $this->reviews->approve($reConfirmed, 2, 'Finance Admin');

        $final = $reConfirmed->fresh();
        $this->assertSame(TransactionStatus::COMPLETED->value, $final->status);
        $this->assertNotNull($final->completed_at);

        $history = $this->reviews->getReviewHistory($final);
        $this->assertCount(2, $history);
    }

}
