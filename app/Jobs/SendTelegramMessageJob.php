<?php

namespace App\Jobs;

use App\Models\TelegramIntegration;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTelegramMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    public array $backoff = [5, 15, 30];

    public function __construct(
        public TelegramIntegration $telegramIntegration,
        public string $message,
    ) {}

    public function handle(TelegramService $telegramService): void
    {
        Log::info('SendTelegramMessageJob started', [
            'integration_id' => $this->telegramIntegration->id,
            'bot_username' => $this->telegramIntegration->bot_username,
            'attempt' => $this->attempts(),
        ]);

        $result = $telegramService->sendMessage(
            $this->telegramIntegration,
            $this->message,
        );

        if ($result['success']) {
            $this->telegramIntegration->last_verified_at = now();
            $this->telegramIntegration->save();

            Log::info('SendTelegramMessageJob completed successfully', [
                'integration_id' => $this->telegramIntegration->id,
                'status_code' => $result['status_code'] ?? null,
            ]);

            return;
        }

        Log::warning('SendTelegramMessageJob API call failed', [
            'integration_id' => $this->telegramIntegration->id,
            'attempt' => $this->attempts(),
            'error' => $result['message'],
        ]);

        throw new \RuntimeException($result['message']);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendTelegramMessageJob exhausted all attempts', [
            'integration_id' => $this->telegramIntegration->id,
            'bot_username' => $this->telegramIntegration->bot_username,
            'error' => $e->getMessage(),
        ]);
    }
}
