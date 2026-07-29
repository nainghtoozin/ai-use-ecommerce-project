<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class OrderStatusTransitionService
{
    private const APPLY = 'apply';
    private const REVERSE = 'reverse';
    private const NONE = 'none';

    private const TRANSITION_MATRIX = [
        'pending' => [
            'confirmed'  => self::APPLY,
            'processing' => self::APPLY,
            'shipped'    => self::APPLY,
            'delivered'  => self::APPLY,
            'cancelled'  => self::NONE,
        ],
        'confirmed' => [
            'pending'    => self::REVERSE,
            'processing' => self::NONE,
            'shipped'    => self::NONE,
            'delivered'  => self::NONE,
            'cancelled'  => self::REVERSE,
        ],
        'processing' => [
            'pending'    => self::REVERSE,
            'confirmed'  => self::NONE,
            'shipped'    => self::NONE,
            'delivered'  => self::NONE,
            'cancelled'  => self::REVERSE,
        ],
        'shipped' => [
            'pending'    => self::REVERSE,
            'confirmed'  => self::NONE,
            'processing' => self::NONE,
            'delivered'  => self::NONE,
            'cancelled'  => self::REVERSE,
        ],
        'delivered' => [
            'pending'    => self::REVERSE,
            'confirmed'  => self::REVERSE,
            'processing' => self::REVERSE,
            'shipped'    => self::NONE,
            'cancelled'  => self::REVERSE,
        ],
        'cancelled' => [
            'pending'    => self::NONE,
            'confirmed'  => self::APPLY,
            'processing' => self::APPLY,
            'shipped'    => self::APPLY,
            'delivered'  => self::APPLY,
        ],
    ];

    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function transition(Order $order, string $newStatus): void
    {
        $oldStatus = $order->order_status;

        if ($oldStatus === $newStatus) {
            Log::info("Order #{$order->id} already at status '{$newStatus}', no action taken.");
            return;
        }

        $action = self::TRANSITION_MATRIX[$oldStatus][$newStatus] ?? null;

        if ($action === null) {
            throw new \InvalidArgumentException(
                "Invalid order status transition: {$oldStatus} → {$newStatus}"
            );
        }

        Log::info("Order #{$order->invoice_number} status transition: {$oldStatus} → {$newStatus}, inventory action: {$action}");

        $order->update(['order_status' => $newStatus]);

        match ($action) {
            self::APPLY => $this->orderService->applyStockReduction($order),
            self::REVERSE => $this->orderService->reverseStockReduction($order),
            self::NONE => null,
        };

        Log::info("Order #{$order->invoice_number} transition complete. stock_reduced: " . ($order->fresh()->stock_reduced ? 'true' : 'false'));
    }

    public function getInventoryAction(string $fromStatus, string $toStatus): string
    {
        return self::TRANSITION_MATRIX[$fromStatus][$toStatus] ?? 'invalid';
    }

    public function isValidTransition(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus) {
            return true;
        }

        return isset(self::TRANSITION_MATRIX[$fromStatus][$toStatus]);
    }
}
