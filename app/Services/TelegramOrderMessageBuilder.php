<?php

namespace App\Services;

use App\Data\TelegramPayload;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class TelegramOrderMessageBuilder
{
    public function buildNewOrder(Order $order): TelegramPayload
    {
        $order->loadMissing('items.product', 'paymentMethod', 'tenant');

        $lines = [];
        $lines[] = "<b>🛒 New Order Received</b>";
        $lines[] = "";
        $lines[] = "📋 <b>Order:</b> #{$order->id}";
        $lines[] = "👤 <b>Customer:</b> " . e($order->customer_name);
        $lines[] = "📞 <b>Phone:</b> " . e($order->phone);
        $lines[] = "📅 <b>Date:</b> " . $order->created_at->format('M j, Y g:i A');
        $lines[] = "📦 <b>Items:</b> " . $order->items->count();
        $lines[] = "💰 <b>Total:</b> " . number_format((float) $order->total_amount) . ' ' . config('payments.default_currency');
        $lines[] = "💳 <b>Payment:</b> " . ($order->paymentMethod?->name ?? 'N/A');

        if ($order->order_status) {
            $lines[] = "📊 <b>Status:</b> " . ucfirst($order->order_status);
        }

        if ((float) ($order->delivery_fee ?? 0) > 0) {
            $lines[] = "🚚 <b>Delivery:</b> " . number_format((float) $order->delivery_fee) . ' ' . config('payments.default_currency');
        }

        if ($order->tenant) {
            $lines[] = "🏪 <b>Shop:</b> " . e($order->tenant->name);
        }

        if ($order->items->isNotEmpty()) {
            $lines[] = "";
            $lines[] = "<b>Products:</b>";

            foreach ($order->items as $item) {
                $productName = $item->product?->name ?? "Product #{$item->product_id}";
                $itemTotal = (float) $item->price * (int) $item->quantity;
                $lines[] = "  • " . e($productName) . " x{$item->quantity} — " . number_format($itemTotal) . ' ' . config('payments.default_currency');
            }
        }

        $lines[] = "";
        $lines[] = "🕐 " . now()->format('M j, Y g:i A');

        $message = implode("\n", $lines);

        Log::info('[TelegramOrderMessageBuilder] Payload generated', [
            'notification_type' => 'order.new',
            'order_id' => $order->id,
            'length' => strlen($message),
        ]);

        return new TelegramPayload(
            message: $message,
            notificationType: 'order.new',
            destination: 'order',
            context: [
                'notification_type' => 'order.new',
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'created_at' => now()->toIso8601String(),
            ],
        );
    }

    public function buildStatusChange(Order $order, string $event, ?string $oldStatus = null, ?string $rejectionReason = null): TelegramPayload
    {
        $order->loadMissing('paymentMethod', 'tenant');

        $emoji = match ($event) {
            'confirmed' => '✅',
            'processing' => '🔄',
            'shipped' => '📦',
            'delivered' => '✅',
            'cancelled_by_admin', 'cancelled_by_customer' => '❌',
            'payment_verified' => '💳',
            'payment_rejected' => '❌',
            'payment_proof_uploaded' => '📎',
            default => '📋',
        };

        $label = match ($event) {
            'confirmed' => 'Order Confirmed',
            'processing' => 'Order Processing',
            'shipped' => 'Order Shipped',
            'delivered' => 'Order Delivered',
            'cancelled_by_admin' => 'Order Cancelled by Admin',
            'cancelled_by_customer' => 'Order Cancelled by Customer',
            'payment_verified' => 'Payment Verified',
            'payment_rejected' => 'Payment Rejected',
            'payment_proof_uploaded' => 'Payment Proof Uploaded',
            default => 'Status Updated',
        };

        $lines = [];
        $lines[] = "<b>{$emoji} {$label}</b>";
        $lines[] = "";
        $lines[] = "📋 <b>Order:</b> #{$order->id}";
        $lines[] = "👤 <b>Customer:</b> " . e($order->customer_name);
        $lines[] = "📞 <b>Phone:</b> " . e($order->phone);
        $lines[] = "💰 <b>Total:</b> " . number_format((float) $order->total_amount) . ' ' . config('payments.default_currency');
        $lines[] = "💳 <b>Payment:</b> " . ($order->paymentMethod?->name ?? 'N/A');

        if ($oldStatus) {
            $lines[] = "📊 <b>Status:</b> {$oldStatus} → {$order->order_status}";
        } else {
            $lines[] = "📊 <b>Status:</b> {$order->order_status}";
        }

        if ($event === 'payment_rejected' && $rejectionReason) {
            $lines[] = "⚠️ <b>Reason:</b> " . e($rejectionReason);
        }

        if ($order->tenant) {
            $lines[] = "🏪 <b>Shop:</b> " . e($order->tenant->name);
        }

        $lines[] = "";
        $lines[] = "🕐 " . now()->format('M j, Y g:i A');

        $message = implode("\n", $lines);

        $contextEvent = match ($event) {
            'confirmed' => 'order.confirmed',
            'processing' => 'order.processing',
            'shipped' => 'order.shipped',
            'delivered' => 'order.delivered',
            'cancelled_by_admin' => 'order.cancelled',
            'cancelled_by_customer' => 'order.cancelled',
            'payment_verified' => 'payment.verified',
            'payment_rejected' => 'payment.rejected',
            'payment_proof_uploaded' => 'payment.proof_uploaded',
            default => 'order.updated',
        };

        Log::info('[TelegramOrderMessageBuilder] Payload generated', [
            'notification_type' => $contextEvent,
            'order_id' => $order->id,
            'length' => strlen($message),
        ]);

        return new TelegramPayload(
            message: $message,
            notificationType: $contextEvent,
            destination: 'order',
            context: [
                'notification_type' => $contextEvent,
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'event' => $event,
                'created_at' => now()->toIso8601String(),
            ],
        );
    }

    public function buildSample(Order $order): TelegramPayload
    {
        return $this->buildNewOrder($order);
    }
}
