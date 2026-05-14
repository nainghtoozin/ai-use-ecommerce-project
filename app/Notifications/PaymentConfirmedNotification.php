<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification
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
            'title' => '💳 Payment Confirmed',
            'message' => "We have received your payment for Order #{$orderNumber}.\nWe will process your order shortly.",
            'order_id' => $this->order->id,
            'order_number' => $orderNumber,
            'action_url' => route('client.orders.show', $this->order),
        ];
    }
}
