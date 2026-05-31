<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\TelegramRecipientResolver;
use App\Events\OrderStatusChanged;
use App\Events\PaymentVerified;
use App\Events\PaymentRejected;
use App\Events\PaymentProofUploaded;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\PaymentVerifiedNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\OrderShippedNotification;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\PaymentProofUploadedNotification;
use App\Services\ActivityLogger;
use App\Services\BroadcastService;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProcessOrderStatusChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;
    public int $tries = 3;
    public array $backoff = [5, 15, 30];

    public function __construct(
        public Order $order,
        public string $event,
        public ?string $oldStatus = null,
        public ?string $rejectionReason = null,
    ) {}

    public function handle(NotificationPreferenceService $preferenceService): void
    {
        $this->order->loadMissing('user');

        $this->logActivity();

        $notificationsEnabled = Setting::get('notifications_enabled', 'true') === 'true';

        if ($notificationsEnabled) {
            $userNotif = $this->getUserNotification();
            if ($userNotif && $this->order->user && $preferenceService->userWantsNotification($this->order->user, $userNotif['pref_key'])) {
                try {
                    $this->order->user->notify(new $userNotif['class']($this->order));
                } catch (\Throwable $e) {
                    Log::warning("Failed to send {$this->event} notification to user", [
                        'order_id' => $this->order->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            $adminNotif = $this->getAdminNotification();
            if ($adminNotif) {
                $admins = User::role('admin')
                    ->where('users.tenant_id', $this->order->tenant_id)
                    ->get();
                $adminsWhoWant = $preferenceService->filterUsersByPreference($admins, $adminNotif['pref_key']);
                if ($adminsWhoWant->isNotEmpty()) {
                    try {
                        Notification::send($adminsWhoWant, new $adminNotif['class']($this->order));
                    } catch (\Throwable $e) {
                        Log::warning("Failed to send {$this->event} admin notification", [
                            'order_id' => $this->order->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $broadcast = $this->getBroadcastEvent();
            if ($broadcast) {
                BroadcastService::fire(new $broadcast['class']($this->order), ['order_id' => $this->order->id]);
            }
        }

        $this->dispatchTelegramStatusChange();
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessOrderStatusChange exhausted all attempts', [
            'order_id' => $this->order->id,
            'event' => $this->event,
            'error' => $e->getMessage(),
        ]);
    }

    private function dispatchTelegramStatusChange(): void
    {
        try {
            $resolver = app(TelegramRecipientResolver::class);
            $integrations = $resolver->resolve($this->order);

            if ($integrations->isEmpty()) {
                Log::info('[ProcessOrderStatusChange] Telegram status notification skipped - no verified integrations', [
                    'order_id' => $this->order->id,
                    'event' => $this->event,
                ]);

                return;
            }

            $message = $this->buildStatusMessage();

            foreach ($integrations as $integration) {
                SendTelegramMessageJob::dispatch($integration, $message)->onQueue('default');
            }

            Log::info('[ProcessOrderStatusChange] Telegram status notification dispatched', [
                'order_id' => $this->order->id,
                'event' => $this->event,
                'integration_count' => $integrations->count(),
                'integration_ids' => $integrations->pluck('id')->toArray(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ProcessOrderStatusChange] Telegram status notification dispatch failed', [
                'order_id' => $this->order->id,
                'event' => $this->event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildStatusMessage(): string
    {
        $this->order->loadMissing('paymentMethod');

        $emoji = match ($this->event) {
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

        $label = match ($this->event) {
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
        $lines[] = "{$emoji} {$label} #{$this->order->id}";
        $lines[] = '';
        $lines[] = "👤 Customer: {$this->order->customer_name}";
        $lines[] = "📞 Phone: {$this->order->phone}";
        $lines[] = '💰 Total: ' . number_format((float) $this->order->total_amount) . ' MMK';
        $lines[] = '💳 Payment: ' . ($this->order->paymentMethod?->name ?? 'N/A');

        if ($this->oldStatus) {
            $lines[] = '';
            $lines[] = "📋 Status: {$this->oldStatus} -> {$this->order->order_status}";
        } else {
            $lines[] = "📋 Status: {$this->order->order_status}";
        }

        if ($this->event === 'payment_rejected' && $this->rejectionReason) {
            $lines[] = "⚠️ Reason: {$this->rejectionReason}";
        }

        $lines[] = '';
        $lines[] = '🕐 ' . now()->format('M j, Y g:i A');

        return implode("\n", $lines);
    }

    private function logActivity(): void
    {
        $config = $this->getActivityConfig();
        ActivityLogger::log($config['description'], $config['event'], $this->order, $config['properties']);
    }

    private function getActivityConfig(): array
    {
        return match ($this->event) {
            'confirmed' => [
                'description' => "Order #{$this->order->id} confirmed by admin",
                'event' => 'order_status_changed',
                'properties' => ['old_status' => $this->oldStatus ?? 'pending', 'new_status' => 'confirmed'],
            ],
            'processing' => [
                'description' => "Order #{$this->order->id} moved to processing",
                'event' => 'order_status_changed',
                'properties' => ['new_status' => 'processing'],
            ],
            'shipped' => [
                'description' => "Order #{$this->order->id} shipped by admin",
                'event' => 'order_status_changed',
                'properties' => ['new_status' => 'shipped'],
            ],
            'delivered' => [
                'description' => "Order #{$this->order->id} delivered by admin",
                'event' => 'order_status_changed',
                'properties' => ['new_status' => 'delivered'],
            ],
            'cancelled_by_admin' => [
                'description' => "Order #{$this->order->id} cancelled by admin",
                'event' => 'order_cancelled',
                'properties' => ['new_status' => 'cancelled'],
            ],
            'cancelled_by_customer' => [
                'description' => "Order #{$this->order->id} cancelled by customer",
                'event' => 'order_cancelled',
                'properties' => ['order_id' => $this->order->id, 'old_status' => $this->oldStatus],
            ],
            'payment_verified' => [
                'description' => "Payment verified for Order #{$this->order->id}",
                'event' => 'payment_verified',
                'properties' => ['order_id' => $this->order->id],
            ],
            'payment_rejected' => [
                'description' => "Payment rejected for Order #{$this->order->id}",
                'event' => 'payment_rejected',
                'properties' => ['order_id' => $this->order->id, 'reason' => $this->rejectionReason],
            ],
            'payment_proof_uploaded' => [
                'description' => "Payment proof uploaded for Order #{$this->order->id}",
                'event' => 'payment_proof_uploaded',
                'properties' => ['order_id' => $this->order->id],
            ],
            default => [
                'description' => "Order #{$this->order->id} status changed",
                'event' => 'order_status_changed',
                'properties' => [],
            ],
        };
    }

    private function getUserNotification(): ?array
    {
        return match ($this->event) {
            'confirmed' => ['class' => PaymentConfirmedNotification::class, 'pref_key' => 'order_status_changed'],
            'processing' => ['class' => PaymentConfirmedNotification::class, 'pref_key' => 'order_status_changed'],
            'shipped' => ['class' => OrderShippedNotification::class, 'pref_key' => 'order_status_changed'],
            'delivered' => ['class' => OrderDeliveredNotification::class, 'pref_key' => 'order_status_changed'],
            'cancelled_by_admin' => ['class' => OrderCancelledNotification::class, 'pref_key' => 'order_status_changed'],
            'payment_verified' => ['class' => PaymentVerifiedNotification::class, 'pref_key' => 'payment_verified'],
            'payment_rejected' => ['class' => PaymentRejectedNotification::class, 'pref_key' => 'payment_rejected'],
            default => null,
        };
    }

    private function getAdminNotification(): ?array
    {
        return match ($this->event) {
            'cancelled_by_customer' => ['class' => OrderCancelledNotification::class, 'pref_key' => 'order_cancelled'],
            'payment_proof_uploaded' => ['class' => PaymentProofUploadedNotification::class, 'pref_key' => 'payment_proof_uploaded'],
            default => null,
        };
    }

    private function getBroadcastEvent(): ?array
    {
        return match ($this->event) {
            'payment_verified' => ['class' => PaymentVerified::class],
            'payment_rejected' => ['class' => PaymentRejected::class],
            'cancelled_by_customer' => ['class' => OrderStatusChanged::class],
            'payment_proof_uploaded' => ['class' => PaymentProofUploaded::class],
            default => null,
        };
    }
}
