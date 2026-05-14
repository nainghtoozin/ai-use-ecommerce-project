<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderShippedNotification extends Notification
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
            'title' => '🚚 Order Shipped',
            'message' => "Your order #{$orderNumber} is on the way.\nThank you for shopping with us.",
            'order_id' => $this->order->id,
            'order_number' => $orderNumber,
            'action_url' => route('client.orders.show', $this->order),
        ];
    }
}
