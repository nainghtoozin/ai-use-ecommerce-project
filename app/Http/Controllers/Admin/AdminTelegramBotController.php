<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminTelegramBotController extends Controller
{
    public function edit()
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        return Inertia::render('Admin/Settings/TelegramBotConnect', [
            'settings' => [
                'telegram_bot_token' => $settings['telegram_bot_token'] ?? '',
                'telegram_chat_id' => $settings['telegram_chat_id'] ?? '',
                'telegram_parse_mode' => $settings['telegram_parse_mode'] ?? 'HTML',
                'telegram_enabled' => $settings['telegram_enabled'] ?? 'true',
            ],
        ]);
    }

    public function update(Request $request)
    {
        $rules = [
            'telegram_bot_token' => 'nullable|string|max:255',
            'telegram_chat_id' => 'nullable|string|max:255',
            'telegram_parse_mode' => 'required|in:HTML,Markdown',
            'telegram_enabled' => 'required',
        ];

        $enabled = filter_var($request->input('telegram_enabled'), FILTER_VALIDATE_BOOLEAN);

        if ($enabled) {
            $rules['telegram_bot_token'] = 'required|string|max:255';
            $rules['telegram_chat_id'] = 'required|string|max:255';
        }

        $request->validate($rules);

        Setting::set('telegram_bot_token', $request->input('telegram_bot_token'));
        Setting::set('telegram_chat_id', $request->input('telegram_chat_id'));
        Setting::set('telegram_parse_mode', $request->input('telegram_parse_mode'));
        Setting::set('telegram_enabled', $enabled ? 'true' : 'false');

        ActivityLogger::log(
            'Telegram settings updated',
            'settings_updated',
            properties: [
                'telegram_parse_mode' => $request->input('telegram_parse_mode'),
                'telegram_enabled' => $enabled ? 'true' : 'false',
            ]
        );

        return redirect()->route('admin.settings.telegram')
            ->with('success', 'Telegram settings updated successfully.');
    }

    public function test(Request $request, TelegramService $telegramService)
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        $botToken = $settings['telegram_bot_token'] ?? '';
        $chatId = $settings['telegram_chat_id'] ?? '';
        $parseMode = $settings['telegram_parse_mode'] ?? 'HTML';

        if (blank($botToken) || blank($chatId)) {
            return redirect()->route('admin.settings.telegram')
                ->with('error', 'Please save a bot token and chat ID before sending a test message.');
        }

        $result = $telegramService->sendMessage(
            'Telegram bot connection successful.',
            $parseMode,
            $botToken,
            $chatId
        );

        ActivityLogger::log(
            'Telegram test message sent',
            'telegram_test',
            properties: [
                'success' => $result,
            ]
        );

        if ($result) {
            return redirect()->route('admin.settings.telegram')
                ->with('success', 'Test message sent successfully!');
        }

        return redirect()->route('admin.settings.telegram')
            ->with('error', 'Failed to send test message. Please check your bot token and chat ID.');
    }
}
