<?php

namespace App\Services;

use App\Models\TelegramIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookService
{
    const TELEGRAM_API = 'https://api.telegram.org/bot';

    public function setWebhook(TelegramIntegration $integration): array
    {
        $token = $integration->bot_token;

        if (empty($token)) {
            return ['success' => false, 'message' => 'Bot token is missing.'];
        }

        $webhookUrl = route('webhooks.telegram', $integration->id);

        Log::info('Preparing webhook registration', [
            'integration_id' => $integration->id,
            'bot_username' => $integration->bot_username,
            'webhook_url' => $webhookUrl,
        ]);

        $secretToken = $integration->webhook_secret;

        if (empty($secretToken)) {
            $secretToken = \Illuminate\Support\Str::random(64);
            $integration->webhook_secret = $secretToken;
            $integration->save();
        }

        $payload = [
            'url' => $webhookUrl,
            'secret_token' => $secretToken,
            'allowed_updates' => ['message', 'callback_query', 'edited_message', 'inline_query'],
            'drop_pending_updates' => true,
            'max_connections' => 40,
        ];

        try {
            $response = Http::timeout(15)
                ->post(self::TELEGRAM_API . $token . '/setWebhook', $payload);

            $body = $response->json();

            Log::info('Telegram webhook registration attempted', [
                'integration_id' => $integration->id,
                'status_code' => $response->status(),
                'ok' => $body['ok'] ?? false,
            ]);

            if ($response->successful() && ($body['ok'] ?? false)) {
                Log::info('Telegram webhook registered successfully', [
                    'integration_id' => $integration->id,
                    'url' => $webhookUrl,
                ]);

                return [
                    'success' => true,
                    'message' => 'Webhook registered successfully.',
                    'response' => $body,
                ];
            }

            $error = $body['description'] ?? 'Unknown error';

            Log::error('Telegram webhook registration failed', [
                'integration_id' => $integration->id,
                'error' => $error,
            ]);

            return ['success' => false, 'message' => $error, 'response' => $body];
        } catch (\Throwable $e) {
            Log::error('Telegram webhook registration connection error', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Failed to connect to Telegram API.'];
        }
    }

    public function removeWebhook(TelegramIntegration $integration): array
    {
        $token = $integration->bot_token;

        if (empty($token)) {
            return ['success' => false, 'message' => 'Bot token is missing.'];
        }

        try {
            $response = Http::timeout(15)
                ->post(self::TELEGRAM_API . $token . '/deleteWebhook', [
                    'drop_pending_updates' => true,
                ]);

            $body = $response->json();

            Log::info('Telegram webhook removal attempted', [
                'integration_id' => $integration->id,
                'status_code' => $response->status(),
                'ok' => $body['ok'] ?? false,
            ]);

            if ($response->successful() && ($body['ok'] ?? false)) {
                Log::info('Telegram webhook removed successfully', [
                    'integration_id' => $integration->id,
                ]);

                return ['success' => true, 'message' => 'Webhook removed successfully.'];
            }

            $error = $body['description'] ?? 'Unknown error';

            Log::error('Telegram webhook removal failed', [
                'integration_id' => $integration->id,
                'error' => $error,
            ]);

            return ['success' => false, 'message' => $error];
        } catch (\Throwable $e) {
            Log::error('Telegram webhook removal connection error', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Failed to connect to Telegram API.'];
        }
    }

    public function getWebhookInfo(TelegramIntegration $integration): array
    {
        $token = $integration->bot_token;

        if (empty($token)) {
            return ['success' => false, 'message' => 'Bot token is missing.'];
        }

        try {
            $response = Http::timeout(15)
                ->get(self::TELEGRAM_API . $token . '/getWebhookInfo');

            $body = $response->json();

            if ($response->successful() && ($body['ok'] ?? false)) {
                return [
                    'success' => true,
                    'data' => $body['result'] ?? [],
                ];
            }

            return ['success' => false, 'message' => $body['description'] ?? 'Unknown error'];
        } catch (\Throwable $e) {
            Log::error('Telegram webhook info retrieval failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Failed to connect to Telegram API.'];
        }
    }

    public function verifySignature(Request $request, TelegramIntegration $integration): bool
    {
        $expected = $integration->webhook_secret;

        if (empty($expected)) {
            Log::warning('Telegram webhook signature check failed - no webhook_secret stored', [
                'integration_id' => $integration->id,
                'bot_username' => $integration->bot_username,
            ]);

            return false;
        }

        $received = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (empty($received)) {
            Log::warning('Telegram webhook signature check failed - missing X-Telegram-Bot-Api-Secret-Token header', [
                'integration_id' => $integration->id,
                'bot_username' => $integration->bot_username,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return false;
        }

        if (!hash_equals($expected, $received)) {
            Log::warning('Telegram webhook signature check failed - token mismatch', [
                'integration_id' => $integration->id,
                'bot_username' => $integration->bot_username,
            ]);

            return false;
        }

        Log::info('Telegram webhook signature verified successfully', [
            'integration_id' => $integration->id,
            'bot_username' => $integration->bot_username,
        ]);

        return true;
    }
}
