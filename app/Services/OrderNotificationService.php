<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Notifications\NewOrderAdminNotification;
use App\Notifications\OrderPlacedClientNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class OrderNotificationService
{
    public function __construct(
        private readonly NotificationPreferenceService $preferenceService
    ) {}

    public function notifyOrderPlaced(Order $order): void
    {
        try {
            $admins = User::role('admin')
                ->where('users.tenant_id', $order->tenant_id)
                ->get();

            $admins = $this->preferenceService->filterUsersByPreference($admins, 'new_order');

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new NewOrderAdminNotification($order));
            }

            $order->loadMissing('user');

            if ($order->user && $this->preferenceService->userWantsNotification($order->user, 'order_placed')) {
                $order->user->notify(new OrderPlacedClientNotification($order));
            }
        } catch (\Throwable $exception) {
            Log::warning('Database order notification failed.', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
