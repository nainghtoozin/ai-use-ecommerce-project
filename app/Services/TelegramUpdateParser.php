<?php

namespace App\Services;

class TelegramUpdateParser
{
    public function parse(array $update): array
    {
        $updateType = $this->determineType($update);

        $result = [
            'update_id' => $update['update_id'] ?? null,
            'type' => $updateType,
            'raw' => $update,
        ];

        $parsed = match ($updateType) {
            'message' => $this->parseMessage($update),
            'callback_query' => $this->parseCallbackQuery($update),
            'edited_message' => $this->parseEditedMessage($update),
            'inline_query' => $this->parseInlineQuery($update),
            default => [],
        };

        return array_merge($result, $parsed);
    }

    public function determineType(array $update): string
    {
        if (isset($update['message'])) {
            return 'message';
        }

        if (isset($update['callback_query'])) {
            return 'callback_query';
        }

        if (isset($update['edited_message'])) {
            return 'edited_message';
        }

        if (isset($update['inline_query'])) {
            return 'inline_query';
        }

        if (isset($update['my_chat_member'])) {
            return 'my_chat_member';
        }

        if (isset($update['chat_member'])) {
            return 'chat_member';
        }

        if (isset($update['chosen_inline_result'])) {
            return 'chosen_inline_result';
        }

        if (isset($update['shipping_query'])) {
            return 'shipping_query';
        }

        if (isset($update['pre_checkout_query'])) {
            return 'pre_checkout_query';
        }

        if (isset($update['poll'])) {
            return 'poll';
        }

        if (isset($update['poll_answer'])) {
            return 'poll_answer';
        }

        return 'unknown';
    }

    public function parseMessage(array $update): array
    {
        $message = $update['message'] ?? [];

        $from = $message['from'] ?? [];
        $chat = $message['chat'] ?? [];

        $data = [
            'message_id' => $message['message_id'] ?? null,
            'chat_id' => $chat['id'] ?? null,
            'chat_type' => $chat['type'] ?? null,
            'chat_title' => $chat['title'] ?? null,
            'chat_username' => $chat['username'] ?? null,
            'user_id' => $from['id'] ?? null,
            'user_first_name' => $from['first_name'] ?? null,
            'user_last_name' => $from['last_name'] ?? null,
            'username' => $from['username'] ?? null,
            'language_code' => $from['language_code'] ?? null,
            'text' => $message['text'] ?? null,
            'date' => $message['date'] ?? null,
            'is_bot' => $from['is_bot'] ?? false,
            'entities' => $message['entities'] ?? [],
            'reply_to_message' => $message['reply_to_message'] ?? null,
        ];

        $data['is_command'] = $this->isCommand($message);
        $data['command'] = $data['is_command'] ? $this->extractCommand($message) : null;

        return $data;
    }

    public function parseCallbackQuery(array $update): array
    {
        $callback = $update['callback_query'] ?? [];
        $from = $callback['from'] ?? [];
        $message = $callback['message'] ?? [];
        $chat = $message['chat'] ?? [];

        return [
            'callback_query_id' => $callback['id'] ?? null,
            'callback_data' => $callback['data'] ?? null,
            'message_id' => $message['message_id'] ?? null,
            'chat_id' => $chat['id'] ?? null,
            'chat_type' => $chat['type'] ?? null,
            'chat_title' => $chat['title'] ?? null,
            'chat_username' => $chat['username'] ?? null,
            'user_id' => $from['id'] ?? null,
            'user_first_name' => $from['first_name'] ?? null,
            'username' => $from['username'] ?? null,
            'message_text' => $message['text'] ?? null,
            'date' => $callback['date'] ?? null,
        ];
    }

    public function parseEditedMessage(array $update): array
    {
        return $this->parseMessage(['message' => $update['edited_message']]);
    }

    public function parseInlineQuery(array $update): array
    {
        $inline = $update['inline_query'] ?? [];
        $from = $inline['from'] ?? [];

        return [
            'inline_query_id' => $inline['id'] ?? null,
            'query' => $inline['query'] ?? null,
            'offset' => $inline['offset'] ?? null,
            'user_id' => $from['id'] ?? null,
            'user_first_name' => $from['first_name'] ?? null,
            'username' => $from['username'] ?? null,
        ];
    }

    public function isCommand(array $message): bool
    {
        $text = $message['text'] ?? '';
        $entities = $message['entities'] ?? [];

        if (empty($text) || empty($entities)) {
            return false;
        }

        foreach ($entities as $entity) {
            if (($entity['type'] ?? '') === 'bot_command') {
                return true;
            }
        }

        return false;
    }

    public function extractCommand(array $message): ?string
    {
        $text = $message['text'] ?? '';

        if (empty($text)) {
            return null;
        }

        if ($text[0] !== '/') {
            return null;
        }

        $parts = explode(' ', $text, 2);

        return $parts[0];
    }

    public function extractCommandArgs(array $message): ?string
    {
        $text = $message['text'] ?? '';

        if (empty($text) || $text[0] !== '/') {
            return null;
        }

        $parts = explode(' ', $text, 2);

        return $parts[1] ?? null;
    }
}
