<?php

namespace App\Listeners;

use App\Events\Payments\PaymentIntentCancelled;
use App\Events\Payments\PaymentIntentCreated;
use App\Events\Payments\PaymentIntentExpired;
use App\Events\Payments\PaymentIntentRejected;
use App\Services\Payment\Platform\PaymentTimelineService;
use Illuminate\Events\Dispatcher;

class PaymentTimelineEventSubscriber
{
    public function __construct(
        private readonly PaymentTimelineService $timeline,
    ) {}

    public function handleCreated(PaymentIntentCreated $event): void
    {
        $this->timeline->record(
            intent: $event->intent,
            type: 'created',
            description: 'Payment intent created',
        );
    }

    public function handleCancelled(PaymentIntentCancelled $event): void
    {
        $this->timeline->record(
            intent: $event->intent,
            type: 'cancelled',
            description: 'Payment cancelled',
        );
    }

    public function handleExpired(PaymentIntentExpired $event): void
    {
        $this->timeline->record(
            intent: $event->intent,
            type: 'expired',
            description: 'Payment expired',
        );
    }

    public function handleRejected(PaymentIntentRejected $event): void
    {
        $intent = $event->intent;
        $reason = $intent->metadata['rejection_reason'] ?? 'Payment rejected';

        $this->timeline->record(
            intent: $intent,
            type: 'rejected',
            description: $reason,
            metadata: ['rejection_reason' => $reason],
        );
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            PaymentIntentCreated::class => 'handleCreated',
            PaymentIntentCancelled::class => 'handleCancelled',
            PaymentIntentExpired::class => 'handleExpired',
            PaymentIntentRejected::class => 'handleRejected',
        ];
    }
}
