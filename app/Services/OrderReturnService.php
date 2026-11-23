<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderCancellation;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\ReturnSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrderReturnService
{
    private const OPEN_STATUSES = [
        OrderReturn::STATUS_REQUESTED,
        OrderReturn::STATUS_APPROVED,
        OrderReturn::STATUS_AWAITING_SHIPMENT,
        OrderReturn::STATUS_IN_TRANSIT,
        OrderReturn::STATUS_RECEIVED,
        OrderReturn::STATUS_INSPECTION,
    ];

    private const COUNTING_STATUSES = [
        OrderReturn::STATUS_REQUESTED,
        OrderReturn::STATUS_APPROVED,
        OrderReturn::STATUS_AWAITING_SHIPMENT,
        OrderReturn::STATUS_IN_TRANSIT,
        OrderReturn::STATUS_RECEIVED,
        OrderReturn::STATUS_INSPECTION,
        OrderReturn::STATUS_COMPLETED,
    ];

    public function __construct(
        private readonly AfterSalesSettingsService $settingsService,
        private readonly OrderRefundService $refundService,
        private readonly OrderService $orderService,
        private readonly AfterSalesNumberService $numbers,
        private readonly AfterSalesHistoryService $history,
        private readonly AfterSalesNotificationService $notifier,
    ) {}

    public function eligibility(Order $order): array
    {
        $settings = $this->settingsService->returnSettings($order->tenant_id);
        $result = [
            'allowed' => false,
            'reason' => null,
            'settings' => $settings,
            'window_days' => $settings->return_window_days,
            'delivered_at' => null,
            'days_remaining' => null,
            'items' => [],
            'has_open_return' => false,
        ];

        if (!$settings->returns_enabled) {
            $result['reason'] = 'Returns are disabled for this store.';
            return $result;
        }

        if ($order->order_status !== Order::ORDER_STATUS_DELIVERED) {
            $result['reason'] = 'Only delivered orders can be returned.';
            return $result;
        }

        if (!$order->delivered_at) {
            $result['reason'] = 'Delivery date is not recorded for this order; returns cannot be processed automatically. Contact the store.';
            return $result;
        }

        $deliveredAt = $order->delivered_at;
        $result['delivered_at'] = $deliveredAt?->toIso8601String();

        if ($deliveredAt) {
            $deadline = $deliveredAt->copy()->addDays((int) $settings->return_window_days);
            $result['window_days'] = $settings->return_window_days;
            $result['days_remaining'] = (int) now()->diffInDays($deadline, false);

            if (now()->greaterThan($deadline)) {
                $result['reason'] = 'The return window of ' . $settings->return_window_days . ' days has expired.';
                return $result;
            }
        }

        $result['has_open_return'] = OrderReturn::withoutTenantScope()
            ->where('order_id', $order->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->exists();

        if ($result['has_open_return']) {
            $result['reason'] = 'A return request is already open for this order.';
            return $result;
        }

        $items = $this->availableQuantities($order);
        $result['items'] = $items;

        if (collect($items)->sum('available') <= 0) {
            $result['reason'] = 'Every item on this order has already been returned.';
            return $result;
        }

        $result['allowed'] = true;

        return $result;
    }

    public function availableQuantities(Order $order): array
    {
        $order->loadMissing('items.product', 'items.variant');

        $used = OrderReturnItem::withoutTenantScope()
            ->whereIn('order_return_id', function ($query) use ($order) {
                $query->select('id')->from('order_returns')
                    ->where('order_id', $order->id)
                    ->whereIn('status', self::COUNTING_STATUSES);
            })
            ->selectRaw('order_item_id, SUM(quantity) as returned_quantity')
            ->groupBy('order_item_id')
            ->pluck('returned_quantity', 'order_item_id');

        $result = [];

        foreach ($order->items as $item) {
            $returned = (int) ($used[$item->id] ?? 0);
            $available = max(0, (int) $item->quantity - $returned);

            $result[$item->id] = [
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'name' => $item->product?->name ?? ('Product #' . $item->product_id),
                'variant' => $item->variant?->label,
                'quantity' => (int) $item->quantity,
                'returned' => $returned,
                'available' => $available,
                'price' => (float) $item->price,
            ];
        }

        return $result;
    }

    public function request(Order $order, Model $actor, array $data): OrderReturn
    {
        return DB::transaction(function () use ($order, $actor, $data) {
            $locked = Order::withoutTenantScope()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $eligibility = $this->eligibility($locked);

            if (!$eligibility['allowed']) {
                throw new \InvalidArgumentException($eligibility['reason'] ?? 'This order is not eligible for a return.');
            }

            $settings = $eligibility['settings'];
            $requestedItems = $this->normalizeItems($data['items'] ?? [], $eligibility['items']);

            if (!$settings->allow_partial_return) {
                foreach ($eligibility['items'] as $item) {
                    if ($item['available'] <= 0) {
                        continue;
                    }

                    $requested = collect($requestedItems)->firstWhere('order_item_id', $item['order_item_id']);

                    if (!$requested || (int) $requested['quantity'] !== (int) $item['available']) {
                        throw new \InvalidArgumentException('This store only accepts full returns.');
                    }
                }
            }

            $resolution = $data['resolution'] ?? OrderReturn::RESOLUTION_REFUND;
            $allowedResolutions = $this->allowedResolutions($settings);

            if (!in_array($resolution, $allowedResolutions, true)) {
                throw new \InvalidArgumentException('The selected resolution is not available.');
            }

            $reason = $this->resolveReason($settings, $data['reason_code'] ?? null, $data['reason_label'] ?? null);

            if ($settings->require_reason && !$reason) {
                throw new \InvalidArgumentException('A return reason is required.');
            }

            $note = trim((string) ($data['note'] ?? '')) ?: null;

            if ($settings->require_note && !$note) {
                throw new \InvalidArgumentException('Additional return details are required.');
            }

            $evidence = array_values(array_filter($data['evidence'] ?? []));

            if ($settings->require_evidence && empty($evidence)) {
                throw new \InvalidArgumentException('At least one evidence photo is required.');
            }

            $responsibility = $this->shippingResponsibility($settings, $reason);

            $return = new OrderReturn();
            $return->tenant_id = $locked->tenant_id;
            $return->order_id = $locked->id;
            $return->rma_number = $this->numbers->nextForTenant(
                $locked->tenant_id,
                'return',
                fn (string $number) => OrderReturn::withoutTenantScope()->where('rma_number', $number)->exists()
            );
            $return->requested_by_type = $actor->getMorphClass();
            $return->requested_by_id = $actor->getKey();
            $return->status = OrderReturn::STATUS_REQUESTED;
            $return->resolution = $resolution;
            $return->reason_code = $reason['code'] ?? null;
            $return->reason_label = $reason['label'] ?? null;
            $return->customer_note = $note;
            $return->evidence = $evidence ?: null;
            $return->shipping_responsibility = $responsibility;
            $return->refund_shipping_fee = (bool) $settings->refund_shipping_fee;
            $return->requested_at = now();
            $return->expires_at = $locked->delivered_at
                ? $locked->delivered_at->copy()->addDays((int) $settings->return_window_days)
                : now()->addDays((int) $settings->return_window_days);
            $return->policy_snapshot = [
                'return_window_days' => $settings->return_window_days,
                'allow_full_return' => $settings->allow_full_return,
                'allow_partial_return' => $settings->allow_partial_return,
                'allow_refund' => $settings->allow_refund,
                'allow_exchange' => $settings->allow_exchange,
                'allow_replacement' => $settings->allow_replacement,
                'require_evidence' => $settings->require_evidence,
                'refund_shipping_fee' => $settings->refund_shipping_fee,
                'captured_at' => now()->toIso8601String(),
            ];
            $return->save();

            foreach ($requestedItems as $item) {
                OrderReturnItem::withoutTenantScope()->create([
                    'tenant_id' => $locked->tenant_id,
                    'order_return_id' => $return->id,
                    'order_item_id' => $item['order_item_id'],
                    'quantity' => $item['quantity'],
                    'condition' => OrderReturnItem::CONDITION_PENDING,
                    'restock' => false,
                ]);
            }

            $this->history->record(
                $return, $actor, OrderCancellation::ROLE_CUSTOMER, 'return_requested',
                null, OrderReturn::STATUS_REQUESTED, $reason['label'] ?? null
            );

            $this->notifier->notifyMerchants(
                $locked,
                'Return requested',
                "Return {$return->rma_number} for order #{$locked->invoice_number} is awaiting review."
            );

            return $return->fresh(['items']);
        });
    }

    public function approve(OrderReturn $return, ?Model $actor, ?string $note = null): OrderReturn
    {
        return DB::transaction(function () use ($return, $actor, $note) {
            $locked = OrderReturn::withoutTenantScope()->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderReturn::STATUS_REQUESTED) {
                throw new \InvalidArgumentException('Only requested returns can be approved.');
            }

            $locked->update([
                'status' => OrderReturn::STATUS_AWAITING_SHIPMENT,
                'approved_at' => now(),
                'decision_by_type' => $actor?->getMorphClass(),
                'decision_by_id' => $actor?->getKey(),
                'decision_note' => $note,
            ]);

            $this->history->record(
                $locked, $actor, OrderCancellation::ROLE_MERCHANT, 'return_approved',
                OrderReturn::STATUS_REQUESTED, OrderReturn::STATUS_AWAITING_SHIPMENT, $note
            );

            $order = Order::withoutTenantScope()->find($locked->order_id);
            if ($order) {
                $this->notifier->notifyCustomer(
                    $order,
                    'Return approved',
                    "Return {$locked->rma_number} was approved. Please ship the items back to the store."
                );
            }

            return $locked->fresh();
        });
    }

    public function reject(OrderReturn $return, ?Model $actor, ?string $reason = null): OrderReturn
    {
        return DB::transaction(function () use ($return, $actor, $reason) {
            $locked = OrderReturn::withoutTenantScope()->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderReturn::STATUS_REQUESTED) {
                throw new \InvalidArgumentException('Only requested returns can be rejected.');
            }

            $locked->update([
                'status' => OrderReturn::STATUS_REJECTED,
                'rejected_at' => now(),
                'decision_by_type' => $actor?->getMorphClass(),
                'decision_by_id' => $actor?->getKey(),
                'rejection_reason' => $reason,
            ]);

            $this->history->record(
                $locked, $actor, OrderCancellation::ROLE_MERCHANT, 'return_rejected',
                OrderReturn::STATUS_REQUESTED, OrderReturn::STATUS_REJECTED, $reason
            );

            $order = Order::withoutTenantScope()->find($locked->order_id);
            if ($order) {
                $this->notifier->notifyCustomer(
                    $order,
                    'Return request declined',
                    "Return {$locked->rma_number} was declined." . ($reason ? " Reason: {$reason}" : '')
                );
            }

            return $locked->fresh();
        });
    }

    public function markInTransit(OrderReturn $return, Model $actor, string $actorRole, array $tracking = []): OrderReturn
    {
        return DB::transaction(function () use ($return, $actor, $actorRole, $tracking) {
            $locked = OrderReturn::withoutTenantScope()->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if (!$locked->canTransitionTo(OrderReturn::STATUS_IN_TRANSIT)) {
                throw new \InvalidArgumentException('This return cannot be marked as shipped from its current status.');
            }

            $attributes = array_merge($this->trackingAttributes($tracking), [
                'status' => OrderReturn::STATUS_IN_TRANSIT,
                'shipped_at' => $locked->shipped_at ?? now(),
            ]);

            $from = $locked->status;
            $locked->update($attributes);

            $this->history->record(
                $locked, $actor, $actorRole, 'return_in_transit', $from, OrderReturn::STATUS_IN_TRANSIT,
                $locked->tracking_note
            );

            $order = Order::withoutTenantScope()->find($locked->order_id);
            if ($order) {
                $this->notifier->notifyCustomer(
                    $order,
                    'Return shipment in transit',
                    "Return {$locked->rma_number} is on its way back to the store."
                        . ($locked->tracking_number ? " Tracking: {$locked->tracking_number}" : '')
                );
            }

            return $locked->fresh();
        });
    }

    public function updateTracking(OrderReturn $return, ?Model $actor, array $tracking, string $actorRole = OrderCancellation::ROLE_MERCHANT): OrderReturn
    {
        return DB::transaction(function () use ($return, $actor, $tracking, $actorRole) {
            $locked = OrderReturn::withoutTenantScope()->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if (!in_array($locked->status, [
                OrderReturn::STATUS_APPROVED,
                OrderReturn::STATUS_AWAITING_SHIPMENT,
                OrderReturn::STATUS_IN_TRANSIT,
                OrderReturn::STATUS_RECEIVED,
                OrderReturn::STATUS_INSPECTION,
            ], true)) {
                throw new \InvalidArgumentException('Tracking details can no longer be updated for this return.');
            }

            $attributes = $this->trackingAttributes($tracking);

            if (empty($attributes)) {
                throw new \InvalidArgumentException('Provide at least one tracking detail to update.');
            }

            $locked->update($attributes);

            $this->history->record(
                $locked, $actor, $actorRole, 'return_tracking_updated', null, null,
                $this->trackingSummary($locked)
            );

            $order = Order::withoutTenantScope()->find($locked->order_id);
            if ($order) {
                $this->notifier->notifyCustomer(
                    $order,
                    'Return shipment updated',
                    "Shipment details for return {$locked->rma_number} were updated."
                        . ($locked->tracking_number ? " Tracking: {$locked->tracking_number}" : '')
                );
            }

            return $locked->fresh();
        });
    }

    private function trackingAttributes(array $tracking): array
    {
        $attributes = [];

        foreach (['carrier_name', 'tracking_number', 'tracking_note'] as $key) {
            if (array_key_exists($key, $tracking)) {
                $value = trim((string) $tracking[$key]);
                $attributes[$key] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('tracking_url', $tracking)) {
            $attributes['tracking_url'] = OrderReturn::sanitizeTrackingUrl($tracking['tracking_url']);
        }

        if (array_key_exists('shipped_at', $tracking)) {
            $attributes['shipped_at'] = $tracking['shipped_at'] ?: null;
        }

        return $attributes;
    }

    private function trackingSummary(OrderReturn $return): string
    {
        $parts = array_filter([
            $return->carrier_name,
            $return->tracking_number,
            $return->tracking_url,
        ]);

        return $parts ? 'Tracking updated: ' . implode(' · ', $parts) : 'Tracking details cleared.';
    }

    public function receive(OrderReturn $return, ?Model $actor, array $items = []): OrderReturn
    {
        return DB::transaction(function () use ($return, $actor, $items) {
            $locked = OrderReturn::withoutTenantScope()->with('items')->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if (!in_array($locked->status, [OrderReturn::STATUS_APPROVED, OrderReturn::STATUS_AWAITING_SHIPMENT, OrderReturn::STATUS_IN_TRANSIT, OrderReturn::STATUS_RECEIVED], true)) {
                throw new \InvalidArgumentException('This return cannot be received from its current status.');
            }

            $received = $this->normalizeReceived($items, $locked->items);
            $from = $locked->status;

            foreach ($locked->items as $item) {
                $quantity = (int) ($received[$item->id] ?? $item->quantity);

                if ($quantity < 0 || $quantity > $item->quantity) {
                    throw new \InvalidArgumentException('Received quantity cannot exceed the requested return quantity.');
                }

                $item->update(['received_quantity' => $quantity]);
            }

            $locked->update([
                'status' => OrderReturn::STATUS_RECEIVED,
                'received_at' => now(),
            ]);

            $this->history->record($locked, $actor, OrderCancellation::ROLE_MERCHANT, 'return_received', $from, OrderReturn::STATUS_RECEIVED);

            $order = Order::withoutTenantScope()->find($locked->order_id);
            if ($order) {
                $this->notifier->notifyCustomer(
                    $order,
                    'Return received',
                    "Return {$locked->rma_number} was received by the store and is awaiting inspection."
                );
            }

            return $locked->fresh(['items']);
        });
    }

    public function inspect(OrderReturn $return, ?Model $actor, array $items = []): OrderReturn
    {
        return DB::transaction(function () use ($return, $actor, $items) {
            $locked = OrderReturn::withoutTenantScope()->with('items.orderItem.product')->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if (!in_array($locked->status, [OrderReturn::STATUS_RECEIVED, OrderReturn::STATUS_INSPECTION], true)) {
                throw new \InvalidArgumentException('Only received returns can be inspected.');
            }

            $from = $locked->status;
            $quantities = [];

            foreach ($locked->items as $item) {
                $payload = $items[$item->id] ?? [];
                $condition = $payload['condition'] ?? OrderReturnItem::CONDITION_RESELLABLE;

                if (!in_array($condition, [OrderReturnItem::CONDITION_RESELLABLE, OrderReturnItem::CONDITION_DAMAGED], true)) {
                    $condition = OrderReturnItem::CONDITION_DAMAGED;
                }

                $restock = $condition === OrderReturnItem::CONDITION_RESELLABLE && (bool) ($payload['restock'] ?? true);
                $quantity = (int) ($item->received_quantity ?: $item->quantity);

                $item->update([
                    'condition' => $condition,
                    'restock' => $restock,
                    'inspection_note' => $payload['note'] ?? null,
                ]);

                if ($restock && !$item->stock_restored_at && $quantity > 0) {
                    $quantities[$item->order_item_id] = ($quantities[$item->order_item_id] ?? 0) + $quantity;
                    $item->update(['stock_restored_at' => now()]);
                } elseif (!$restock) {
                    $this->history->record(
                        $locked, $actor, OrderCancellation::ROLE_MERCHANT, 'return_item_write_off',
                        null, null,
                        'Item written off (not restocked): ' . ($item->orderItem?->product?->name ?? 'item #' . $item->order_item_id)
                    );
                }
            }

            if (!empty($quantities)) {
                $this->orderService->restoreStockForItems(
                    Order::withoutTenantScope()->findOrFail($locked->order_id),
                    $quantities,
                    'order_return',
                    $locked->id,
                    "Return {$locked->rma_number} restocked"
                );
            }

            $locked->update([
                'status' => OrderReturn::STATUS_INSPECTION,
                'inspected_at' => now(),
            ]);

            $this->history->record($locked, $actor, OrderCancellation::ROLE_MERCHANT, 'return_inspected', $from, OrderReturn::STATUS_INSPECTION);

            $order = Order::withoutTenantScope()->find($locked->order_id);
            if ($order) {
                $this->notifier->notifyCustomer(
                    $order,
                    'Return inspection completed',
                    "Inspection for return {$locked->rma_number} is complete. The store will finalise your resolution shortly."
                );
            }

            return $locked->fresh(['items']);
        });
    }

    public function complete(OrderReturn $return, ?Model $actor, array $data = []): OrderReturn
    {
        return DB::transaction(function () use ($return, $actor, $data) {
            $locked = OrderReturn::withoutTenantScope()->with('items.orderItem')->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if (!in_array($locked->status, [OrderReturn::STATUS_RECEIVED, OrderReturn::STATUS_INSPECTION], true)) {
                throw new \InvalidArgumentException('Only received or inspected returns can be completed.');
            }

            $order = Order::withoutTenantScope()->whereKey($locked->order_id)->firstOrFail();
            $refund = null;
            $replacement = null;

            if ($locked->resolution === OrderReturn::RESOLUTION_REFUND) {
                $amount = $this->refundAmount($locked, $order);

                if ($amount > 0) {
                    $refund = $this->refundService->create($order, $amount, [
                        'method' => $data['method'] ?? 'manual',
                        'notes' => 'Created from return ' . $locked->rma_number,
                        'actor_role' => OrderCancellation::ROLE_MERCHANT,
                    ], $actor, $locked);
                }
            } else {
                $replacement = $this->createReplacementOrder($locked, $order, $data);
            }

            $from = $locked->status;

            $locked->update([
                'status' => OrderReturn::STATUS_COMPLETED,
                'completed_at' => now(),
                'refund_id' => $refund?->id,
                'replacement_order_id' => $replacement?->id,
                'merchant_note' => $data['merchant_note'] ?? $locked->merchant_note,
            ]);

            $this->history->record(
                $locked, $actor, OrderCancellation::ROLE_MERCHANT, 'return_completed',
                $from, OrderReturn::STATUS_COMPLETED,
                $refund ? 'Refund ' . $refund->refund_number . ' created.' : ($replacement ? 'Replacement order #' . $replacement->invoice_number . ' created.' : null)
            );

            $this->notifier->notifyCustomer(
                $order,
                'Return completed',
                "Return {$locked->rma_number} has been completed."
                    . ($refund ? ' A refund of ' . number_format((float) $refund->amount, 2) . ' is pending.' : '')
            );

            return $locked->fresh(['items', 'refund', 'replacementOrder']);
        });
    }

    public function cancel(OrderReturn $return, Model $actor, string $actorRole): OrderReturn
    {
        return DB::transaction(function () use ($return, $actor, $actorRole) {
            $locked = OrderReturn::withoutTenantScope()->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if (!$locked->canTransitionTo(OrderReturn::STATUS_CANCELLED)) {
                throw new \InvalidArgumentException('This return can no longer be cancelled.');
            }

            $from = $locked->status;
            $locked->update(['status' => OrderReturn::STATUS_CANCELLED]);

            $this->history->record($locked, $actor, $actorRole, 'return_cancelled', $from, OrderReturn::STATUS_CANCELLED);

            $order = Order::withoutTenantScope()->find($locked->order_id);
            if ($order) {
                if ($actorRole === OrderCancellation::ROLE_CUSTOMER) {
                    $this->notifier->notifyMerchants(
                        $order,
                        'Return cancelled by customer',
                        "Return {$locked->rma_number} for order #{$order->invoice_number} was cancelled by the customer."
                    );
                } else {
                    $this->notifier->notifyCustomer(
                        $order,
                        'Return request cancelled',
                        "Return {$locked->rma_number} has been cancelled by the store."
                    );
                }
            }

            return $locked->fresh();
        });
    }

    public function expire(OrderReturn $return): OrderReturn
    {
        if (!$return->canTransitionTo(OrderReturn::STATUS_EXPIRED)) {
            return $return;
        }

        $from = $return->status;
        $return->update(['status' => OrderReturn::STATUS_EXPIRED]);
        $this->history->record($return, null, OrderCancellation::ROLE_SYSTEM, 'return_expired', $from, OrderReturn::STATUS_EXPIRED);

        return $return->fresh();
    }

    public function refundAmount(OrderReturn $return, Order $order): float
    {
        $return->loadMissing('items.orderItem');

        $goods = 0.0;
        foreach ($return->items as $item) {
            $quantity = (int) ($item->received_quantity ?: $item->quantity);
            $goods += (float) ($item->orderItem?->price ?? 0) * $quantity;
        }

        if ($return->refund_shipping_fee) {
            $goods += (float) $return->shipping_fee_amount;
        }

        return round(min($goods, $this->refundService->refundableBalance($order)), 2);
    }

    private function createReplacementOrder(OrderReturn $return, Order $order, array $data): Order
    {
        $return->loadMissing('items.orderItem.product', 'items.orderItem.variant');

        $stockItems = [];
        foreach ($return->items as $item) {
            $orderItem = $item->orderItem;

            if (!$orderItem) {
                continue;
            }

            $stockItems[] = [
                'product_id' => $orderItem->product_id,
                'variant_id' => $orderItem->variant_id,
                'quantity' => (int) $item->quantity,
                'price' => (float) $orderItem->price,
            ];
        }

        $this->orderService->validateStock($stockItems);

        $replacement = Order::withoutTenantScope()->create([
            'tenant_id' => $order->tenant_id,
            'user_id' => $order->user_id,
            'user_type' => $order->user_type,
            'customer_name' => $order->customer_name,
            'first_name' => $order->first_name,
            'last_name' => $order->last_name,
            'phone' => $order->phone,
            'email' => $order->email,
            'address' => $order->address,
            'city_id' => $order->city_id,
            'township_id' => $order->township_id,
            'postal_code' => $order->postal_code,
            'payment_method_id' => $order->payment_method_id,
            'subtotal' => 0,
            'total_amount' => 0,
            'delivery_fee' => 0,
            'discount_amount' => 0,
            'paid_amount' => 0,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'stock_reduced' => false,
            'notes' => 'Replacement for return ' . $return->rma_number . ' (original order #' . ($order->invoice_number ?: $order->id) . ')',
            'idempotency_key' => 'RET-' . $return->id,
        ]);

        foreach ($stockItems as $item) {
            $replacement->items()->create([
                'tenant_id' => $order->tenant_id,
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);
        }

        $this->orderService->applyStockReduction($replacement);

        $this->history->record(
            $return, null, OrderCancellation::ROLE_SYSTEM, 'replacement_created',
            null, null,
            'Replacement order ' . $replacement->invoice_number . ' created.'
        );

        return $replacement->fresh();
    }

    private function normalizeItems(array $items, array $available): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $orderItemId = (int) ($item['order_item_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if (!isset($available[$orderItemId])) {
                throw new \InvalidArgumentException('One of the selected items does not belong to this order.');
            }

            if ($quantity <= 0) {
                continue;
            }

            if ($quantity > $available[$orderItemId]['available']) {
                throw new \InvalidArgumentException('Return quantity exceeds the returnable quantity for ' . $available[$orderItemId]['name'] . '.');
            }

            $normalized[] = ['order_item_id' => $orderItemId, 'quantity' => $quantity];
        }

        if (empty($normalized)) {
            throw new \InvalidArgumentException('Select at least one item to return.');
        }

        return $normalized;
    }

    private function normalizeReceived(array $items, $returnItems): array
    {
        $result = [];

        foreach ($items as $key => $value) {
            if (is_array($value)) {
                $result[(int) ($value['id'] ?? $key)] = (int) ($value['received_quantity'] ?? $value['quantity'] ?? 0);
            } else {
                $result[(int) $key] = (int) $value;
            }
        }

        return $result;
    }

    private function allowedResolutions(ReturnSetting $settings): array
    {
        $resolutions = [];

        if ($settings->allow_refund) {
            $resolutions[] = OrderReturn::RESOLUTION_REFUND;
        }
        if ($settings->allow_exchange) {
            $resolutions[] = OrderReturn::RESOLUTION_EXCHANGE;
        }
        if ($settings->allow_replacement) {
            $resolutions[] = OrderReturn::RESOLUTION_REPLACEMENT;
        }

        return $resolutions ?: [OrderReturn::RESOLUTION_REFUND];
    }

    private function shippingResponsibility(ReturnSetting $settings, ?array $reason): string
    {
        $map = $settings->shipping_responsibility_by_reason ?? [];

        if ($reason && isset($map[$reason['code']])) {
            return $map[$reason['code']] === 'merchant' ? 'merchant' : 'customer';
        }

        return $settings->return_shipping_responsibility === 'merchant' ? 'merchant' : 'customer';
    }

    private function resolveReason(ReturnSetting $settings, ?string $code, ?string $label): ?array
    {
        $reasons = $settings->reasons ?: ReturnSetting::DEFAULT_REASONS;

        if ($code) {
            foreach ($reasons as $reason) {
                if (($reason['code'] ?? null) === $code) {
                    return $reason;
                }
            }
        }

        if ($label) {
            foreach ($reasons as $reason) {
                if (($reason['label'] ?? null) === $label) {
                    return $reason;
                }
            }

            return ['code' => $code ?: 'other', 'label' => $label];
        }

        return null;
    }
}
