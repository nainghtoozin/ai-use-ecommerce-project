<?php

namespace App\Jobs;

use App\Data\TelegramPayload;
use App\Models\Setting;
use App\Models\TelegramIntegration;
use App\Services\TelegramNotificationRouter;
use App\Services\TelegramRecipientResolver;
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
        public ?string $chatId = null,
        public ?array $payload = null,
    ) {}

    public static function dispatchForTenant(int $tenantId, TelegramPayload $payload): int
    {
        try {
            if (Setting::get('notifications_enabled', 'true', $tenantId) !== 'true') {
                return 0;
            }

            $integrations = app(TelegramRecipientResolver::class)->resolve(null, $tenantId);

            if ($integrations->isEmpty()) {
                return 0;
            }

            $router = app(TelegramNotificationRouter::class);
            $dispatched = 0;

            foreach ($integrations as $integration) {
                foreach ($router->resolve($integration, $payload->destination ?? 'payment') as $target) {
                    static::dispatch(
                        $integration,
                        $payload->message,
                        $target['chat_id'],
                        $payload->toArray(),
                    )->onQueue('default');
                    $dispatched++;
                }
            }

            return $dispatched;
        } catch (\Throwable $e) {
            Log::warning('Telegram tenant dispatch failed', [
                'tenant_id' => $tenantId,
                'notification_type' => $payload->notificationType,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public function handle(TelegramService $telegramService): void
    {
        $notificationType = $this->payload['notification_type'] ?? 'unknown';
        $context = $this->payload['context'] ?? [];

        Log::info('SendTelegramMessageJob started', [
            'integration_id' => $this->telegramIntegration->id,
            'bot_username' => $this->telegramIntegration->bot_username,
            'notification_type' => $notificationType,
            'attempt' => $this->attempts(),
        ]);

        $result = $telegramService->sendMessage(
            $this->telegramIntegration,
            $this->message,
            $this->chatId,
        );

        if ($result['success']) {
            $this->telegramIntegration->last_verified_at = now();
            $this->telegramIntegration->save();

            Log::info('SendTelegramMessageJob completed successfully', [
                'integration_id' => $this->telegramIntegration->id,
                'notification_type' => $notificationType,
                'status_code' => $result['status_code'] ?? null,
            ]);

            return;
        }

        Log::warning('SendTelegramMessageJob API call failed', [
            'integration_id' => $this->telegramIntegration->id,
            'notification_type' => $notificationType,
            'attempt' => $this->attempts(),
            'error' => $result['message'],
        ]);

        throw new \RuntimeException($result['message']);
    }

    public function failed(\Throwable $e): void
    {
        $notificationType = $this->payload['notification_type'] ?? 'unknown';

        Log::error('SendTelegramMessageJob exhausted all attempts', [
            'integration_id' => $this->telegramIntegration->id,
            'bot_username' => $this->telegramIntegration->bot_username,
            'notification_type' => $notificationType,
            'error' => $e->getMessage(),
        ]);
    }
}
