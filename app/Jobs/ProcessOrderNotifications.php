<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use App\Events\OrderPlaced;
use App\Events\PaymentProofUploaded;
use App\Notifications\PaymentProofUploadedNotification;
use App\Services\ActivityLogger;
use App\Services\BroadcastService;
use App\Services\NotificationPreferenceService;
use App\Services\OrderNotificationService;
use App\Services\TelegramService;
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
        TelegramService $telegramService,
        OrderNotificationService $orderNotificationService,
        NotificationPreferenceService $preferenceService,
    ): void {
        try {
            ActivityLogger::log(
                'Order #' . $this->order->id . ' placed',
                'order_created',
                $this->order,
                ['order_id' => $this->order->id, 'total_amount' => $this->order->total_amount]
            );

            $telegramService->sendOrderNotification($this->order);
            $orderNotificationService->notifyOrderPlaced($this->order);

            $admins = User::where('role', User::ROLE_ADMIN)->get();
            $adminsWhoWantNewOrder = $preferenceService->filterUsersByPreference($admins, 'new_order');
            if ($adminsWhoWantNewOrder->isNotEmpty()) {
                BroadcastService::fire(new OrderPlaced($this->order), ['order_id' => $this->order->id]);
            }

            if ($this->paymentScreenshotPath) {
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
}
