<?php

namespace App\Http\Controllers;

use App\Models\TelegramIntegration;
use App\Services\TelegramUpdateParser;
use App\Services\TelegramWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        TelegramIntegration $telegramIntegration,
        TelegramWebhookService $webhookService,
        TelegramUpdateParser $parser,
    ) {
        $requestId = uniqid('tg_', true);

        Log::info('Telegram webhook request arrived', [
            'request_id' => $requestId,
            'integration_id' => $telegramIntegration?->id ?? 'unknown',
            'bot_username' => $telegramIntegration?->bot_username ?? 'unknown',
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => strlen($request->getContent()),
            'ip' => $request->ip(),
        ]);

        $signatureResult = $this->checkSignature($request, $telegramIntegration, $webhookService, $requestId);

        if ($signatureResult === 'valid') {
            Log::info('Telegram webhook signature verified', [
                'request_id' => $requestId,
                'integration_id' => $telegramIntegration->id,
            ]);
        } elseif ($signatureResult === 'missing_secret') {
            Log::info('Telegram webhook proceeding without secret token (first-time setup)', [
                'request_id' => $requestId,
                'integration_id' => $telegramIntegration->id,
            ]);
        } else {
            Log::warning('Telegram webhook signature invalid - processing anyway', [
                'request_id' => $requestId,
                'integration_id' => $telegramIntegration->id,
                'has_secret' => !empty($telegramIntegration->webhook_secret),
                'has_header' => !empty($request->header('X-Telegram-Bot-Api-Secret-Token')),
            ]);
        }

        $payload = $request->all();

        if (empty($payload)) {
            Log::warning('Telegram webhook empty payload', [
                'request_id' => $requestId,
                'integration_id' => $telegramIntegration->id,
            ]);

            return response('OK', 200);
        }

        try {
            $parsed = $parser->parse($payload);

            Log::info('Telegram webhook update parsed', [
                'request_id' => $requestId,
                'integration_id' => $telegramIntegration->id,
                'update_id' => $parsed['update_id'],
                'type' => $parsed['type'],
                'chat_id' => $parsed['chat_id'] ?? null,
                'user_id' => $parsed['user_id'] ?? null,
            ]);

            if ($parsed['chat_id'] !== null) {
                $this->autoDiscoverChat($telegramIntegration, $parsed, $requestId);
            }

            $this->handleUpdate($telegramIntegration, $parsed, $requestId);
        } catch (\Throwable $e) {
            Log::error('Telegram webhook processing failed', [
                'request_id' => $requestId,
                'integration_id' => $telegramIntegration->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response('OK', 200);
    }

    private function checkSignature(
        Request $request,
        TelegramIntegration $integration,
        TelegramWebhookService $webhookService,
        string $requestId,
    ): string {
        $hasSecret = !empty($integration->webhook_secret);

        Log::info('Telegram webhook signature check', [
            'request_id' => $requestId,
            'integration_id' => $integration->id,
            'has_webhook_secret' => $hasSecret,
        ]);

        if (!$hasSecret) {
            return 'missing_secret';
        }

        if ($webhookService->verifySignature($request, $integration)) {
            Log::info('Telegram webhook signature valid', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
            ]);

            return 'valid';
        }

        Log::warning('Telegram webhook signature invalid - secret exists but header missing or mismatch', [
            'request_id' => $requestId,
            'integration_id' => $integration->id,
            'has_header' => !empty($request->header('X-Telegram-Bot-Api-Secret-Token')),
            'header_length' => strlen($request->header('X-Telegram-Bot-Api-Secret-Token') ?? ''),
        ]);

        return 'reject';
    }

    private function autoDiscoverChat(TelegramIntegration $integration, array $parsed, string $requestId): void
    {
        $needsSave = false;

        if (empty($integration->chat_id) || $integration->chat_id != $parsed['chat_id']) {
            $integration->chat_id = (string) $parsed['chat_id'];
            $needsSave = true;
        }

        if (isset($parsed['chat_type']) && !empty($parsed['chat_type'])) {
            $integration->chat_type = $parsed['chat_type'];
            $needsSave = true;
        }

        $groupTitle = $parsed['chat_title'] ?? null;
        if (empty($groupTitle) && !empty($integration->chat_type) && $integration->chat_type !== 'private') {
            $message = $parsed['raw']['message'] ?? $parsed['raw']['callback_query']['message'] ?? [];
            $chat = $message['chat'] ?? [];
            $groupTitle = $chat['title'] ?? null;
        }
        if (!empty($groupTitle) && $integration->group_title !== $groupTitle) {
            $integration->group_title = $groupTitle;
            $needsSave = true;
        }

        $chatUsername = $parsed['chat_username'] ?? null;
        if (empty($chatUsername)) {
            $message = $parsed['raw']['message'] ?? $parsed['raw']['callback_query']['message'] ?? [];
            $chat = $message['chat'] ?? [];
            $chatUsername = $chat['username'] ?? null;
        }
        if (!empty($chatUsername) && $integration->chat_username !== $chatUsername) {
            $integration->chat_username = $chatUsername;
            $needsSave = true;
        }

        if ($integration->verification_status !== 'verified') {
            $integration->verification_status = 'verified';
            $integration->last_verified_at = now();
            $needsSave = true;
        }

        if (empty($integration->webhook_secret)) {
            $integration->webhook_secret = \Illuminate\Support\Str::random(64);
            $needsSave = true;

            Log::info('Telegram webhook_secret auto-generated on first callback', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
            ]);
        }

        if ($needsSave) {
            $integration->saveQuietly();

            Log::info('Telegram integration auto-updated via webhook', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
                'chat_id' => $integration->chat_id,
                'chat_type' => $integration->chat_type,
                'group_title' => $integration->group_title,
                'verification_status' => $integration->verification_status,
            ]);
        }
    }

    private function handleUpdate(TelegramIntegration $integration, array $parsed, string $requestId): void
    {
        match ($parsed['type']) {
            'message' => $this->handleMessage($integration, $parsed, $requestId),
            'callback_query' => $this->handleCallbackQuery($integration, $parsed, $requestId),
            'edited_message' => $this->handleEditedMessage($integration, $parsed, $requestId),
            'inline_query' => $this->handleInlineQuery($integration, $parsed, $requestId),
            'my_chat_member' => $this->handleChatMember($integration, $parsed, $requestId),
            default => $this->handleUnknown($integration, $parsed, $requestId),
        };
    }

    private function handleMessage(TelegramIntegration $integration, array $parsed, string $requestId): void
    {
        if ($parsed['is_command']) {
            $this->handleCommand($integration, $parsed, $requestId);
        } else {
            Log::info('Telegram message received', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
                'chat_id' => $parsed['chat_id'],
                'user_id' => $parsed['user_id'],
                'text_length' => strlen($parsed['text'] ?? ''),
            ]);
        }
    }

    private function handleCommand(TelegramIntegration $integration, array $parsed, string $requestId): void
    {
        $command = $parsed['command'] ?? '';

        Log::info('Telegram bot command received', [
            'request_id' => $requestId,
            'integration_id' => $integration->id,
            'command' => $command,
            'from_user_id' => $parsed['user_id'],
            'chat_id' => $parsed['chat_id'],
        ]);
    }

    private function handleCallbackQuery(TelegramIntegration $integration, array $parsed, string $requestId): void
    {
        Log::info('Telegram callback query received', [
            'request_id' => $requestId,
            'integration_id' => $integration->id,
            'callback_data' => $parsed['callback_data'],
            'from_user_id' => $parsed['user_id'],
        ]);
    }

    private function handleEditedMessage(TelegramIntegration $integration, array $parsed, string $requestId): void
    {
        Log::info('Telegram edited message received', [
            'request_id' => $requestId,
            'integration_id' => $integration->id,
            'message_id' => $parsed['message_id'],
        ]);
    }

    private function handleInlineQuery(TelegramIntegration $integration, array $parsed, string $requestId): void
    {
        Log::info('Telegram inline query received', [
            'request_id' => $requestId,
            'integration_id' => $integration->id,
            'query' => $parsed['query'],
            'from_user_id' => $parsed['user_id'],
        ]);
    }

    private function handleChatMember(TelegramIntegration $integration, array $parsed, string $requestId): void
    {
        Log::info('Telegram chat member update received', [
            'request_id' => $requestId,
            'integration_id' => $integration->id,
            'chat_id' => $parsed['chat_id'],
        ]);
    }

    private function handleUnknown(TelegramIntegration $integration, array $parsed, string $requestId): void
    {
        Log::info('Telegram unknown update type', [
            'request_id' => $requestId,
            'integration_id' => $integration->id,
            'update_id' => $parsed['update_id'],
            'type' => $parsed['type'],
        ]);
    }
}
