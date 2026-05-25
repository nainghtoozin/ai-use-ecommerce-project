<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\TelegramRecipientResolver;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Events\PaymentProofUploaded;
use App\Events\PaymentVerified;
use App\Events\PaymentRejected;
use App\Notifications\PaymentProofUploadedNotification;
use App\Services\ActivityLogger;
use App\Services\BroadcastService;
use App\Services\DashboardCacheService;
use App\Services\NotificationPreferenceService;
use App\Services\OrderNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProcessOrderNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public array $backoff = [5, 15, 30];

    public function __construct(
        public Order $order,
        public ?string $paymentScreenshotPath = null
    ) {}

    public function handle(
        OrderNotificationService $orderNotificationService,
        NotificationPreferenceService $preferenceService,
    ): void {
        try {
            $notificationsEnabled = Setting::get('notifications_enabled', 'true') === 'true';

            $admins = User::role('admin')->get();

            ActivityLogger::log(
                'Order #' . $this->order->id . ' placed',
                'order_created',
                $this->order,
                ['order_id' => $this->order->id, 'total_amount' => $this->order->total_amount]
            );

            BroadcastService::fire(new OrderPlaced($this->order), ['order_id' => $this->order->id]);

            if ($notificationsEnabled) {
                $orderNotificationService->notifyOrderPlaced($this->order);
            }

            if ($notificationsEnabled && $this->paymentScreenshotPath) {
                $adminsWhoWantProof = $preferenceService->filterUsersByPreference($admins, 'payment_proof_uploaded');
                if ($adminsWhoWantProof->isNotEmpty()) {
                    try {
                        Notification::send(
                            $adminsWhoWantProof,
                            new PaymentProofUploadedNotification($this->order)
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Failed to send payment proof DB notification', [
                            'order_id' => $this->order->id,
                            'message' => $e->getMessage(),
                        ]);
                    }

                    BroadcastService::fire(new PaymentProofUploaded($this->order), ['order_id' => $this->order->id]);
                }
            }

            $this->dispatchTelegramOrderPlaced();
        } catch (\Throwable $e) {
            Log::error('Order placed but notifications failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessOrderNotifications exhausted all attempts', [
            'order_id' => $this->order->id,
            'error' => $e->getMessage(),
        ]);
    }

    private function dispatchTelegramOrderPlaced(): void
    {
        try {
            $resolver = app(TelegramRecipientResolver::class);
            $integrations = $resolver->resolve($this->order);

            if ($integrations->isEmpty()) {
                Log::info('[ProcessOrderNotifications] Telegram order notification skipped - no verified integrations', [
                    'order_id' => $this->order->id,
                ]);

                return;
            }

            $this->order->loadMissing('items.product', 'paymentMethod');

            $lines = [];
            $lines[] = "🆕 New Order #{$this->order->id}";
            $lines[] = '';
            $lines[] = "👤 Customer: {$this->order->customer_name}";
            $lines[] = "📞 Phone: {$this->order->phone}";
            $lines[] = '💳 Payment: ' . ($this->order->paymentMethod?->name ?? 'N/A');
            $lines[] = '💰 Total: ' . number_format((float) $this->order->total_amount) . ' MMK';
            $lines[] = '';

            if ($this->order->items->isNotEmpty()) {
                $lines[] = '📦 Products:';

                foreach ($this->order->items as $item) {
                    $productName = $item->product?->name ?? "Product #{$item->product_id}";
                    $itemTotal = (float) $item->price * (int) $item->quantity;
                    $lines[] = '  - ' . $productName . ' x' . $item->quantity . ' - ' . number_format($itemTotal) . ' MMK';
                }

                $lines[] = '';
            }

            $lines[] = '🕐 ' . now()->format('M j, Y g:i A');

            $message = implode("\n", $lines);

            foreach ($integrations as $integration) {
                SendTelegramMessageJob::dispatch($integration, $message)->onQueue('default');
            }

            Log::info('[ProcessOrderNotifications] Telegram order notification dispatched', [
                'order_id' => $this->order->id,
                'integration_count' => $integrations->count(),
                'integration_ids' => $integrations->pluck('id')->toArray(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ProcessOrderNotifications] Telegram order notification dispatch failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
