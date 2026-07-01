<?php

namespace Tests\Feature;

use App\Contracts\Webhook\GatewayPayloadParser;
use App\Contracts\Webhook\GatewaySignatureVerifier;
use App\Contracts\Webhook\PaymentGatewayAdapter;
use App\Data\Currency;
use App\Data\Webhook\WebhookEvent;
use App\Data\Webhook\WebhookResult;
use App\Enums\Payment\TransactionStatus;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\WebhookLog;
use App\Services\Payment\Platform\ManualPaymentService;
use App\Services\Payment\Platform\PaymentReviewService;
use App\Services\Webhook\WebhookProcessor;
use App\Services\Webhook\WebhookRouter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WebhookArchitectureTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Plan $plan;
    private ManualPaymentService $manual;
    private PaymentReviewService $reviews;
    private WebhookRouter $router;
    private WebhookProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMinimalSchema();

        $this->tenant = Tenant::create([
            'slug' => 'webhook-test',
            'name' => 'Webhook Test',
            'status' => 'active',
        ]);

        $this->plan = Plan::create([
            'name' => 'Starter', 'slug' => 'starter-webhook',
            'description' => 'Starter plan',
            'monthly_price' => 29, 'yearly_price' => 290, 'status' => 'active',
            'analytics_enabled' => true, 'custom_domain_enabled' => true,
            'product_limit' => 100, 'staff_limit' => 5, 'storage_limit' => 1024,
            'orders_monthly_limit' => 500, 'coupon_limit' => 20,
            'promotion_limit' => 10, 'flash_sale_limit' => 5,
            'branch_limit' => 3, 'warehouse_limit' => 2, 'pos_device_limit' => 3,
        ]);

        $this->manual = $this->app->make(ManualPaymentService::class);
        $this->reviews = $this->app->make(PaymentReviewService::class);
        $this->router = $this->app->make(WebhookRouter::class);
        $this->processor = $this->app->make(WebhookProcessor::class);
    }

    private static function usd(): Currency
    {
        return Currency::fromCode('USD');
    }

    private function createCompletedIntent(): PaymentIntent
    {
        $intent = $this->manual->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $confirmed = $this->manual->confirmPayment($intent);
        $this->reviews->approve($confirmed, 1, 'Admin');

        return $confirmed->fresh();
    }

    /* ── Webhook Router ── */

    public function test_router_resolves_registered_gateway(): void
    {
        $adapter = $this->router->resolve('stripe');

        $this->assertInstanceOf(PaymentGatewayAdapter::class, $adapter);
        $this->assertSame('stripe', $adapter->getGatewayName());
    }

    public function test_router_resolves_all_gateways(): void
    {
        $gateways = ['stripe', 'kpay', 'ayapay', 'wavepay', 'paypal', 'lemonsqueezy', 'paddle'];
        $resolved = [];

        foreach ($gateways as $name) {
            $adapter = $this->router->resolve($name);
            $resolved[] = $adapter->getGatewayName();
        }

        $this->assertSame($gateways, $resolved);
    }

    public function test_router_throws_for_unknown_gateway(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown webhook gateway: unknown_gateway');

        $this->router->resolve('unknown_gateway');
    }

    public function test_router_has_checks_registration(): void
    {
        $this->assertTrue($this->router->has('stripe'));
        $this->assertFalse($this->router->has('nonexistent'));
    }

    public function test_router_lists_registered_gateways(): void
    {
        $gateways = $this->router->getRegisteredGateways();

        $this->assertContains('stripe', $gateways);
        $this->assertContains('paypal', $gateways);
        $this->assertCount(7, $gateways);
    }

    /* ── Gateway Adapter Signature Verification ── */

    public function test_adapter_returns_signature_verifier(): void
    {
        $adapter = $this->router->resolve('stripe');

        $verifier = $adapter->getSignatureVerifier();

        $this->assertInstanceOf(GatewaySignatureVerifier::class, $verifier);
        $this->assertTrue($verifier->verify('{}', []));
    }

    public function test_each_adapter_has_own_signature_verifier(): void
    {
        $stripe = $this->router->resolve('stripe');
        $paypal = $this->router->resolve('paypal');

        $this->assertNotSame(
            $stripe->getSignatureVerifier(),
            $paypal->getSignatureVerifier(),
        );
    }

    /* ── Gateway Adapter Payload Parsing ── */

    public function test_adapter_returns_payload_parser(): void
    {
        $adapter = $this->router->resolve('stripe');

        $parser = $adapter->getPayloadParser();

        $this->assertInstanceOf(GatewayPayloadParser::class, $parser);
    }

    public function test_stripe_parser_returns_webhook_event(): void
    {
        $adapter = $this->router->resolve('stripe');
        $parser = $adapter->getPayloadParser();

        $payload = [
            'id' => 'evt_123',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_123',
                    'amount' => 2900,
                    'currency' => 'usd',
                    'status' => 'succeeded',
                    'metadata' => ['reference_number' => 'PAY-20260701-000001'],
                ],
            ],
        ];

        $event = $parser->parse($payload, []);

        $this->assertInstanceOf(WebhookEvent::class, $event);
        $this->assertSame('stripe', $event->gateway);
        $this->assertSame('payment_intent.succeeded', $event->eventType);
        $this->assertSame('evt_123', $event->gatewayEventId);
        $this->assertSame('pi_123', $event->gatewayReference);
        $this->assertSame('PAY-20260701-000001', $event->referenceNumber);
    }

    public function test_paypal_parser_returns_webhook_event(): void
    {
        $adapter = $this->router->resolve('paypal');
        $parser = $adapter->getPayloadParser();

        $payload = [
            'id' => 'wh_123',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'cap_123',
                'amount' => ['value' => '29.00', 'currency_code' => 'USD'],
                'status' => 'COMPLETED',
                'custom_id' => 'PAY-20260701-000002',
            ],
        ];

        $event = $parser->parse($payload, []);

        $this->assertSame('paypal', $event->gateway);
        $this->assertSame('PAYMENT.CAPTURE.COMPLETED', $event->eventType);
        $this->assertSame('wh_123', $event->gatewayEventId);
        $this->assertSame('cap_123', $event->gatewayReference);
        $this->assertSame('PAY-20260701-000002', $event->referenceNumber);
        $this->assertSame(29.0, $event->amount);
        $this->assertSame('USD', $event->currency);
    }

    /* ── WebhookLog ── */

    public function test_webhook_log_created_on_processing(): void
    {
        $result = $this->processor->process('stripe', [
            'id' => 'evt_log_1',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_log_1']],
        ], ['x-signature' => 'test_sig']);

        $log = WebhookLog::where('gateway_event_id', 'evt_log_1')->first();

        $this->assertNotNull($log);
        $this->assertSame('stripe', $log->gateway);
        $this->assertSame('payment_intent.succeeded', $log->event_type);
        $this->assertSame('pi_log_1', $log->gateway_reference);
    }

    public function test_webhook_log_records_failure_reason(): void
    {
        $result = $this->processor->process('unknown_gateway', [], []);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(WebhookResult::STATUS_FAILED, $result->status);
    }

    public function test_webhook_log_records_payload(): void
    {
        $payload = ['id' => 'evt_payload_1', 'type' => 'test'];

        $this->processor->process('stripe', $payload, []);

        $log = WebhookLog::where('gateway_event_id', 'evt_payload_1')->first();
        $this->assertNotNull($log);
        $this->assertSame($payload, $log->request_payload);
    }

    public function test_webhook_log_records_headers(): void
    {
        $this->processor->process('stripe', [
            'id' => 'evt_headers_1',
            'type' => 'test',
        ], ['x-custom-header' => 'value123']);

        $log = WebhookLog::where('gateway_event_id', 'evt_headers_1')->first();
        $this->assertNotNull($log);
        $this->assertIsArray($log->request_headers);
    }

    public function test_webhook_log_sanitizes_sensitive_headers(): void
    {
        $this->processor->process('stripe', [
            'id' => 'evt_sensitive',
            'type' => 'test',
        ], ['authorization' => 'Bearer secret123', 'x-api-key' => 'key456']);

        $log = WebhookLog::where('gateway_event_id', 'evt_sensitive')->first();
        $this->assertSame('[REDACTED]', $log->request_headers['authorization']);
        $this->assertSame('[REDACTED]', $log->request_headers['x-api-key']);
    }

    /* ── Idempotency (Duplicate Detection) ── */

    public function test_duplicate_webhook_is_detected(): void
    {
        $intent = $this->createCompletedIntent();

        $payload = [
            'id' => 'evt_dup_1',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_dup_1',
                    'metadata' => ['reference_number' => $intent->reference_number],
                ],
            ],
        ];

        $first = $this->processor->process('stripe', $payload, []);
        $this->assertSame(WebhookResult::STATUS_PROCESSED, $first->status);

        $result = $this->processor->process('stripe', $payload, []);

        $this->assertSame(WebhookResult::STATUS_DUPLICATE, $result->status);
    }

    public function test_different_events_not_duplicate(): void
    {
        $intent = $this->createCompletedIntent();

        $payloadA = [
            'id' => 'evt_diff_a',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_diff_a',
                    'metadata' => ['reference_number' => $intent->reference_number],
                ],
            ],
        ];

        $payloadB = [
            'id' => 'evt_diff_b',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_diff_b',
                    'metadata' => ['reference_number' => $intent->reference_number],
                ],
            ],
        ];

        $this->processor->process('stripe', $payloadA, []);

        $result = $this->processor->process('stripe', $payloadB, []);

        $this->assertSame(WebhookResult::STATUS_PROCESSED, $result->status);
    }

    /* ── Processor: Payment Confirmed ── */

    public function test_processor_payment_confirmed_with_intent(): void
    {
        $intent = $this->createCompletedIntent();

        $result = $this->processor->process('stripe', [
            'id' => 'evt_confirm_1',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_confirm_1',
                    'amount' => 2900,
                    'currency' => 'usd',
                    'status' => 'succeeded',
                    'metadata' => ['reference_number' => $intent->reference_number],
                ],
            ],
        ], []);

        $this->assertSame(WebhookResult::STATUS_PROCESSED, $result->status);
        $this->assertStringContainsString('Payment confirmed', $result->message);
    }

    public function test_processor_payment_confirmed_without_intent_returns_failure(): void
    {
        $result = $this->processor->process('stripe', [
            'id' => 'evt_confirm_none',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_none',
                    'amount' => 2900,
                    'currency' => 'usd',
                    'status' => 'succeeded',
                ],
            ],
        ], []);

        $this->assertSame(WebhookResult::STATUS_FAILED, $result->status);
        $this->assertStringContainsString('Payment intent not found', $result->message);
    }

    /* ── Processor: Payment Failed ── */

    public function test_processor_payment_failed_with_intent(): void
    {
        $intent = $this->createCompletedIntent();

        $result = $this->processor->process('stripe', [
            'id' => 'evt_fail_1',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_fail_1',
                    'metadata' => ['reference_number' => $intent->reference_number],
                ],
            ],
        ], []);

        $this->assertSame(WebhookResult::STATUS_PROCESSED, $result->status);
        $this->assertStringContainsString('Payment failed', $result->message);
    }

    /* ── Processor: Refund Received ── */

    public function test_processor_refund_received(): void
    {
        $intent = $this->createCompletedIntent();

        $result = $this->processor->process('stripe', [
            'id' => 'evt_refund_1',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_refund_1',
                    'metadata' => ['reference_number' => $intent->reference_number],
                ],
            ],
        ], []);

        $this->assertSame(WebhookResult::STATUS_PROCESSED, $result->status);
        $this->assertStringContainsString('Refund received', $result->message);
    }

    /* ── Processor: Unhandled Event Type ── */

    public function test_processor_unhandled_event_type(): void
    {
        $result = $this->processor->process('stripe', [
            'id' => 'evt_unhandled',
            'type' => 'unknown.event.type',
            'data' => ['object' => ['id' => 'pi_unhandled']],
        ], []);

        $this->assertSame(WebhookResult::STATUS_UNHANDLED, $result->status);
        $this->assertStringContainsString('Unhandled event type', $result->message);
    }

    /* ── Processor: Unknown Gateway ── */

    public function test_processor_unknown_gateway(): void
    {
        $result = $this->processor->process('completely_unknown', [], []);

        $this->assertSame(WebhookResult::STATUS_FAILED, $result->status);
        $this->assertStringContainsString('Unknown webhook gateway', $result->message);
    }

    /* ── Timeline Integration ── */

    public function test_processor_records_timeline_for_confirmed_payment(): void
    {
        $intent = $this->createCompletedIntent();

        $this->processor->process('stripe', [
            'id' => 'evt_timeline_1',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_timeline_1',
                    'metadata' => ['reference_number' => $intent->reference_number],
                ],
            ],
        ], []);

        $events = $intent->timelineEvents()->where('type', 'payment_confirmed')->get();

        $this->assertCount(1, $events);
        $this->assertStringContainsString('stripe', $events->first()->description);
    }

    /* ── Business Events ── */

    public function test_processor_dispatches_gateway_notification_received_event(): void
    {
        Event::fake();

        $this->processor->process('stripe', [
            'id' => 'evt_dispatch_1',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_dispatch_1']],
        ], []);

        Event::assertDispatched(\App\Events\Webhooks\GatewayNotificationReceived::class);
    }

    public function test_processor_dispatches_payment_confirmed_event(): void
    {
        $intent = $this->createCompletedIntent();

        Event::fake();

        $this->processor->process('stripe', [
            'id' => 'evt_pc_dispatch',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_pc_dispatch',
                    'metadata' => ['reference_number' => $intent->reference_number],
                ],
            ],
        ], []);

        Event::assertDispatched(\App\Events\Webhooks\PaymentConfirmed::class);
    }

    /* ── Security: Header Sanitization ── */

    public function test_sensitive_headers_are_redacted_in_logs(): void
    {
        $this->processor->process('stripe', [
            'id' => 'evt_security',
            'type' => 'test',
        ], ['authorization' => 'Bearer tok_123', 'x-signature' => 'sig_abc']);

        $log = WebhookLog::where('gateway_event_id', 'evt_security')->first();
        $this->assertSame('[REDACTED]', $log->request_headers['authorization']);
        $this->assertSame('[REDACTED]', $log->request_headers['x-signature']);
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

        Schema::create('webhook_logs', function ($table) {
            $table->id();
            $table->string('gateway');
            $table->string('event_type')->nullable();
            $table->string('gateway_event_id')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->string('status');
            $table->json('request_headers')->nullable();
            $table->longText('request_payload')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index('gateway');
            $table->index('gateway_event_id');
            $table->index('status');
            $table->index('payment_intent_id');
            $table->index(['gateway', 'gateway_event_id']);
        });
    }
}
