<?php

namespace Tests\Feature;

use App\Data\Currency;
use App\Enums\Payment\TransactionStatus;
use App\Models\PaymentComment;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Payment\Platform\LedgerService;
use App\Services\Payment\Platform\ManualPaymentService;
use App\Services\Payment\Platform\PaymentCommentService;
use App\Services\Payment\Platform\PaymentEvidenceService;
use App\Services\Payment\Platform\PaymentReviewService;
use App\Services\Payment\Platform\PaymentTimelineService;
use App\Services\Payment\Platform\PaymentTransactionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransactionFoundationTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Plan $plan;
    private ManualPaymentService $manual;
    private PaymentEvidenceService $evidence;
    private PaymentReviewService $reviews;
    private PaymentTransactionService $transactions;
    private PaymentTimelineService $timeline;
    private PaymentCommentService $comments;
    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMinimalSchema();

        $this->tenant = Tenant::create([
            'slug' => 'txn-test',
            'name' => 'Transaction Test',
            'status' => 'active',
        ]);

        $this->plan = Plan::create([
            'name' => 'Starter', 'slug' => 'starter-txn',
            'description' => 'Starter plan',
            'monthly_price' => 29, 'yearly_price' => 290, 'status' => 'active',
            'analytics_enabled' => true, 'custom_domain_enabled' => true,
            'product_limit' => 100, 'staff_limit' => 5, 'storage_limit' => 1024,
            'orders_monthly_limit' => 500, 'coupon_limit' => 20,
            'promotion_limit' => 10, 'flash_sale_limit' => 5,
            'branch_limit' => 3, 'warehouse_limit' => 2, 'pos_device_limit' => 3,
        ]);

        $this->manual = $this->app->make(ManualPaymentService::class);
        $this->evidence = $this->app->make(PaymentEvidenceService::class);
        $this->reviews = $this->app->make(PaymentReviewService::class);
        $this->transactions = $this->app->make(PaymentTransactionService::class);
        $this->timeline = $this->app->make(PaymentTimelineService::class);
        $this->comments = $this->app->make(PaymentCommentService::class);
        $this->ledger = $this->app->make(LedgerService::class);
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

    /* ── Transaction Service ── */

    public function test_transaction_created_from_completed_intent(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->approve($intent, 1, 'Admin');

        $transaction = $this->transactions->findByIntent($intent);

        $this->assertNotNull($transaction);
        $this->assertSame($intent->id, $transaction->payment_intent_id);
        $this->assertSame('29.00', (string) $transaction->amount);
        $this->assertSame('USD', $transaction->currency);
        $this->assertSame('manual', $transaction->gateway);
        $this->assertSame('completed', $transaction->status);
        $this->assertNotNull($transaction->transaction_number);
        $this->assertStringStartsWith('TXN-', $transaction->transaction_number);
    }

    public function test_transaction_has_tenant_plan_relationships(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->approve($intent, 1, 'Admin');

        $transaction = $this->transactions->findByIntent($intent);
        $this->assertNotNull($transaction);
        $this->assertSame($this->tenant->id, $transaction->tenant_id);
        $this->assertSame($this->plan->id, $transaction->plan_id);
    }

    public function test_transaction_not_created_before_completion(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $transaction = $this->transactions->findByIntent($intent);

        $this->assertNull($transaction);
    }

    public function test_transaction_not_created_on_rejection(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->reject($intent, 'Invalid evidence', 1, 'Admin');

        $transaction = $this->transactions->findByIntent($intent);

        $this->assertNull($transaction);
    }

    public function test_transaction_created_only_once_for_same_intent(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->approve($intent, 1, 'Admin');

        $first = $this->transactions->findByIntent($intent);
        $second = $this->transactions->findByIntent($intent);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
    }

    public function test_find_by_transaction_number(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->approve($intent, 1, 'Admin');

        $transaction = $this->transactions->findByIntent($intent);
        $found = $this->transactions->findByTransactionNumber($transaction->transaction_number);

        $this->assertNotNull($found);
        $this->assertSame($transaction->id, $found->id);
    }

    public function test_search_by_reference_number(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->approve($intent, 1, 'Admin');

        $transaction = $this->transactions->findByIntent($intent);
        $number = $transaction->transaction_number;

        $results = $this->transactions->search(referenceNumber: $number);

        $this->assertCount(1, $results);
        $this->assertSame($transaction->id, $results->first()->id);
    }

    public function test_search_by_gateway(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->approve($intent, 1, 'Admin');

        $results = $this->transactions->search(gateway: 'manual');

        $this->assertCount(1, $results);
    }

    public function test_get_for_tenant_returns_transactions(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->approve($intent, 1, 'Admin');

        $results = $this->transactions->getForTenant($this->tenant);

        $this->assertCount(1, $results);
    }

    /* ── Timeline Service ── */

    public function test_timeline_records_created_event(): void
    {
        $intent = $this->manual->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $events = $this->timeline->getForIntent($intent);

        $types = $events->pluck('type')->toArray();
        $this->assertContains('created', $types);
    }

    public function test_timeline_records_completed_event(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->approve($intent, 1, 'Admin');

        $events = $this->timeline->getForIntent($intent);

        $types = $events->pluck('type')->toArray();
        $this->assertContains('completed', $types);
    }

    public function test_timeline_records_rejected_event(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->reject($intent, 'Invalid evidence', 1, 'Admin');

        $events = $this->timeline->getForIntent($intent);

        $types = $events->pluck('type')->toArray();
        $this->assertContains('rejected', $types);
    }

    public function test_timeline_records_resubmitted_event(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->reject($intent, 'Invalid evidence', 1, 'Admin');

        $this->manual->resubmitPayment($intent->fresh());

        $events = $this->timeline->getForIntent($intent);

        $types = $events->pluck('type')->toArray();
        $this->assertContains('resubmitted', $types);
    }

    public function test_timeline_records_evidence_uploaded_event(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->evidence->store($intent, 'screenshot', 'uploads/ss.jpg');

        $events = $this->timeline->getForIntent($intent);

        $types = $events->pluck('type')->toArray();
        $this->assertContains('evidence_uploaded', $types);
    }

    public function test_timeline_events_are_chronological(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $this->evidence->store($intent, 'screenshot', 'img.jpg');
        $this->reviews->reject($intent, 'Bad screenshot', 1, 'Admin');
        $this->manual->resubmitPayment($intent->fresh());
        $confirmed = $this->manual->confirmPayment($intent->fresh());
        $this->reviews->approve($confirmed, 1, 'Admin');

        $events = $this->timeline->getForIntent($intent);

        $expectedOrder = ['created', 'evidence_uploaded', 'rejected', 'resubmitted', 'completed'];
        $actualOrder = $events->pluck('type')->toArray();

        foreach ($expectedOrder as $type) {
            $this->assertContains($type, $actualOrder);
        }

        // Verify chronological ordering by comparing timestamps
        $previous = null;
        foreach ($events as $event) {
            if ($previous) {
                $this->assertTrue(
                    $event->occurred_at->gte($previous->occurred_at),
                    "Timeline event '{$event->type}' is out of order"
                );
            }
            $previous = $event;
        }
    }

    public function test_timeline_cancelled_event(): void
    {
        $intent = $this->manual->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $this->manual->cancelPayment($intent);

        $events = $this->timeline->getForIntent($intent);

        $types = $events->pluck('type')->toArray();
        $this->assertContains('cancelled', $types);
    }

    public function test_timeline_get_by_type(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->approve($intent, 1, 'Admin');

        $createdEvents = $this->timeline->getByType($intent, 'created');
        $completedEvents = $this->timeline->getByType($intent, 'completed');

        $this->assertCount(1, $createdEvents);
        $this->assertCount(1, $completedEvents);
    }

    /* ── Comment Service ── */

    public function test_comment_added_by_admin(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $comment = $this->comments->addComment(
            intent: $intent,
            authorType: 'admin',
            authorId: 1,
            authorName: 'Super Admin',
            body: 'Please provide a clearer screenshot.',
        );

        $this->assertSame($intent->id, $comment->payment_intent_id);
        $this->assertSame('admin', $comment->author_type);
        $this->assertSame(1, $comment->author_id);
        $this->assertSame('Super Admin', $comment->author_name);
        $this->assertSame('Please provide a clearer screenshot.', $comment->body);
    }

    public function test_comment_added_by_merchant(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $comment = $this->comments->addComment(
            intent: $intent,
            authorType: 'merchant',
            authorId: 42,
            authorName: 'Merchant User',
            body: 'I have re-uploaded the evidence.',
        );

        $this->assertSame('merchant', $comment->author_type);
        $this->assertSame('Merchant User', $comment->author_name);
    }

    public function test_comment_added_by_system(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $comment = $this->comments->addComment(
            intent: $intent,
            authorType: 'system',
            authorId: null,
            authorName: 'System',
            body: 'Payment automatically flagged for review.',
        );

        $this->assertSame('system', $comment->author_type);
        $this->assertNull($comment->author_id);
    }

    public function test_comment_with_metadata(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $comment = $this->comments->addComment(
            intent: $intent,
            authorType: 'admin',
            authorId: 1,
            authorName: 'Admin',
            body: 'Review note',
            metadata: ['ip_address' => '127.0.0.1', 'user_agent' => 'Test'],
        );

        $this->assertSame(['ip_address' => '127.0.0.1', 'user_agent' => 'Test'], $comment->metadata);
    }

    public function test_comment_triggers_timeline_event(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $this->comments->addComment(
            intent: $intent,
            authorType: 'admin',
            authorId: 1,
            authorName: 'Admin',
            body: 'Test comment',
        );

        $events = $this->timeline->getForIntent($intent);

        $commentEvents = $events->where('type', 'comment_added');
        $this->assertCount(1, $commentEvents);
        $this->assertStringContainsString('Admin', $commentEvents->first()->description);
    }

    public function test_get_comments_for_intent(): void
    {
        $intent = $this->createIntentAtWaitingReview();

        $this->comments->addComment($intent, 'admin', 1, 'Admin', 'First comment');
        $this->comments->addComment($intent, 'merchant', 42, 'Merchant', 'Reply comment');

        $all = $this->comments->getForIntent($intent);

        $this->assertCount(2, $all);
        $this->assertSame('First comment', $all->first()->body);
        $this->assertSame('Reply comment', $all->last()->body);
    }

    /* ── Ledger Service ── */

    public function test_ledger_entry_created_on_completion(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->approve($intent, 1, 'Admin');

        $transaction = $this->transactions->findByIntent($intent);
        $entries = $this->ledger->getForTransaction($transaction);

        $this->assertCount(1, $entries);
        $this->assertSame('payment_completed', $entries->first()->type);
        $this->assertSame('29.00', (string) $entries->first()->amount);
        $this->assertSame('USD', $entries->first()->currency);
    }

    public function test_ledger_entry_has_transaction_id(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->approve($intent, 1, 'Admin');

        $transaction = $this->transactions->findByIntent($intent);
        $entries = $this->ledger->getForTransaction($transaction);

        $this->assertSame($transaction->id, $entries->first()->transaction_id);
    }

    public function test_ledger_entry_has_payment_intent_id(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->approve($intent, 1, 'Admin');

        $entries = $this->ledger->getForIntent($intent);

        $this->assertCount(1, $entries);
        $this->assertSame($intent->id, $entries->first()->payment_intent_id);
    }

    public function test_ledger_get_by_type(): void
    {
        $intent = $this->createIntentAtWaitingReview();
        $this->reviews->approve($intent, 1, 'Admin');

        $entries = $this->ledger->getByType('payment_completed');

        $this->assertCount(1, $entries);
    }

    public function test_ledger_manual_record(): void
    {
        $entry = $this->ledger->record(
            type: 'test_event',
            amount: 100.00,
            currency: 'USD',
            description: 'Test ledger entry',
            metadata: ['key' => 'value'],
        );

        $this->assertSame('test_event', $entry->type);
        $this->assertSame('100.00', (string) $entry->amount);
        $this->assertSame('USD', $entry->currency);
        $this->assertSame('Test ledger entry', $entry->description);
        $this->assertSame(['key' => 'value'], $entry->metadata);
        $this->assertNotNull($entry->recorded_at);
    }

    /* ── Full Integrated Lifecycle ── */

    public function test_full_integrated_lifecycle(): void
    {
        $intent = $this->manual->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'yearly',
            amount: 290.00,
            currency: self::usd(),
        );

        $this->assertSame(TransactionStatus::WAITING_PAYMENT->value, $intent->status);

        // Merchant uploads evidence
        $this->evidence->store($intent, 'receipt', 'uploads/receipt.pdf', 'Payment receipt');

        // Merchant confirms payment
        $confirmed = $this->manual->confirmPayment($intent);
        $this->assertSame(TransactionStatus::WAITING_REVIEW->value, $confirmed->status);

        // Admin comments
        $this->comments->addComment($confirmed, 'admin', 1, 'Admin', 'Thank you, we will review shortly.');

        // Admin rejects
        $this->reviews->reject($confirmed, 'Receipt is unclear', 1, 'Admin');
        $this->assertTrue($confirmed->fresh()->isRejected());

        // Merchant comments before resubmitting
        $this->comments->addComment(
            $confirmed->fresh(), 'merchant', 42, 'Merchant',
            'I will upload a clearer receipt.',
        );

        // Merchant uploads new evidence
        $this->evidence->store($confirmed->fresh(), 'receipt', 'uploads/receipt_v2.pdf', 'Clearer receipt');

        // Merchant resubmits
        $resubmitted = $this->manual->resubmitPayment($confirmed->fresh());
        $this->assertSame(TransactionStatus::WAITING_PAYMENT->value, $resubmitted->status);

        // Merchant re-confirms
        $reConfirmed = $this->manual->confirmPayment($resubmitted);
        $this->assertSame(TransactionStatus::WAITING_REVIEW->value, $reConfirmed->status);

        // Admin approves
        $this->reviews->approve($reConfirmed, 2, 'Finance Admin');
        $final = $reConfirmed->fresh();
        $this->assertSame(TransactionStatus::COMPLETED->value, $final->status);

        // Verify transaction was created
        $transaction = $this->transactions->findByIntent($final);
        $this->assertNotNull($transaction);
        $this->assertSame('290.00', (string) $transaction->amount);
        $this->assertStringStartsWith('TXN-', $transaction->transaction_number);

        // Verify ledger entry
        $ledgerEntries = $this->ledger->getForTransaction($transaction);
        $this->assertCount(1, $ledgerEntries);
        $this->assertSame('payment_completed', $ledgerEntries->first()->type);

        // Verify timeline has all expected events
        $events = $this->timeline->getForIntent($final);
        $eventTypes = $events->pluck('type')->toArray();

        $expectedEvents = [
            'created', 'evidence_uploaded', 'rejected',
            'comment_added', 'evidence_uploaded', 'resubmitted',
            'completed',
        ];

        foreach ($expectedEvents as $expected) {
            $this->assertContains($expected, $eventTypes, "Missing timeline event: {$expected}");
        }

        // Verify comments (2: admin + merchant)
        $allComments = $this->comments->getForIntent($final);
        $this->assertCount(2, $allComments);
    }

    private function createMinimalSchema(): void
    {
        Schema::create('tenants', function ($table) {
            $table->id(); $table->string('slug')->unique();
            $table->string('name'); $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->json('settings')->nullable();
            $table->string('logo')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('plans', function ($table) {
            $table->id(); $table->string('name'); $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->decimal('yearly_price', 10, 2)->nullable();
            $table->integer('product_limit')->nullable();
            $table->integer('staff_limit')->nullable();
            $table->bigInteger('storage_limit')->nullable();
            $table->integer('orders_monthly_limit')->nullable();
            $table->integer('coupon_limit')->nullable();
            $table->integer('promotion_limit')->nullable();
            $table->integer('flash_sale_limit')->nullable();
            $table->integer('branch_limit')->nullable();
            $table->integer('warehouse_limit')->nullable();
            $table->integer('pos_device_limit')->nullable();
            $table->boolean('analytics_enabled')->default(false);
            $table->boolean('custom_domain_enabled')->default(false);
            $table->string('status', 20)->default('active');
            $table->string('price')->nullable();
            $table->string('currency')->nullable();
            $table->string('interval')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

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
            $table->string('idempotency_key')->nullable();
            $table->timestamps();
            $table->index('tenant_id');
            $table->index('status');
            $table->index('expires_at');
        });

        Schema::create('reference_numbers', function ($table) {
            $table->id();
            $table->string('prefix', 10);
            $table->date('date');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['prefix', 'date']);
        });

        Schema::create('payment_evidences', function ($table) {
            $table->id();
            $table->unsignedBigInteger('payment_intent_id');
            $table->string('type');
            $table->string('file_path')->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('payment_intent_id');
        });

        Schema::create('payment_reviews', function ($table) {
            $table->id();
            $table->unsignedBigInteger('payment_intent_id');
            $table->string('action');
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->string('reviewer_name')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('payment_intent_id');
            $table->index('action');
        });

        Schema::create('payment_transactions', function ($table) {
            $table->id();
            $table->unsignedBigInteger('payment_intent_id')->unique();
            $table->string('transaction_number')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('gateway');
            $table->string('status')->default('completed');
            $table->string('gateway_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('tenant_id');
            $table->index('transaction_number');
            $table->index('status');
        });

        Schema::create('payment_timeline_events', function ($table) {
            $table->id();
            $table->unsignedBigInteger('payment_intent_id');
            $table->string('type');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index('payment_intent_id');
            $table->index('type');
            $table->index('occurred_at');
        });

        Schema::create('payment_comments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('payment_intent_id');
            $table->string('author_type');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('author_name');
            $table->text('body');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('payment_intent_id');
            $table->index('author_type');
        });

        Schema::create('ledger_entries', function ($table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->string('type');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index('transaction_id');
            $table->index('payment_intent_id');
            $table->index('type');
        });
    }
}
