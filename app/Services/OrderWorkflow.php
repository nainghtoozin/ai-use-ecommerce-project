<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Tenant;

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

    public function workflowMode(): string
    {
        $tenant = Tenant::getCurrent();

        $mode = is_array($tenant?->settings) ? ($tenant->settings['workflow_mode'] ?? 'standard') : 'standard';

        return $mode === 'simplified' ? 'simplified' : 'standard';
    }

    public function isSimplified(): bool
    {
        return $this->workflowMode() === 'simplified';
    }

    public function canAcceptAndProcess(Order $order): bool
    {
        return $this->isSimplified() && $this->canConfirmOrder($order);
    }

    public function isCod(Order $order): bool
    {
        if (!$order->relationLoaded('paymentMethod')) {
            $order->load('paymentMethod');
        }

        if ($order->paymentMethod) {
            return $order->paymentMethod->type === 'cod';
        }

        return $order->payment_method_id === null;
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
