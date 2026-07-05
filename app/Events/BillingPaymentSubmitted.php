<?php

namespace App\Events;

use App\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BillingPaymentSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PaymentIntent $intent
    ) {}

    public function broadcastOn(): array
    {
        $superAdmins = User::role('superadmin')->pluck('id');

        return $superAdmins->map(fn($id) => new PrivateChannel('notifications.user.' . $id))->toArray();
    }

    public function broadcastAs(): string
    {
        return 'billing.payment_submitted';
    }

    public function broadcastWith(): array
    {
        $merchantName = $this->intent->tenant?->name ?? 'A merchant';
        $planName = $this->intent->plan?->name ?? 'a plan';

        return [
            'id' => $this->intent->id,
            'title' => '💰 New Payment Submitted',
            'message' => "{$merchantName} submitted a payment for {$planName}.",
            'action_url' => url('/superadmin/billing'),
        ];
    }
}
