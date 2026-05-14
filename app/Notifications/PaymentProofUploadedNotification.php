<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentProofUploadedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Order $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => '💳 Payment Proof Uploaded',
            'message' => 'Customer '.($this->order->customer_name ?? $this->order->first_name.' '.$this->order->last_name).' uploaded payment proof for Order #'.$this->order->id.'. Review and verify the payment.',
            'order_id' => $this->order->id,
            'order_number' => $this->order->id,
            'action_url' => route('admin.orders.show', $this->order),
        ];
    }
}
