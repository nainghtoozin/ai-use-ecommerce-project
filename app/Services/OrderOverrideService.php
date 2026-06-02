<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderOverrideLog;
use App\Models\Tenant;

class OrderOverrideService
{
    private const VALID_ORDER_STATUSES = [
        Order::ORDER_STATUS_PENDING,
        Order::ORDER_STATUS_CONFIRMED,
        Order::ORDER_STATUS_PROCESSING,
        Order::ORDER_STATUS_SHIPPED,
        Order::ORDER_STATUS_DELIVERED,
        Order::ORDER_STATUS_CANCELLED,
    ];

    private const VALID_PAYMENT_STATUSES = [
        Order::PAYMENT_STATUS_PENDING,
        Order::PAYMENT_STATUS_PAID,
        Order::PAYMENT_STATUS_FAILED,
        Order::PAYMENT_STATUS_REFUNDED,
    ];

    public function overrideOrderStatus(Order $order, string $newStatus, string $reason): void
    {
        $this->validateOrderStatus($order, $newStatus);

        $oldStatus = $order->order_status;

        $order->update(['order_status' => $newStatus]);

        $this->logOverride($order, 'order_status', $oldStatus, $newStatus, $reason);

        ActivityLogger::log(
            "Super Admin overrode order #{$order->id} status from '{$oldStatus}' to '{$newStatus}'. Reason: {$reason}",
            'order_status_override',
            $order,
            [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
            ],
            'order_override'
        );
    }

    public function overridePaymentStatus(Order $order, string $newStatus, string $reason): void
    {
        $this->validatePaymentStatus($order, $newStatus);

        $oldStatus = $order->payment_status;

        $data = ['payment_status' => $newStatus];

        if ($newStatus === Order::PAYMENT_STATUS_PAID) {
            $data['payment_verified_at'] = now();
            $data['rejection_reason'] = null;
        } elseif ($newStatus === Order::PAYMENT_STATUS_PENDING) {
            $data['payment_verified_at'] = null;
            $data['rejection_reason'] = null;
        } elseif ($newStatus === Order::PAYMENT_STATUS_FAILED || $newStatus === Order::PAYMENT_STATUS_REFUNDED) {
            $data['rejection_reason'] = $reason;
            $data['payment_verified_at'] = null;
        }

        $order->update($data);

        $this->logOverride($order, 'payment_status', $oldStatus, $newStatus, $reason);

        ActivityLogger::log(
            "Super Admin overrode payment #{$order->id} status from '{$oldStatus}' to '{$newStatus}'. Reason: {$reason}",
            'payment_status_override',
            $order,
            [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
            ],
            'order_override'
        );
    }

    private function validateOrderStatus(Order $order, string $newStatus): void
    {
        if ($order->order_status === $newStatus) {
            throw new \InvalidArgumentException('Order already has this status.');
        }

        if (!in_array($newStatus, self::VALID_ORDER_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid order status '{$newStatus}'.");
        }
    }

    private function validatePaymentStatus(Order $order, string $newStatus): void
    {
        if ($order->payment_status === $newStatus) {
            throw new \InvalidArgumentException('Payment already has this status.');
        }

        if (!in_array($newStatus, self::VALID_PAYMENT_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid payment status '{$newStatus}'.");
        }
    }

    private function logOverride(Order $order, string $field, string $oldValue, string $newValue, string $reason): void
    {
        OrderOverrideLog::create([
            'order_id' => $order->id,
            'user_id' => auth()->id(),
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'tenant_id' => Tenant::getCurrent()?->id,
        ]);
    }
}
