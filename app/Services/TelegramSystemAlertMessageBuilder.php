<?php

namespace App\Services;

use App\Data\TelegramPayload;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TelegramSystemAlertMessageBuilder
{
    public function paymentSuccess(Order $order): TelegramPayload
    {
        $order->loadMissing('paymentMethod', 'tenant');

        $lines = [];
        $lines[] = "<b>✅ Payment Received</b>";
        $lines[] = "";
        $lines[] = "📋 <b>Order:</b> #{$order->id}";
        $lines[] = "👤 <b>Customer:</b> " . e($order->customer_name);
        $lines[] = "💰 <b>Amount:</b> " . number_format((float) ($order->paid_amount ?? $order->total_amount)) . ' ' . config('payments.default_currency');
        $lines[] = "💳 <b>Method:</b> " . ($order->paymentMethod?->name ?? 'N/A');

        if ($order->tenant) {
            $lines[] = "🏪 <b>Shop:</b> " . e($order->tenant->name);
        }

        $lines[] = "";
        $lines[] = "🕐 " . now()->format('M j, Y g:i A');

        return new TelegramPayload(
            message: implode("\n", $lines),
            notificationType: 'payment.success',
            destination: 'payment',
            context: [
                'notification_type' => 'payment.success',
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'created_at' => now()->toIso8601String(),
            ],
        );
    }

    public function paymentFailed(Order $order, ?string $reason = null): TelegramPayload
    {
        $order->loadMissing('paymentMethod', 'tenant');

        $lines = [];
        $lines[] = "<b>❌ Payment Failed</b>";
        $lines[] = "";
        $lines[] = "📋 <b>Order:</b> #{$order->id}";
        $lines[] = "👤 <b>Customer:</b> " . e($order->customer_name);
        $lines[] = "💰 <b>Amount:</b> " . number_format((float) $order->total_amount) . ' ' . config('payments.default_currency');
        $lines[] = "💳 <b>Method:</b> " . ($order->paymentMethod?->name ?? 'N/A');

        if ($reason) {
            $lines[] = "⚠️ <b>Reason:</b> " . e($reason);
        }

        if ($order->tenant) {
            $lines[] = "🏪 <b>Shop:</b> " . e($order->tenant->name);
        }

        $lines[] = "";
        $lines[] = "🕐 " . now()->format('M j, Y g:i A');

        return new TelegramPayload(
            message: implode("\n", $lines),
            notificationType: 'payment.failed',
            destination: 'payment',
            context: [
                'notification_type' => 'payment.failed',
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'created_at' => now()->toIso8601String(),
            ],
        );
    }

    public function lowStock(Product $product, int $currentStock): TelegramPayload
    {
        $product->loadMissing('tenant');

        $lines = [];
        $lines[] = "<b>⚠️ Low Stock Alert</b>";
        $lines[] = "";
        $lines[] = "📦 <b>Product:</b> " . e($product->name);
        $lines[] = "🏷️ <b>SKU:</b> " . ($product->sku ?? 'N/A');
        $lines[] = "📉 <b>Stock:</b> {$currentStock} remaining";

        if ($product->tenant) {
            $lines[] = "🏪 <b>Shop:</b> " . e($product->tenant->name);
        }

        $lines[] = "";
        $lines[] = "🕐 " . now()->format('M j, Y g:i A');

        return new TelegramPayload(
            message: implode("\n", $lines),
            notificationType: 'inventory.low_stock',
            destination: 'inventory',
            context: [
                'notification_type' => 'inventory.low_stock',
                'tenant_id' => $product->tenant_id,
                'inventory_id' => $product->id,
                'current_stock' => $currentStock,
                'created_at' => now()->toIso8601String(),
            ],
        );
    }

    public function outOfStock(Product $product): TelegramPayload
    {
        $product->loadMissing('tenant');

        $lines = [];
        $lines[] = "<b>🚫 Out of Stock</b>";
        $lines[] = "";
        $lines[] = "📦 <b>Product:</b> " . e($product->name);
        $lines[] = "🏷️ <b>SKU:</b> " . ($product->sku ?? 'N/A');

        if ($product->tenant) {
            $lines[] = "🏪 <b>Shop:</b> " . e($product->tenant->name);
        }

        $lines[] = "";
        $lines[] = "🕐 " . now()->format('M j, Y g:i A');

        return new TelegramPayload(
            message: implode("\n", $lines),
            notificationType: 'inventory.out_of_stock',
            destination: 'inventory',
            context: [
                'notification_type' => 'inventory.out_of_stock',
                'tenant_id' => $product->tenant_id,
                'inventory_id' => $product->id,
                'created_at' => now()->toIso8601String(),
            ],
        );
    }

    public function newCustomer(User $user): TelegramPayload
    {
        $lines = [];
        $lines[] = "<b>🎉 New Customer Registered</b>";
        $lines[] = "";
        $lines[] = "👤 <b>Name:</b> " . e($user->name ?? $user->first_name . ' ' . $user->last_name);
        $lines[] = "📧 <b>Email:</b> " . e($user->email);

        if ($user->phone) {
            $lines[] = "📞 <b>Phone:</b> " . e($user->phone);
        }

        $lines[] = "📅 <b>Joined:</b> " . ($user->created_at?->format('M j, Y g:i A') ?? 'N/A');

        $lines[] = "";
        $lines[] = "🕐 " . now()->format('M j, Y g:i A');

        return new TelegramPayload(
            message: implode("\n", $lines),
            notificationType: 'customer.new',
            destination: 'system',
            context: [
                'notification_type' => 'customer.new',
                'tenant_id' => $user->tenant_id,
                'customer_id' => $user->id,
                'created_at' => now()->toIso8601String(),
            ],
        );
    }

    public function dailySummary(array $data): TelegramPayload
    {
        $lines = [];
        $lines[] = "<b>📊 Daily Summary</b>";
        $lines[] = "";
        $lines[] = "📅 <b>Date:</b> " . now()->format('M j, Y');

        if (isset($data['total_orders'])) {
            $lines[] = "📋 <b>Orders:</b> {$data['total_orders']}";
        }

        if (isset($data['total_revenue'])) {
            $lines[] = "💰 <b>Revenue:</b> " . number_format((float) $data['total_revenue']) . ' ' . config('payments.default_currency');
        }

        if (isset($data['new_customers'])) {
            $lines[] = "👤 <b>New Customers:</b> {$data['new_customers']}";
        }

        if (isset($data['pending_orders'])) {
            $lines[] = "⏳ <b>Pending:</b> {$data['pending_orders']}";
        }

        if (isset($data['low_stock_items'])) {
            $lines[] = "⚠️ <b>Low Stock:</b> {$data['low_stock_items']}";
        }

        $lines[] = "";
        $lines[] = "🕐 " . now()->format('M j, Y g:i A');

        return new TelegramPayload(
            message: implode("\n", $lines),
            notificationType: 'system.daily_summary',
            destination: 'system',
            context: [
                'notification_type' => 'system.daily_summary',
                'tenant_id' => $data['tenant_id'] ?? null,
                'created_at' => now()->toIso8601String(),
            ],
        );
    }

    public function queueFailure(string $jobName, string $error): TelegramPayload
    {
        $lines = [];
        $lines[] = "<b>⚠️ Queue Job Failed</b>";
        $lines[] = "";
        $lines[] = "🔄 <b>Job:</b> " . e($jobName);
        $lines[] = "❌ <b>Error:</b> " . e($error);
        $lines[] = "";
        $lines[] = "🕐 " . now()->format('M j, Y g:i A');

        return new TelegramPayload(
            message: implode("\n", $lines),
            notificationType: 'system.queue_failure',
            destination: 'system',
            context: [
                'notification_type' => 'system.queue_failure',
                'job' => $jobName,
                'error' => $error,
                'created_at' => now()->toIso8601String(),
            ],
        );
    }

    public function securityAlert(string $alert, array $details = []): TelegramPayload
    {
        $lines = [];
        $lines[] = "<b>🔒 Security Alert</b>";
        $lines[] = "";
        $lines[] = "⚠️ <b>Alert:</b> " . e($alert);

        foreach ($details as $key => $value) {
            if (in_array($key, ['ip', 'location', 'device', 'browser', 'action', 'time'], true)) {
                $lines[] = "• <b>" . ucfirst($key) . ":</b> " . e((string) $value);
            }
        }

        $lines[] = "";
        $lines[] = "🕐 " . now()->format('M j, Y g:i A');

        return new TelegramPayload(
            message: implode("\n", $lines),
            notificationType: 'security.alert',
            destination: 'security',
            context: [
                'notification_type' => 'security.alert',
                'alert' => $alert,
                'created_at' => now()->toIso8601String(),
            ],
        );
    }

    public function manualAlert(string $message, string $sentBy): TelegramPayload
    {
        $lines = [];
        $lines[] = "<b>📢 Admin Notification</b>";
        $lines[] = "";
        $lines[] = e($message);
        $lines[] = "";
        $lines[] = "👤 <b>Sent by:</b> " . e($sentBy);
        $lines[] = "";
        $lines[] = "🕐 " . now()->format('M j, Y g:i A');

        return new TelegramPayload(
            message: implode("\n", $lines),
            notificationType: 'manual.admin',
            destination: 'manual',
            context: [
                'notification_type' => 'manual.admin',
                'sent_by' => $sentBy,
                'created_at' => now()->toIso8601String(),
            ],
        );
    }
}
