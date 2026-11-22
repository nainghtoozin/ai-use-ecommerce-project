<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\Product;
use App\Models\ReturnSetting;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Jobs\SendAfterSalesExternalNotificationJob;
use App\Notifications\AfterSalesStatusNotification;
use App\Services\OrderReturnService;
use App\Services\OrderRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderReturnRefundAfterSalesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $customer;
    private User $merchant;
    private Product $product;
    private OrderReturnService $returns;
    private OrderRefundService $refunds;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Notification::fake();

        $this->tenant = Tenant::create([
            'name' => 'Return Refund Store',
            'slug' => 'return-refund-store',
            'store_url' => '/store/return-refund-store',
            'status' => 'active',
        ]);

        Tenant::setCurrent($this->tenant);
        $this->grantStorefrontEntitlement($this->tenant);

        $this->customer = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Return Customer',
            'email' => 'return-customer@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->merchant = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Return Merchant',
            'email' => 'return-merchant@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $category = Category::create(['name' => 'Return Cat', 'slug' => 'return-cat']);

        $this->product = Product::create([
            'name' => 'Return Widget',
            'type' => 'single',
            'price' => 1000,
            'stock' => 50,
            'status' => 'active',
            'category_id' => $category->id,
        ]);

        $this->returns = app(OrderReturnService::class);
        $this->refunds = app(OrderRefundService::class);
    }

    private function makeDeliveredOrder(array $overrides = [], int $quantity = 2): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $this->customer->id,
            'user_type' => User::class,
            'first_name' => 'Return',
            'last_name' => 'Customer',
            'phone' => '09911122233',
            'address' => 'No. 2, Return Road',
            'subtotal' => 1000 * $quantity,
            'total_amount' => 1000 * $quantity,
            'delivery_fee' => 0,
            'paid_amount' => 1000 * $quantity,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'order_status' => Order::ORDER_STATUS_DELIVERED,
            'delivered_at' => now()->subDay(),
            'stock_reduced' => true,
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'price' => 1000,
        ]);

        return $order;
    }

    private function settings(): ReturnSetting
    {
        return app(\App\Services\AfterSalesSettingsService::class)
            ->returnSettings($this->tenant->id);
    }

    private function requestReturn(Order $order, int $quantity = 1, array $extra = []): OrderReturn
    {
        $item = $order->items()->first();

        return $this->returns->request($order, $this->customer, array_merge([
            'items' => [['order_item_id' => $item->id, 'quantity' => $quantity]],
            'resolution' => OrderReturn::RESOLUTION_REFUND,
            'reason_code' => 'damaged',
            'note' => 'Arrived with a scratch',
        ], $extra));
    }

    private function advanceToReceived(OrderReturn $return): OrderReturn
    {
        $this->returns->approve($return, $this->merchant, 'Approved');

        return $this->returns->receive($return->fresh(), $this->merchant);
    }

    public function test_delivered_order_within_window_is_return_eligible(): void
    {
        $order = $this->makeDeliveredOrder();

        $eligibility = $this->returns->eligibility($order);

        $this->assertTrue($eligibility['allowed']);
        $this->assertSame(7, $eligibility['window_days']);
        $this->assertGreaterThan(0, $eligibility['days_remaining']);
    }

    public function test_return_window_expiry_blocks_eligibility(): void
    {
        $order = $this->makeDeliveredOrder(['delivered_at' => now()->subDays(10)]);

        $eligibility = $this->returns->eligibility($order);

        $this->assertFalse($eligibility['allowed']);
        $this->assertStringContainsString('expired', $eligibility['reason']);
    }

    public function test_undelivered_order_cannot_be_returned(): void
    {
        $order = $this->makeDeliveredOrder([
            'order_status' => Order::ORDER_STATUS_SHIPPED,
            'delivered_at' => null,
        ]);

        $eligibility = $this->returns->eligibility($order);

        $this->assertFalse($eligibility['allowed']);
        $this->assertStringContainsString('Only delivered', $eligibility['reason']);
    }

    public function test_full_return_flow_restocks_once_and_creates_refund(): void
    {
        $order = $this->makeDeliveredOrder();

        $return = $this->requestReturn($order, 1);
        $this->assertSame(OrderReturn::STATUS_REQUESTED, $return->status);

        $received = $this->advanceToReceived($return);
        $this->assertSame(OrderReturn::STATUS_RECEIVED, $received->status);
        $this->assertSame(0, StockMovement::where('type', StockMovement::TYPE_RETURN)->count());

        $inspected = $this->returns->inspect($received->fresh(), $this->merchant, [
            $received->items->first()->id => ['condition' => OrderReturnItem::CONDITION_RESELLABLE, 'restock' => true],
        ]);

        $this->assertSame(OrderReturn::STATUS_INSPECTION, $inspected->status);
        $this->assertNotNull($inspected->items->first()->stock_restored_at);
        $this->assertSame(1, StockMovement::where('type', StockMovement::TYPE_RETURN)->count());

        $this->returns->inspect($inspected->fresh(), $this->merchant, [
            $inspected->items->first()->id => ['condition' => OrderReturnItem::CONDITION_RESELLABLE, 'restock' => true],
        ]);
        $this->assertSame(1, StockMovement::where('type', StockMovement::TYPE_RETURN)->count(), 'Stock must not be restored twice.');

        $completed = $this->returns->complete($inspected->fresh(), $this->merchant);
        $this->assertSame(OrderReturn::STATUS_COMPLETED, $completed->status);
        $this->assertNotNull($completed->refund_id);
        $this->assertSame(1000.0, round((float) $completed->refund->amount, 2));
        $this->assertSame(OrderRefund::STATUS_PENDING, $completed->refund->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
    }

    public function test_damaged_item_is_written_off_without_restock(): void
    {
        $order = $this->makeDeliveredOrder();
        $received = $this->advanceToReceived($this->requestReturn($order, 1));

        $inspected = $this->returns->inspect($received->fresh(), $this->merchant, [
            $received->items->first()->id => ['condition' => OrderReturnItem::CONDITION_DAMAGED, 'restock' => false],
        ]);

        $this->assertSame(OrderReturnItem::CONDITION_DAMAGED, $inspected->items->first()->condition);
        $this->assertNull($inspected->items->first()->stock_restored_at);
        $this->assertSame(0, StockMovement::where('type', StockMovement::TYPE_RETURN)->count());
        $this->assertTrue($inspected->histories()->where('action', 'return_item_write_off')->exists());
    }

    public function test_full_return_only_store_rejects_partial_return(): void
    {
        $order = $this->makeDeliveredOrder([], 3);
        $this->settings()->update(['allow_partial_return' => false]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('full returns');

        $this->requestReturn($order, 1);
    }

    public function test_unpaid_order_cannot_receive_refund_record(): void
    {
        $order = $this->makeDeliveredOrder([
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'paid_amount' => 0,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->refunds->create($order, 500, [], $this->merchant);
    }

    public function test_refund_cannot_exceed_paid_balance(): void
    {
        $order = $this->makeDeliveredOrder([], 2);

        $this->refunds->create($order, 1500, [], $this->merchant);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds the remaining refundable balance');

        $this->refunds->create($order, 700, [], $this->merchant);
    }

    public function test_refund_lifecycle_moves_payment_status_from_partial_to_refunded(): void
    {
        $order = $this->makeDeliveredOrder([], 2);

        $first = $this->refunds->create($order, 1000, [], $this->merchant);
        $this->assertSame(OrderRefund::STATUS_PENDING, $first->status);
        $this->assertSame(1000.0, $this->refunds->refundableBalance($order));

        $this->refunds->markProcessing($first, $this->merchant);
        $this->refunds->complete($first->fresh(), $this->merchant);

        $order->refresh();
        $this->assertSame(Order::PAYMENT_STATUS_PARTIALLY_REFUNDED, $order->payment_status);
        $this->assertSame(1000.0, $this->refunds->refundableBalance($order));

        $second = $this->refunds->create($order->fresh(), 1000, [], $this->merchant);
        $this->refunds->complete($second, $this->merchant);

        $this->assertSame(Order::PAYMENT_STATUS_REFUNDED, $order->fresh()->payment_status);
        $this->assertSame(0.0, $this->refunds->refundableBalance($order->fresh()));
    }

    public function test_duplicate_open_return_is_blocked(): void
    {
        $order = $this->makeDeliveredOrder();
        $this->requestReturn($order, 1);

        $this->expectException(\InvalidArgumentException::class);

        $this->requestReturn($order, 1);
    }

    public function test_mark_in_transit_moves_awaiting_shipment_return_and_records_history(): void
    {
        $order = $this->makeDeliveredOrder();
        $approved = $this->returns->approve($this->requestReturn($order, 1), $this->merchant, 'Approved');

        $transit = $this->returns->markInTransit($approved, $this->merchant, 'merchant', [
            'carrier_name' => 'DHL',
            'tracking_number' => 'TRK-778',
            'tracking_url' => 'https://dhl.test/track/TRK-778',
            'tracking_note' => 'Handed to courier',
        ]);

        $this->assertSame(OrderReturn::STATUS_IN_TRANSIT, $transit->status);
        $this->assertSame('DHL', $transit->carrier_name);
        $this->assertSame('TRK-778', $transit->tracking_number);
        $this->assertSame('https://dhl.test/track/TRK-778', $transit->safe_tracking_url);
        $this->assertNotNull($transit->shipped_at);

        $entry = $transit->histories()->where('action', 'return_in_transit')->first();

        $this->assertNotNull($entry);
        $this->assertSame(OrderReturn::STATUS_AWAITING_SHIPMENT, $entry->from_status);
        $this->assertSame(OrderReturn::STATUS_IN_TRANSIT, $entry->to_status);
        $this->assertSame('Handed to courier', $entry->note);
        $this->assertTrue($entry->is_customer_visible);
        Notification::assertSentTo($this->customer, \App\Notifications\AfterSalesStatusNotification::class);
    }

    public function test_mark_in_transit_rejects_invalid_and_repeated_transitions(): void
    {
        $order = $this->makeDeliveredOrder();
        $requested = $this->requestReturn($order, 1);

        try {
            $this->returns->markInTransit($requested, $this->merchant, 'merchant');
            $this->fail('A requested return cannot be marked in transit.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot be marked as shipped', $e->getMessage());
        }

        $approved = $this->returns->approve($requested->fresh(), $this->merchant);
        $inTransit = $this->returns->markInTransit($approved, $this->merchant, 'merchant');

        $this->assertSame(OrderReturn::STATUS_IN_TRANSIT, $inTransit->status);
        $this->assertSame(1, $inTransit->histories()->where('action', 'return_in_transit')->count());

        $this->expectException(\InvalidArgumentException::class);
        $this->returns->markInTransit($inTransit->fresh(), $this->merchant, 'merchant');
    }

    public function test_tracking_details_can_be_updated_without_changing_status(): void
    {
        $order = $this->makeDeliveredOrder();
        $approved = $this->returns->approve($this->requestReturn($order, 1), $this->merchant);
        $inTransit = $this->returns->markInTransit($approved, $this->merchant, 'merchant', [
            'carrier_name' => 'DHL',
            'tracking_number' => 'TRK-1',
        ]);

        $updated = $this->returns->updateTracking($inTransit, $this->merchant, [
            'carrier_name' => 'FedEx',
            'tracking_number' => 'FX-9',
            'tracking_url' => 'https://fedex.test/FX-9',
            'tracking_note' => 'Updated handling',
        ]);

        $this->assertSame(OrderReturn::STATUS_IN_TRANSIT, $updated->status);
        $this->assertSame('FedEx', $updated->carrier_name);
        $this->assertSame('FX-9', $updated->tracking_number);
        $this->assertSame('https://fedex.test/FX-9', $updated->safe_tracking_url);
        $this->assertSame('Updated handling', $updated->tracking_note);
        $this->assertSame(1, $updated->histories()->where('action', 'return_in_transit')->count());
        $this->assertSame(1, $updated->histories()->where('action', 'return_tracking_updated')->count());
        $this->assertSame(OrderReturn::STATUS_IN_TRANSIT, $updated->fresh()->status);
    }

    public function test_unsafe_tracking_urls_are_discarded(): void
    {
        $this->assertNull(OrderReturn::sanitizeTrackingUrl('javascript:alert(1)'));
        $this->assertNull(OrderReturn::sanitizeTrackingUrl('data:text/html,<script>alert(1)</script>'));
        $this->assertNull(OrderReturn::sanitizeTrackingUrl('ftp://carrier.test/track'));
        $this->assertNull(OrderReturn::sanitizeTrackingUrl('not a url'));
        $this->assertSame('http://carrier.test/track', OrderReturn::sanitizeTrackingUrl('http://carrier.test/track'));

        $order = $this->makeDeliveredOrder();
        $approved = $this->returns->approve($this->requestReturn($order, 1), $this->merchant);

        $inTransit = $this->returns->markInTransit($approved, $this->merchant, 'merchant', [
            'tracking_url' => 'javascript:alert(1)',
            'tracking_number' => 'SAFE-1',
        ]);

        $this->assertNull($inTransit->tracking_url);
        $this->assertNull($inTransit->safe_tracking_url);
        $this->assertNull($inTransit->trackingPayload()['tracking_url']);
        $this->assertSame('SAFE-1', $inTransit->trackingPayload()['tracking_number']);
    }

    public function test_raw_tracking_url_is_hidden_from_serialization(): void
    {
        $order = $this->makeDeliveredOrder();
        $return = $this->requestReturn($order, 1);
        $return->forceFill(['tracking_url' => 'https://carrier.test/raw'])->save();

        $this->assertArrayNotHasKey('tracking_url', $return->fresh()->toArray());
        $this->assertSame('https://carrier.test/raw', $return->fresh()->trackingPayload()['tracking_url']);
    }

    public function test_tracking_cannot_be_updated_after_completion(): void
    {
        $order = $this->makeDeliveredOrder();
        $approved = $this->returns->approve($this->requestReturn($order, 1), $this->merchant);
        $inTransit = $this->returns->markInTransit($approved, $this->merchant, 'merchant');
        $received = $this->returns->receive($inTransit->fresh(), $this->merchant);
        $inspected = $this->returns->inspect($received->fresh(), $this->merchant, [
            $received->items->first()->id => ['condition' => OrderReturnItem::CONDITION_RESELLABLE, 'restock' => true],
        ]);
        $completed = $this->returns->complete($inspected->fresh(), $this->merchant);
        $this->assertSame(OrderReturn::STATUS_COMPLETED, $completed->status);

        $this->expectException(\InvalidArgumentException::class);
        $this->returns->updateTracking($completed, $this->merchant, ['tracking_number' => 'LATE']);
    }

    public function test_external_notification_jobs_are_queued_for_merchant_and_customer(): void
    {
        $order = $this->makeDeliveredOrder();
        $return = $this->requestReturn($order, 1);

        Queue::assertPushed(SendAfterSalesExternalNotificationJob::class, fn ($job) => $job->audience === 'merchant');
        Queue::assertPushed(SendAfterSalesExternalNotificationJob::class, fn ($job) => $job->orderId === $order->id && $job->tenantId === $this->tenant->id);

        $this->returns->approve($return, $this->merchant);

        Queue::assertPushed(SendAfterSalesExternalNotificationJob::class, fn ($job) => $job->audience === 'customer');
    }

    public function test_customer_notifications_target_only_the_order_customer(): void
    {
        $order = $this->makeDeliveredOrder();
        $return = $this->requestReturn($order, 1);
        $this->returns->approve($return, $this->merchant);

        Notification::assertSentTo($this->customer, AfterSalesStatusNotification::class);
        Notification::assertNotSentTo($this->merchant, AfterSalesStatusNotification::class);
    }

    public function test_no_notification_is_sent_for_a_rejected_transition(): void
    {
        $order = $this->makeDeliveredOrder();
        $requested = $this->requestReturn($order, 1);
        $this->returns->approve($requested, $this->merchant);

        Notification::assertSentToTimes($this->customer, AfterSalesStatusNotification::class, 1);

        try {
            $this->returns->approve($requested->fresh(), $this->merchant);
            $this->fail('An already approved return cannot be approved again.');
        } catch (\InvalidArgumentException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        Notification::assertSentToTimes($this->customer, AfterSalesStatusNotification::class, 1);
    }

    public function test_partial_return_restocks_only_returned_quantity_and_refunds_the_remainder(): void
    {
        $order = $this->makeDeliveredOrder([], 3);

        $return = $this->requestReturn($order, 2);
        $approved = $this->returns->approve($return, $this->merchant);
        $received = $this->returns->receive($approved, $this->merchant);

        $this->assertSame(0, StockMovement::where('type', StockMovement::TYPE_RETURN)->count(), 'Receiving alone must not restock.');

        $inspected = $this->returns->inspect($received->fresh(), $this->merchant, [
            $received->items->first()->id => ['condition' => OrderReturnItem::CONDITION_RESELLABLE, 'restock' => true],
        ]);

        $movement = StockMovement::where('type', StockMovement::TYPE_RETURN)->first();
        $this->assertNotNull($movement);
        $this->assertSame(2, (int) $movement->quantity);

        $completed = $this->returns->complete($inspected->fresh(), $this->merchant);
        $this->assertSame(2000.0, round((float) $completed->refund->amount, 2));

        $this->refunds->complete($completed->refund, $this->merchant);

        $this->assertSame(Order::PAYMENT_STATUS_PARTIALLY_REFUNDED, $order->fresh()->payment_status);
    }

    public function test_refund_processing_and_failure_notify_the_customer(): void
    {
        $order = $this->makeDeliveredOrder();

        $refund = $this->refunds->create($order, 500, ['actor_role' => 'merchant'], $this->merchant);
        Notification::assertSentToTimes($this->customer, AfterSalesStatusNotification::class, 1);

        $this->refunds->markProcessing($refund, $this->merchant);
        Notification::assertSentToTimes($this->customer, AfterSalesStatusNotification::class, 2);

        $this->refunds->fail($refund->fresh(), $this->merchant, 'Bank rejected');
        Notification::assertSentToTimes($this->customer, AfterSalesStatusNotification::class, 3);

        $this->assertSame(OrderRefund::STATUS_FAILED, $refund->fresh()->status);
    }
}
