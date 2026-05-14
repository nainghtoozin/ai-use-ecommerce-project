<?php

namespace App\Services;

use App\Jobs\RetryBroadcast;
use Illuminate\Support\Facades\Log;

class BroadcastService
{
    /**
     * Attempt to broadcast an event immediately.
     * On failure, queue a retry job for graceful degradation.
     */
    public static function fire(mixed $event, array $context = []): void
    {
        try {
            event($event);
        } catch (\Throwable $e) {
            Log::warning('Broadcast unavailable, degraded gracefully', [
                'event' => get_class($event),
                'context' => $context,
                'error' => $e->getMessage(),
            ]);

            try {
                RetryBroadcast::dispatch($event)
                    ->onQueue('broadcasts')
                    ->delay(now()->addSeconds(5));
            } catch (\Throwable $jobException) {
                Log::error('Retry broadcast dispatch also failed', [
                    'event' => get_class($event),
                    'error' => $jobException->getMessage(),
                ]);
            }
        }
    }

    public static function broadcastFallback(string $channel, string $eventName, array $payload = []): void
    {
        Log::info('Broadcast fallback (no Pusher):', [
            'channel' => $channel,
            'event' => $eventName,
            'payload' => $payload,
        ]);
    }
}
