<?php

namespace App\Services;

use App\Jobs\ProcessOrderStatusChange;
use App\Models\CancellationSetting;
use App\Models\Order;
use App\Models\OrderCancellation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrderCancellationService
{
    public function __construct(
        private readonly AfterSalesSettingsService $settingsService,
        private readonly OrderStatusTransitionService $transitionService,
        private readonly OrderRefundService $refundService,
        private readonly AfterSalesNumberService $numbers,
        private readonly AfterSalesHistoryService $history,
        private readonly AfterSalesNotificationService $notifier,
    ) {}

    public function evaluate(Order $order, string $actorRole, ?Model $actor = null): array
    {
        $settings = $this->settingsService->cancellationSettings($order->tenant_id);
        $rules = $this->settingsService->statusRules($settings);

        $result = [
            'allowed' => false,
            'mode' => null,
            'blocked_reason' => null,
            'redirect_to_returns' => false,
            'limit_exceeded' => false,
            'used_cancellations' => 0,
            'max_cancellations' => $settings->max_cancellations,
            'require_reason' => (bool) $settings->require_reason,
            'require_note' => (bool) $settings->require_note,
            'instant_cancel_window_hours' => $settings->instant_cancel_window_hours,
            'allow_request_after_limit' => (bool) $settings->allow_request_after_limit,
            'reasons' => $settings->reasons ?: CancellationSetting::DEFAULT_REASONS,
            'policy' => $this->policySnapshot($settings, $rules),
        ];

        if ($order->order_status === Order::ORDER_STATUS_CANCELLED) {
            $result['blocked_reason'] = 'This order is already cancelled.';
            return $result;
        }

        $active = OrderCancellation::withoutTenantScope()
            ->where('order_id', $order->id)
            ->whereIn('status', [OrderCancellation::STATUS_REQUESTED, OrderCancellation::STATUS_APPROVED])
            ->exists();

        if ($active) {
            $result['blocked_reason'] = 'A cancellation request is already open for this order.';
            return $result;
        }

        if ($order->hasRestoredReturnStock()) {
            $result['blocked_reason'] = 'Returned items from this order have already been restocked. Use the return workflow instead.';
            return $result;
        }

        $isCustomer = $actorRole === OrderCancellation::ROLE_CUSTOMER;

        if ($isCustomer && !$settings->customer_cancel_enabled) {
            $result['blocked_reason'] = 'Cancellation is disabled for this store.';
            return $result;
        }

        if (!$isCustomer && !$settings->merchant_cancel_enabled) {
            $result['blocked_reason'] = 'Merchant cancellation is disabled for this store.';
            return $result;
        }

        if ($isCustomer) {
            if ($order->order_status === Order::ORDER_STATUS_SHIPPED) {
                $result['blocked_reason'] = 'Cancellation is not available once an order is shipped.';
                return $result;
            }

            if ($order->order_status === Order::ORDER_STATUS_DELIVERED) {
                $result['redirect_to_returns'] = true;
                $result['blocked_reason'] = 'Delivered orders can only be returned, not cancelled.';
                return $result;
            }

            $mode = $rules[$order->order_status] ?? CancellationSetting::MODE_NOT_ALLOWED;

            if ($mode === CancellationSetting::MODE_RETURN_ONLY) {
                $result['redirect_to_returns'] = true;
                $result['blocked_reason'] = 'Delivered orders can only be returned, not cancelled.';
                return $result;
            }

            if ($mode === CancellationSetting::MODE_NOT_ALLOWED) {
                $result['blocked_reason'] = 'Cancellation is not available once an order is ' . $order->order_status . '.';
                return $result;
            }

            if ($mode === CancellationSetting::MODE_INSTANT
                && $settings->instant_cancel_window_hours !== null
                && $order->created_at
                && $order->created_at->lt(now()->subHours($settings->instant_cancel_window_hours))) {
                if (!$settings->allow_request_after_limit) {
                    $result['blocked_reason'] = 'The instant cancellation window for this order has passed.';
                    return $result;
                }

                $mode = CancellationSetting::MODE_APPROVAL;
            }
        } else {
            if ($order->order_status === Order::ORDER_STATUS_DELIVERED) {
                $result['redirect_to_returns'] = true;
                $result['blocked_reason'] = 'Delivered orders cannot be cancelled. Process a return instead.';
                return $result;
            }

            if ($order->order_status === Order::ORDER_STATUS_SHIPPED) {
                $result['blocked_reason'] = 'Shipped orders cannot be cancelled. Process a return instead.';
                return $result;
            }

            $mode = in_array($order->order_status, ['pending', 'confirmed', 'processing'], true)
                ? CancellationSetting::MODE_INSTANT
                : CancellationSetting::MODE_NOT_ALLOWED;
        }

        if ($isCustomer && $actor && $settings->max_cancellations !== null) {
            $used = $this->countRecentCustomerCancellations($order, $actor, (int) $settings->limit_period_days);
            $result['used_cancellations'] = $used;

            if ($used >= (int) $settings->max_cancellations) {
                $result['limit_exceeded'] = true;
                $result['mode'] = $mode;

                if ($settings->limit_exceeded_action === 'require_approval' && $settings->allow_request_after_limit) {
                    $mode = CancellationSetting::MODE_APPROVAL;
                } else {
                    $result['blocked_reason'] = 'You have reached the cancellation limit of '
                        . $settings->max_cancellations . ' within ' . $settings->limit_period_days . ' days.';
                    return $result;
                }
            }
        }

        $result['allowed'] = true;
        $result['mode'] = $mode;

        return $result;
    }

    public function request(Order $order, ?Model $actor, string $actorRole, array $data = []): OrderCancellation
    {
        return DB::transaction(function () use ($order, $actor, $actorRole, $data) {
            $locked = Order::withoutTenantScope()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $evaluation = $this->evaluate($locked, $actorRole, $actor);

            if (!$evaluation['allowed']) {
                throw new \InvalidArgumentException($evaluation['blocked_reason'] ?? 'This order cannot be cancelled.');
            }

            $settings = $this->settingsService->cancellationSettings($locked->tenant_id);
            $reason = $this->resolveReason($settings, $data['reason_code'] ?? null, $data['reason_label'] ?? null);
            $note = trim((string) ($data['note'] ?? '')) ?: null;

            if ($settings->require_reason && !$reason) {
                throw new \InvalidArgumentException('A cancellation reason is required.');
            }

            if ($settings->require_note && !$note) {
                throw new \InvalidArgumentException('Additional cancellation details are required.');
            }

            $cancellation = new OrderCancellation();
            $cancellation->tenant_id = $locked->tenant_id;
            $cancellation->order_id = $locked->id;
            $cancellation->cancellation_number = $this->numbers->nextForTenant(
                $locked->tenant_id,
                'cancellation',
                fn (string $number) => OrderCancellation::withoutTenantScope()->where('cancellation_number', $number)->exists()
            );
            $cancellation->requested_by_type = $actor?->getMorphClass();
            $cancellation->requested_by_id = $actor?->getKey();
            $cancellation->requested_by_role = $actorRole;
            $cancellation->reason_code = $reason['code'] ?? null;
            $cancellation->reason_label = $reason['label'] ?? null;
            $cancellation->customer_note = $note;
            $cancellation->status = OrderCancellation::STATUS_REQUESTED;
            $cancellation->mode = $evaluation['mode'];
            $cancellation->requested_at = now();
            $cancellation->policy_snapshot = $evaluation['policy'];
            $cancellation->save();

            $this->history->record(
                $cancellation,
                $actor,
                $actorRole,
                'cancellation_requested',
                null,
                OrderCancellation::STATUS_REQUESTED,
                $reason['label'] ?? null,
                true
            );

            if ($evaluation['mode'] === CancellationSetting::MODE_INSTANT) {
                return $this->finalize($cancellation, $actor, $actorRole);
            }

            $this->notifier->notifyMerchants(
                $locked,
                'Cancellation requested',
                "Order #{$locked->invoice_number} has a cancellation request awaiting review."
            );

            return $cancellation->fresh();
        });
    }

    public function approve(OrderCancellation $cancellation, ?Model $actor, ?string $note = null): OrderCancellation
    {
        return DB::transaction(function () use ($cancellation, $actor, $note) {
            $locked = OrderCancellation::withoutTenantScope()->whereKey($cancellation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderCancellation::STATUS_REQUESTED) {
                throw new \InvalidArgumentException('Only requested cancellations can be approved.');
            }

            $order = Order::withoutTenantScope()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            if (in_array($order->order_status, [Order::ORDER_STATUS_SHIPPED, Order::ORDER_STATUS_DELIVERED], true)) {
                throw new \InvalidArgumentException('This order can no longer be cancelled because it has been shipped or delivered.');
            }

            $locked->update([
                'status' => OrderCancellation::STATUS_APPROVED,
                'decision_by_type' => $actor?->getMorphClass(),
                'decision_by_id' => $actor?->getKey(),
                'decision_note' => $note,
                'decided_at' => now(),
            ]);

            $this->history->record(
                $locked, $actor, OrderCancellation::ROLE_MERCHANT, 'cancellation_approved',
                OrderCancellation::STATUS_REQUESTED, OrderCancellation::STATUS_APPROVED, $note
            );

            return $this->finalize($locked->fresh(), $actor, OrderCancellation::ROLE_MERCHANT);
        });
    }

    public function reject(OrderCancellation $cancellation, ?Model $actor, ?string $reason = null): OrderCancellation
    {
        return DB::transaction(function () use ($cancellation, $actor, $reason) {
            $locked = OrderCancellation::withoutTenantScope()->whereKey($cancellation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderCancellation::STATUS_REQUESTED) {
                throw new \InvalidArgumentException('Only requested cancellations can be rejected.');
            }

            $locked->update([
                'status' => OrderCancellation::STATUS_REJECTED,
                'decision_by_type' => $actor?->getMorphClass(),
                'decision_by_id' => $actor?->getKey(),
                'rejection_reason' => $reason,
                'decided_at' => now(),
            ]);

            $this->history->record(
                $locked, $actor, OrderCancellation::ROLE_MERCHANT, 'cancellation_rejected',
                OrderCancellation::STATUS_REQUESTED, OrderCancellation::STATUS_REJECTED, $reason
            );

            $order = Order::withoutTenantScope()->find($locked->order_id);
            if ($order) {
                $this->notifier->notifyCustomer(
                    $order,
                    'Cancellation request declined',
                    "Your cancellation request for order #{$order->invoice_number} was declined."
                        . ($reason ? " Reason: {$reason}" : '')
                );
            }

            return $locked->fresh();
        });
    }

    private function finalize(OrderCancellation $cancellation, ?Model $actor, string $actorRole): OrderCancellation
    {
        $order = Order::withoutTenantScope()->whereKey($cancellation->order_id)->lockForUpdate()->firstOrFail();

        if (in_array($order->order_status, [Order::ORDER_STATUS_SHIPPED, Order::ORDER_STATUS_DELIVERED], true)) {
            throw new \InvalidArgumentException('This order can no longer be cancelled because it has been shipped or delivered.');
        }

        $stockReducedBefore = (bool) $order->stock_reduced;
        $oldStatus = $order->order_status;

        if ($order->order_status !== Order::ORDER_STATUS_CANCELLED) {
            $this->transitionService->transition($order, Order::ORDER_STATUS_CANCELLED);
        }

        $order->refresh();

        $refund = null;

        if ($this->refundService->isPaidOrder($order)) {
            $amount = $this->refundService->refundableBalance($order);

            if (!$this->settingsService->cancellationSettings($order->tenant_id)->refund_shipping_fee) {
                $amount = max(0, round($amount - (float) $order->delivery_fee - (float) $order->packaging_fee - (float) $order->cod_fee, 2));
            }

            if ($amount > 0) {
                $refund = $this->refundService->create($order, $amount, [
                    'method' => 'manual',
                    'notes' => 'Auto-created from cancellation ' . $cancellation->cancellation_number,
                    'actor_role' => $actorRole,
                ], $actor, $cancellation);
            }
        }

        $cancellation->update([
            'status' => OrderCancellation::STATUS_COMPLETED,
            'completed_at' => now(),
            'stock_restored_at' => $stockReducedBefore ? now() : null,
            'refund_required' => $refund !== null,
            'refund_id' => $refund?->id,
        ]);

        $this->history->record(
            $cancellation, $actor, $actorRole, 'cancellation_completed',
            OrderCancellation::STATUS_APPROVED, OrderCancellation::STATUS_COMPLETED,
            $refund ? 'Stock restored. Refund ' . $refund->refund_number . ' created.' : null
        );

        ProcessOrderStatusChange::dispatch(
            $order,
            $actorRole === OrderCancellation::ROLE_CUSTOMER ? 'cancelled_by_customer' : 'cancelled_by_admin',
            $oldStatus
        );

        if ($actorRole !== OrderCancellation::ROLE_CUSTOMER) {
            $this->notifier->notifyCustomer(
                $order,
                'Order cancelled',
                "Order #{$order->invoice_number} has been cancelled."
                    . ($refund ? ' A refund of ' . number_format((float) $refund->amount, 2) . ' is pending.' : '')
            );
        } else {
            $this->notifier->notifyMerchants(
                $order,
                'Order cancelled by customer',
                "Order #{$order->invoice_number} was cancelled by the customer."
                    . ($refund ? ' A refund of ' . number_format((float) $refund->amount, 2) . ' is pending.' : '')
            );
        }

        return $cancellation->fresh();
    }

    private function countRecentCustomerCancellations(Order $order, Model $actor, int $periodDays): int
    {
        return OrderCancellation::withoutTenantScope()
            ->where('tenant_id', $order->tenant_id)
            ->where('requested_by_role', OrderCancellation::ROLE_CUSTOMER)
            ->where('requested_by_type', $actor->getMorphClass())
            ->where('requested_by_id', $actor->getKey())
            ->where('status', OrderCancellation::STATUS_COMPLETED)
            ->where('requested_at', '>=', now()->subDays(max(1, $periodDays)))
            ->count();
    }

    private function resolveReason(CancellationSetting $settings, ?string $code, ?string $label): ?array
    {
        $reasons = $settings->reasons ?: CancellationSetting::DEFAULT_REASONS;

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

    private function policySnapshot(CancellationSetting $settings, array $rules): array
    {
        return [
            'customer_cancel_enabled' => $settings->customer_cancel_enabled,
            'merchant_cancel_enabled' => $settings->merchant_cancel_enabled,
            'status_rules' => $rules,
            'instant_cancel_window_hours' => $settings->instant_cancel_window_hours,
            'max_cancellations' => $settings->max_cancellations,
            'limit_period_days' => $settings->limit_period_days,
            'limit_exceeded_action' => $settings->limit_exceeded_action,
            'require_reason' => $settings->require_reason,
            'require_note' => $settings->require_note,
            'refund_shipping_fee' => $settings->refund_shipping_fee,
            'captured_at' => now()->toIso8601String(),
        ];
    }
}
