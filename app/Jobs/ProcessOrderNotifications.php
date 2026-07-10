<?php

namespace App\Jobs;

use App\Auth\IdentityResolver;
use App\Models\Order;
use App\Models\Setting;
use App\Services\TelegramNotificationRouter;
use App\Services\TelegramOrderMessageBuilder;
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
            $notificationsEnabled = Setting::get('notifications_enabled', 'true', $this->order->tenant_id) === 'true';

            $admins = IdentityResolver::resolveTenantAdmins($this->order->tenant_id);

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
            Log::info('[ProcessOrderNotifications] Order notification requested', [
                'order_id' => $this->order->id,
            ]);

            if ($this->order->telegram_notified_at !== null) {
                Log::info('[ProcessOrderNotifications] Telegram notification skipped - already notified', [
                    'order_id' => $this->order->id,
                    'telegram_notified_at' => $this->order->telegram_notified_at,
                ]);
                return;
            }

            $resolver = app(TelegramRecipientResolver::class);
            $integrations = $resolver->resolve($this->order);

            if ($integrations->isEmpty()) {
                Log::info('[ProcessOrderNotifications] Telegram order notification skipped - no verified integrations', [
                    'order_id' => $this->order->id,
                ]);
                return;
            }

            $builder = app(TelegramOrderMessageBuilder::class);
            $payload = $builder->buildNewOrder($this->order);

            Log::info('[ProcessOrderNotifications] Payload generated', [
                'order_id' => $this->order->id,
                'notification_type' => $payload->notificationType,
                'destination' => $payload->destination,
            ]);

            $router = app(TelegramNotificationRouter::class);
            $totalDispatches = 0;

            foreach ($integrations as $integration) {
                $targets = $router->resolve($integration, 'order');

                Log::info('[ProcessOrderNotifications] Destination resolved', [
                    'order_id' => $this->order->id,
                    'integration_id' => $integration->id,
                    'target_count' => count($targets),
                    'targets' => $targets,
                ]);

                foreach ($targets as $target) {
                    SendTelegramMessageJob::dispatch(
                        $integration,
                        $payload->message,
                        $target['chat_id'],
                        $payload->toArray(),
                    )->onQueue('default');
                    $totalDispatches++;
                }
            }

            $this->order->timestamps = false;
            $this->order->updateQuietly(['telegram_notified_at' => now()]);

            Log::info('[ProcessOrderNotifications] Notification completed', [
                'order_id' => $this->order->id,
                'integration_count' => $integrations->count(),
                'dispatch_count' => $totalDispatches,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ProcessOrderNotifications] Telegram order notification dispatch failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
