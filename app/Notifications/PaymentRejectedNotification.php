<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentRejectedNotification extends Notification
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
            'title' => '❌ Payment Rejected',
            'message' => "Sorry, we could not verify your payment for Order #{$this->order->id}. Please contact customer support with your payment screenshot and order number.",
            'order_id' => $this->order->id,
            'order_number' => $this->order->id,
            'action_url' => route('client.orders.show', $this->order),
        ];
    }
}