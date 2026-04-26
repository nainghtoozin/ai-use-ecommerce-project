<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOrderAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Order $order
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $orderNumber = $this->order->order_number ?? $this->order->id;

        return [
            'title' => '🛒 New Order Received',
            'message' => "Order #{$orderNumber} has been placed.\nPlease review the order details.",
            'order_id' => $this->order->id,
            'order_number' => $orderNumber,
            'action_url' => route('admin.orders.show', $this->order),
        ];
    }
}
