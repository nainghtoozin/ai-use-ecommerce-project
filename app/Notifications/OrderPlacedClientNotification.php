<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderPlacedClientNotification extends Notification
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
            'title' => '✅ Order Confirmed',
            'message' => "Your order has been placed successfully.\nWe will process and ship it to your address soon.",
            'order_id' => $this->order->id,
            'order_number' => $orderNumber,
            'action_url' => route('client.orders.show', $this->order),
        ];
    }
}
