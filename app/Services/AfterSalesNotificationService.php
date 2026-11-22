<?php

namespace App\Services;

use App\Auth\IdentityResolver;
use App\Jobs\SendAfterSalesExternalNotificationJob;
use App\Models\Order;
use App\Notifications\AfterSalesStatusNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class AfterSalesNotificationService
{
    public function notifyCustomer(Order $order, string $title, string $message, ?string $url = null, array $meta = []): void
    {
        $customer = $order->user;

        if (!$customer) {
            return;
        }

        try {
            $customer->notify(new AfterSalesStatusNotification($order->tenant_id, $title, $message, $url, array_merge([
                'order_id' => $order->id,
            ], $meta)));
        } catch (\Throwable $e) {
            Log::warning('Failed to send after-sales customer notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->queueExternal('customer', $order, $title, $message, $url, $meta);
    }

    public function notifyMerchants(Order $order, string $title, string $message, ?string $url = null, array $meta = []): void
    {
        $admins = IdentityResolver::resolveTenantAdmins($order->tenant_id);

        if ($admins->isNotEmpty()) {
            try {
                Notification::send($admins, new AfterSalesStatusNotification(
                    $order->tenant_id,
                    $title,
                    $message,
                    $url,
                    array_merge(['order_id' => $order->id], $meta)
                ));
            } catch (\Throwable $e) {
                Log::warning('Failed to send after-sales merchant notification', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->queueExternal('merchant', $order, $title, $message, $url, $meta);
    }

    private function queueExternal(string $audience, Order $order, string $title, string $message, ?string $url, array $meta): void
    {
        try {
            SendAfterSalesExternalNotificationJob::dispatch(
                $order->id,
                (int) $order->tenant_id,
                $audience,
                $title,
                $message,
                $url,
                $meta
            )->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('Failed to queue after-sales external notification', [
                'order_id' => $order->id,
                'audience' => $audience,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
