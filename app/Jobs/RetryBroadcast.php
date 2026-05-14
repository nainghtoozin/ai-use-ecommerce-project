<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    public array $backoff = [5, 15, 30];

    public function __construct(
        public mixed $event
    ) {}

    public function handle(): void
    {
        try {
            event($this->event);
            Log::info('Retry broadcast succeeded', [
                'event' => get_class($this->event),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Retry broadcast attempt failed', [
                'event' => get_class($this->event),
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Retry broadcast exhausted all attempts', [
            'event' => get_class($this->event),
            'error' => $e->getMessage(),
        ]);
    }
}
