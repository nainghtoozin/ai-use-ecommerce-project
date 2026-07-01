<?php

namespace App\Services\Payment\Platform;

use App\Models\PaymentIntent;
use App\Models\PaymentTimelineEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PaymentTimelineService
{
    public function record(
        PaymentIntent $intent,
        string $type,
        string $description = '',
        array $metadata = [],
        ?Carbon $occurredAt = null,
    ): PaymentTimelineEvent {
        return PaymentTimelineEvent::create([
            'payment_intent_id' => $intent->id,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    public function getForIntent(PaymentIntent $intent): Collection
    {
        return PaymentTimelineEvent::where('payment_intent_id', $intent->id)
            ->orderBy('occurred_at', 'asc')
            ->get();
    }

    public function getByType(PaymentIntent $intent, string $type): Collection
    {
        return PaymentTimelineEvent::where('payment_intent_id', $intent->id)
            ->where('type', $type)
            ->orderBy('occurred_at', 'asc')
            ->get();
    }
}
