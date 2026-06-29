<?php

namespace App\Services;

use App\Models\TelegramIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    const TELEGRAM_API = 'https://api.telegram.org/bot';

    public function getMe(string $token): array
    {
        try {
            $response = Http::timeout(10)
                ->post(self::TELEGRAM_API . $token . '/getMe');

            $body = $response->json();

            if ($response->successful() && ($body['ok'] ?? false)) {
                return [
                    'success' => true,
                    'data' => $body['result'],
                ];
            }

            $error = $body['description'] ?? 'Invalid bot token';

            return ['success' => false, 'message' => $error];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return ['success' => false, 'message' => 'Could not connect to Telegram API. Please check your network.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'An unexpected error occurred while validating the bot token.'];
        }
    }

    public function validateBotToken(string $token): array
    {
        $result = $this->getMe($token);

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'message' => 'Bot token is valid.',
            'bot_name' => $result['data']['first_name'] ?? null,
            'bot_username' => $result['data']['username'] ?? null,
        ];
    }

    public function sendMessage(TelegramIntegration $integration, string $message, ?string $chatId = null): array
    {
        Log::info('Telegram API request started', [
            'integration_id' => $integration->id,
            'bot_username' => $integration->bot_username,
        ]);

        if (!$integration->isEnabled()) {
            Log::warning('Telegram API skipped - integration disabled', [
                'integration_id' => $integration->id,
            ]);

            return ['success' => false, 'message' => 'Telegram integration is disabled.'];
        }

        $token = $integration->bot_token;

        if (empty($token)) {
            Log::error('Telegram API failed - empty bot token', [
                'integration_id' => $integration->id,
            ]);

            return ['success' => false, 'message' => 'Bot token is missing.'];
        }

        $chatId = $chatId ?? $integration->getEffectiveChatId();

        if (empty($chatId)) {
            Log::error('Telegram API failed - empty chat ID', [
                'integration_id' => $integration->id,
            ]);

            return ['success' => false, 'message' => 'Chat ID is missing.'];
        }

        $parseMode = $integration->getParseMode();

        try {
            $response = Http::timeout(15)
                ->post(self::TELEGRAM_API . $token . '/sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => $parseMode,
                    'disable_web_page_preview' => true,
                ]);

            $statusCode = $response->status();
            $body = $response->json();

            Log::info('Telegram API response received', [
                'integration_id' => $integration->id,
                'status_code' => $statusCode,
                'ok' => $body['ok'] ?? false,
            ]);

            if ($response->successful() && ($body['ok'] ?? false)) {
                Log::info('Telegram API success', [
                    'integration_id' => $integration->id,
                ]);

                return [
                    'success' => true,
                    'message' => 'Message sent successfully.',
                    'status_code' => $statusCode,
                    'response' => $body,
                ];
            }

            $errorDescription = $body['description'] ?? 'Unknown Telegram API error';

            Log::error('Telegram API error', [
                'integration_id' => $integration->id,
                'status_code' => $statusCode,
                'error' => $errorDescription,
            ]);

            return [
                'success' => false,
                'message' => $errorDescription,
                'status_code' => $statusCode,
                'response' => $body,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Telegram API connection error', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Could not connect to Telegram. Please check your network and try again.'];
        } catch (\Throwable $e) {
            Log::error('Telegram API unexpected error', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'An unexpected error occurred while sending the message.'];
        }
    }
}
