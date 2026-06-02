<?php

namespace App\Services;

use App\Models\Order;

class OrderWorkflow
{
    public function canConfirmOrder(Order $order): bool
    {
        if ($order->order_status !== Order::ORDER_STATUS_PENDING) {
            return false;
        }

        if ($this->isCod($order)) {
            return true;
        }

        return $order->payment_status === Order::PAYMENT_STATUS_PAID;
    }

    public function canProcessOrder(Order $order): bool
    {
        return $order->order_status === Order::ORDER_STATUS_CONFIRMED;
    }

    public function canShipOrder(Order $order): bool
    {
        return $order->order_status === Order::ORDER_STATUS_PROCESSING;
    }

    public function canDeliverOrder(Order $order): bool
    {
        return $order->order_status === Order::ORDER_STATUS_SHIPPED;
    }

    public function isCod(Order $order): bool
    {
        if (!$order->relationLoaded('paymentMethod')) {
            $order->load('paymentMethod');
        }

        return $order->paymentMethod && $order->paymentMethod->type === 'cod';
    }

    public function assertCanConfirmOrder(Order $order): void
    {
        if (!$this->canConfirmOrder($order)) {
            throw new \InvalidArgumentException(
                $this->isCod($order)
                    ? 'This order cannot be confirmed.'
                    : 'Order cannot be confirmed. Payment must be completed for bank transfer orders.'
            );
        }
    }

    public function assertCanProcessOrder(Order $order): void
    {
        if (!$this->canProcessOrder($order)) {
            throw new \InvalidArgumentException('This order cannot be moved to processing.');
        }
    }

    public function assertCanShipOrder(Order $order): void
    {
        if (!$this->canShipOrder($order)) {
            throw new \InvalidArgumentException('This order cannot be shipped.');
        }
    }

    public function assertCanDeliverOrder(Order $order): void
    {
        if (!$this->canDeliverOrder($order)) {
            throw new \InvalidArgumentException('This order cannot be marked as delivered.');
        }
    }
}
