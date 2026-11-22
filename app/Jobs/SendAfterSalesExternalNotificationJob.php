<?php

namespace App\Jobs;

use App\Data\TelegramPayload;
use App\Events\AfterSalesStatusUpdated;
use App\Mail\AfterSales\AfterSalesStatusMail;
use App\Models\Order;
use App\Services\BroadcastService;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAfterSalesExternalNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $orderId,
        public readonly int $tenantId,
        public readonly string $audience,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $url = null,
        public readonly array $meta = [],
    ) {}

    public function handle(NotificationPreferenceService $preferences): void
    {
        $order = Order::withoutTenantScope()->with('tenant')->find($this->orderId);

        if (!$order) {
            return;
        }

        if ($this->audience === 'customer') {
            $this->sendCustomerMail($order, $preferences);
        } else {
            $this->sendMerchantTelegram();
        }

        $this->broadcastUpdate($order);
    }

    private function sendCustomerMail(Order $order, NotificationPreferenceService $preferences): void
    {
        $email = $order->email;
        $customer = $order->user;

        if (!$email || !$customer) {
            return;
        }

        if (!$preferences->userWantsNotification($customer, 'order_status_changed')) {
            return;
        }

        try {
            Mail::to($email)->send(AfterSalesStatusMail::forOrder($order, $this->title, $this->message, $this->url));
        } catch (\Throwable $e) {
            Log::warning('[AfterSales] Customer email failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendMerchantTelegram(): void
    {
        try {
            SendTelegramMessageJob::dispatchForTenant(
                $this->tenantId,
                new TelegramPayload(
                    message: '<b>' . e($this->title) . '</b>' . PHP_EOL . e($this->message),
                    notificationType: 'after_sales',
                    context: ['order_id' => $this->orderId] + $this->meta,
                )
            );
        } catch (\Throwable $e) {
            Log::warning('[AfterSales] Telegram dispatch failed', [
                'order_id' => $this->orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function broadcastUpdate(Order $order): void
    {
        try {
            BroadcastService::fire(new AfterSalesStatusUpdated(
                $order,
                $this->title,
                $this->message,
                (string) ($this->meta['category'] ?? 'after_sales'),
                $this->meta['reference'] ?? null,
            ), ['order_id' => $order->id, 'audience' => $this->audience]);
        } catch (\Throwable $e) {
            Log::warning('[AfterSales] Broadcast failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[AfterSales] External notification job failed', [
            'order_id' => $this->orderId,
            'audience' => $this->audience,
            'title' => $this->title,
            'error' => $e->getMessage(),
        ]);
    }
}
