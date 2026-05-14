<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentVerifiedNotification extends Notification
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
        return [
            'title' => '✅ Payment Verified',
            'message' => "OK, we have received your payment for Order #{$this->order->id}. Your order will now be processed.",
            'order_id' => $this->order->id,
            'order_number' => $this->order->id,
            'action_url' => route('client.orders.show', $this->order),
        ];
    }
}