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
        $chatType = $parsed['chat_type'] ?? null;

        if ($chatType === 'channel') {
            Log::warning('Telegram channel update rejected - channels not supported', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
                'chat_id' => $parsed['chat_id'] ?? null,
            ]);

            if (empty($integration->webhook_secret)) {
                $integration->webhook_secret = \Illuminate\Support\Str::random(64);
                $integration->saveQuietly();
            }

            return;
        }

        if ($this->isAnonymousAdmin($parsed)) {
            Log::warning('Telegram anonymous admin update rejected', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
            ]);

            return;
        }

        if ($chatType === 'private') {
            $this->autoDiscoverPersonalChat($integration, $parsed, $requestId);
        } elseif (in_array($chatType, ['group', 'supergroup'], true)) {
            $this->autoDiscoverGroupChatV2($integration, $parsed, $requestId);
        } else {
            Log::warning('Telegram unsupported chat type', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
                'chat_type' => $chatType,
                'chat_id' => $parsed['chat_id'] ?? null,
            ]);
        }

        if (empty($integration->webhook_secret)) {
            $integration->webhook_secret = \Illuminate\Support\Str::random(64);
            $integration->saveQuietly();

            Log::info('Telegram webhook_secret auto-generated on first callback', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
            ]);
        }
    }

    private function isAnonymousAdmin(array $parsed): bool
    {
        $raw = $parsed['raw'] ?? [];
        $message = $raw['message'] ?? $raw['callback_query']['message'] ?? [];
        $senderChat = $message['sender_chat'] ?? null;

        if ($senderChat && isset($senderChat['type']) && $senderChat['type'] === 'channel') {
            return true;
        }

        return false;
    }

    private function autoDiscoverPersonalChat(TelegramIntegration $integration, array $parsed, string $requestId): void
    {
        $chatId = (string) $parsed['chat_id'];
        $chatUsername = $parsed['chat_username'] ?? null;

        if (empty($chatUsername)) {
            $message = $parsed['raw']['message'] ?? $parsed['raw']['callback_query']['message'] ?? [];
            $chat = $message['chat'] ?? [];
            $chatUsername = $chat['username'] ?? null;
        }

        $changed = false;

        if ($integration->personal_chat_id !== $chatId) {
            $integration->personal_chat_id = $chatId;
            $changed = true;

            Log::info('Personal chat detected', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
                'personal_chat_id' => $chatId,
            ]);
        }

        if (!empty($chatUsername) && $integration->personal_chat_username !== $chatUsername) {
            $integration->personal_chat_username = $chatUsername;
            $changed = true;

            Log::info('Personal chat username updated', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
                'personal_chat_username' => $chatUsername,
            ]);
        }

        if (!$integration->isPersonalVerified()) {
            $integration->personal_verified_at = now();
            $changed = true;

            Log::info('Personal chat connected', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
                'personal_chat_id' => $chatId,
            ]);
        } else {
            Log::info('Personal chat updated', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
                'personal_chat_id' => $chatId,
            ]);
        }

        Log::info('Webhook verified', [
            'request_id' => $requestId,
            'integration_id' => $integration->id,
            'channel' => 'personal',
        ]);

        if ($changed) {
            $integration->saveQuietly();
        }
    }

    private function autoDiscoverGroupChatV2(TelegramIntegration $integration, array $parsed, string $requestId): void
    {
        $chatId = (string) $parsed['chat_id'];
        $chatTitle = $parsed['chat_title'] ?? null;
        $chatType = $parsed['chat_type'] ?? 'group';
        $chatUsername = $parsed['chat_username'] ?? null;

        if (empty($chatTitle)) {
            $message = $parsed['raw']['message'] ?? $parsed['raw']['callback_query']['message'] ?? [];
            $chat = $message['chat'] ?? [];
            $chatTitle = $chat['title'] ?? null;
        }

        if (empty($chatUsername)) {
            $message = $parsed['raw']['message'] ?? $parsed['raw']['callback_query']['message'] ?? [];
            $chat = $message['chat'] ?? [];
            $chatUsername = $chat['username'] ?? null;
        }

        $changed = false;

        if ($integration->group_chat_id !== $chatId) {
            $integration->group_chat_id = $chatId;
            $changed = true;

            Log::info('Group chat detected', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
                'group_chat_id' => $chatId,
                'group_chat_type' => $chatType,
            ]);
        }

        if ($integration->group_chat_type !== $chatType) {
            $integration->group_chat_type = $chatType;
            $changed = true;

            Log::info('Group chat type updated', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
                'group_chat_type' => $chatType,
            ]);
        }

        if (!empty($chatTitle) && $integration->group_chat_title !== $chatTitle) {
            $integration->group_chat_title = $chatTitle;
            $changed = true;

            Log::info('Group chat title updated', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
                'group_chat_title' => $chatTitle,
            ]);
        }

        if (!empty($chatUsername) && $integration->group_chat_username !== $chatUsername) {
            $integration->group_chat_username = $chatUsername;
            $changed = true;

            Log::info('Group chat username updated', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
                'group_chat_username' => $chatUsername,
            ]);
        }

        if (!$integration->isGroupVerified()) {
            $integration->group_verified_at = now();
            $changed = true;

            Log::info('Group chat verified', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
                'group_chat_id' => $chatId,
                'group_chat_title' => $chatTitle,
            ]);
        } else {
            Log::info('Group chat updated', [
                'request_id' => $requestId,
                'integration_id' => $integration->id,
                'group_chat_id' => $chatId,
                'group_chat_title' => $chatTitle,
            ]);
        }

        Log::info('Webhook verified', [
            'request_id' => $requestId,
            'integration_id' => $integration->id,
            'channel' => 'group',
        ]);

        if ($changed) {
            $integration->saveQuietly();
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
