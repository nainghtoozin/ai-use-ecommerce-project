<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderCancellation;
use App\Models\OrderRefund;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrderRefundService
{
    public function __construct(
        private readonly AfterSalesNumberService $numbers,
        private readonly AfterSalesHistoryService $history,
        private readonly AfterSalesNotificationService $notifier,
    ) {}

    public function isPaidOrder(Order $order): bool
    {
        return in_array($order->payment_status, [
            Order::PAYMENT_STATUS_PAID,
            Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        ], true);
    }

    public function effectivePaidAmount(Order $order): float
    {
        $paid = (float) ($order->paid_amount ?? 0);

        if ($paid <= 0) {
            $paid = (float) $order->total_amount;
        }

        return round(max(0, $paid), 2);
    }

    public function reservedAmount(Order $order): float
    {
        return round((float) OrderRefund::withoutTenantScope()
            ->where('order_id', $order->id)
            ->whereIn('status', OrderRefund::RESERVING_STATUSES)
            ->sum('amount'), 2);
    }

    public function completedAmount(Order $order): float
    {
        return round((float) OrderRefund::withoutTenantScope()
            ->where('order_id', $order->id)
            ->where('status', OrderRefund::STATUS_COMPLETED)
            ->sum('amount'), 2);
    }

    public function refundableBalance(Order $order): float
    {
        if (!$this->isPaidOrder($order)) {
            return 0.0;
        }

        return max(0.0, round($this->effectivePaidAmount($order) - $this->reservedAmount($order), 2));
    }

    public function create(Order $order, float $amount, array $data = [], ?Model $actor = null, ?Model $source = null): OrderRefund
    {
        return DB::transaction(function () use ($order, $amount, $data, $actor, $source) {
            $locked = Order::withoutTenantScope()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (!$this->isPaidOrder($locked)) {
                throw new \InvalidArgumentException('Unpaid orders cannot receive a refund record.');
            }

            $amount = round($amount, 2);

            if ($amount <= 0) {
                throw new \InvalidArgumentException('Refund amount must be greater than zero.');
            }

            $remaining = $this->refundableBalance($locked);

            if ($amount > $remaining + 0.001) {
                throw new \InvalidArgumentException(
                    'Refund amount exceeds the remaining refundable balance of ' . number_format($remaining, 2) . '.'
                );
            }

            $method = $data['method'] ?? 'manual';
            if (!in_array($method, OrderRefund::METHODS, true)) {
                $method = 'manual';
            }

            $refund = new OrderRefund();
            $refund->tenant_id = $locked->tenant_id;
            $refund->order_id = $locked->id;
            $refund->refund_number = $this->numbers->nextForTenant(
                $locked->tenant_id,
                'refund',
                fn (string $number) => OrderRefund::withoutTenantScope()->where('refund_number', $number)->exists()
            );
            $refund->amount = $amount;
            $refund->method = $method;
            $refund->status = OrderRefund::STATUS_PENDING;
            $refund->notes = $data['notes'] ?? null;
            $refund->reference = $data['reference'] ?? null;
            $refund->evidence_path = $data['evidence_path'] ?? null;
            $refund->requested_by_type = $actor?->getMorphClass();
            $refund->requested_by_id = $actor?->getKey();

            if ($source) {
                $refund->source_type = $source->getMorphClass();
                $refund->source_id = $source->getKey();
            }

            $refund->save();

            $this->history->record(
                $refund,
                $actor,
                $data['actor_role'] ?? null,
                'refund_requested',
                null,
                OrderRefund::STATUS_PENDING,
                "Pending refund of {$refund->amount} created.",
                true
            );

            $actorRole = $data['actor_role'] ?? null;
            $actorIsMerchant = $actorRole === OrderCancellation::ROLE_MERCHANT;

            if ($source === null) {
                if ($actorIsMerchant) {
                    $this->notifier->notifyCustomer($locked, 'Refund initiated',
                        'A refund of ' . number_format($amount, 2) . " was initiated for order #{$locked->invoice_number}.");
                } else {
                    $this->notifier->notifyMerchants($locked, 'Refund requires processing',
                        "Order #{$locked->invoice_number} has a pending refund of " . number_format($amount, 2) . ' awaiting processing.');
                }
            }

            return $refund;
        });
    }

    public function markProcessing(OrderRefund $refund, ?Model $actor = null): OrderRefund
    {
        return DB::transaction(function () use ($refund, $actor) {
            $locked = OrderRefund::withoutTenantScope()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderRefund::STATUS_PENDING) {
                throw new \InvalidArgumentException('Only pending refunds can be moved to processing.');
            }

            $locked->update([
                'status' => OrderRefund::STATUS_PROCESSING,
                'processed_by_type' => $actor?->getMorphClass(),
                'processed_by_id' => $actor?->getKey(),
            ]);

            $this->history->record($locked, $actor, $this->actorRole($actor), 'refund_processing', OrderRefund::STATUS_PENDING, OrderRefund::STATUS_PROCESSING);

            $order = Order::withoutTenantScope()->find($locked->order_id);
            if ($order) {
                $this->notifier->notifyCustomer($order, 'Refund processing',
                    'Your refund of ' . number_format((float) $locked->amount, 2) . " for order #{$order->invoice_number} is being processed.");
            }

            return $locked->fresh();
        });
    }

    public function complete(OrderRefund $refund, ?Model $actor = null, array $data = []): OrderRefund
    {
        return DB::transaction(function () use ($refund, $actor, $data) {
            $locked = OrderRefund::withoutTenantScope()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === OrderRefund::STATUS_COMPLETED) {
                return $locked;
            }

            if ($locked->status === OrderRefund::STATUS_FAILED) {
                throw new \InvalidArgumentException('Failed refunds must be recreated instead of completed.');
            }

            $from = $locked->status;

            $locked->update([
                'status' => OrderRefund::STATUS_COMPLETED,
                'reference' => $data['reference'] ?? $locked->reference,
                'evidence_path' => $data['evidence_path'] ?? $locked->evidence_path,
                'notes' => $data['notes'] ?? $locked->notes,
                'processed_by_type' => $actor?->getMorphClass(),
                'processed_by_id' => $actor?->getKey(),
                'processed_at' => now(),
            ]);

            $order = Order::withoutTenantScope()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
            $this->syncPaymentStatus($order);

            $this->history->record($locked, $actor, $this->actorRole($actor), 'refund_completed', $from, OrderRefund::STATUS_COMPLETED,
                "Refund of {$locked->amount} recorded as completed.");
            $this->notifier->notifyCustomer($order, 'Refund processed',
                "A refund of {$locked->amount} for order #{$order->invoice_number} has been processed.");

            return $locked->fresh();
        });
    }

    public function fail(OrderRefund $refund, ?Model $actor = null, ?string $reason = null): OrderRefund
    {
        return DB::transaction(function () use ($refund, $actor, $reason) {
            $locked = OrderRefund::withoutTenantScope()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === OrderRefund::STATUS_COMPLETED) {
                throw new \InvalidArgumentException('Completed refunds cannot be marked as failed.');
            }

            $from = $locked->status;

            $locked->update([
                'status' => OrderRefund::STATUS_FAILED,
                'failure_reason' => $reason,
                'processed_by_type' => $actor?->getMorphClass(),
                'processed_by_id' => $actor?->getKey(),
                'processed_at' => now(),
            ]);

            $this->history->record($locked, $actor, $this->actorRole($actor), 'refund_failed', $from, OrderRefund::STATUS_FAILED, $reason);

            $order = Order::withoutTenantScope()->find($locked->order_id);
            if ($order) {
                $this->notifier->notifyCustomer($order, 'Refund could not be completed',
                    'The refund of ' . number_format((float) $locked->amount, 2) . " for order #{$order->invoice_number} failed."
                        . ($reason ? " Reason: {$reason}" : '') . ' The store will follow up shortly.');
            }

            return $locked->fresh();
        });
    }

    public function syncPaymentStatus(Order $order): void
    {
        $completed = $this->completedAmount($order);
        $paid = $this->effectivePaidAmount($order);

        if ($completed <= 0) {
            return;
        }

        $status = $completed + 0.01 >= $paid
            ? Order::PAYMENT_STATUS_REFUNDED
            : Order::PAYMENT_STATUS_PARTIALLY_REFUNDED;

        if ($order->payment_status !== $status) {
            $order->update(['payment_status' => $status]);
        }
    }

    private function actorRole(?Model $actor): ?string
    {
        if (!$actor) {
            return null;
        }

        return method_exists($actor, 'isSuperAdmin') && $actor->isSuperAdmin() ? 'merchant' : 'merchant';
    }
}
