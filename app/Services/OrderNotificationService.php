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
    public function notifyOrderPlaced(Order $order): void
    {
        try {
            $admins = User::query()
                ->where('role', User::ROLE_ADMIN)
                ->get();

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new NewOrderAdminNotification($order));
            }

            $order->loadMissing('user');

            if ($order->user) {
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
