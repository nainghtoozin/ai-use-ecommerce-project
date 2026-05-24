<?php

namespace App\Http\Controllers;

use App\Http\Requests\TelegramIntegrationRequest;
use App\Jobs\SendTelegramMessageJob;
use App\Models\TelegramIntegration;
use App\Services\TelegramService;
use App\Services\TelegramWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TelegramIntegrationController extends Controller
{
    public function edit()
    {
        $integration = TelegramIntegration::where('user_id', auth()->id())->first();

        $statusData = null;
        if ($integration) {
            $statusData = [
                'integration' => [
                    'id' => $integration->id,
                    'bot_name' => $integration->bot_name,
                    'bot_username' => $integration->bot_username,
                    'verification_status' => $integration->verification_status,
                    'verification_status_label' => $integration->getVerificationStatusLabel(),
                    'chat_type' => $integration->chat_type,
                    'chat_type_label' => $integration->getChatTypeLabel(),
                    'group_title' => $integration->group_title,
                    'chat_username' => $integration->chat_username,
                    'chat_id' => $integration->chat_id,
                    'is_enabled' => $integration->is_enabled,
                    'last_verified_at' => $integration->last_verified_at?->toIso8601String(),
                    'created_at' => $integration->created_at?->toIso8601String(),
                ],
            ];
        }

        return Inertia::render('Admin/Settings/TelegramIntegration', [
            'integration' => $integration,
            'integrationStatus' => $statusData,
        ]);
    }

    public function show(): JsonResponse
    {
        $integration = TelegramIntegration::where('user_id', auth()->id())->first();

        return response()->json([
            'success' => true,
            'data' => $integration,
        ]);
    }

    public function store(TelegramIntegrationRequest $request)
    {
        $data = $request->validated();

        $integration = TelegramIntegration::updateOrCreate(
            ['user_id' => auth()->id()],
            $data
        );

        $wasRecentlyCreated = $integration->wasRecentlyCreated;

        Log::info($wasRecentlyCreated
            ? 'Telegram integration created'
            : 'Telegram integration updated', [
            'user_id' => auth()->id(),
            'integration_id' => $integration->id,
        ]);

        if ($request->inertia()) {
            return redirect()->back()->with('success', 'Telegram settings saved successfully.');
        }

        return response()->json([
            'success' => true,
            'message' => $wasRecentlyCreated
                ? 'Telegram integration created successfully.'
                : 'Telegram integration updated successfully.',
            'data' => $integration,
        ]);
    }

    public function connect(Request $request, TelegramService $telegramService, TelegramWebhookService $webhookService)
    {
        $request->validate([
            'bot_token' => ['required', 'string'],
            'bot_name' => ['nullable', 'string', 'max:255'],
            'bot_username' => ['nullable', 'string', 'max:255'],
        ]);

        $token = $request->input('bot_token');

        $validation = $telegramService->validateBotToken($token);

        if (!$validation['success']) {
            Log::warning('Telegram connect failed - invalid bot token', [
                'user_id' => auth()->id(),
                'error' => $validation['message'],
            ]);

            return response()->json([
                'success' => false,
                'message' => $validation['message'],
            ], 422);
        }

        $botName = $request->input('bot_name', $validation['bot_name'] ?? null);
        $botUsername = $request->input('bot_username', $validation['bot_username'] ?? null);

        $integration = TelegramIntegration::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'bot_name' => $botName,
                'bot_username' => $botUsername,
                'bot_token' => $token,
                'verification_status' => 'pending_verification',
                'is_enabled' => true,
            ]
        );

        $webhookResult = $webhookService->setWebhook($integration);

        if (!$webhookResult['success']) {
            Log::error('Telegram connect failed - webhook registration error', [
                'user_id' => auth()->id(),
                'integration_id' => $integration->id,
                'error' => $webhookResult['message'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bot token is valid but webhook registration failed: ' . $webhookResult['message'],
            ], 500);
        }

        Log::info('Telegram integration connected with webhook', [
            'user_id' => auth()->id(),
            'integration_id' => $integration->id,
            'bot_username' => $botUsername,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Telegram bot connected successfully. Send /start to your bot to complete verification.',
            'data' => [
                'integration' => $integration,
                'webhook_url' => route('webhooks.telegram', $integration->id),
            ],
        ]);
    }

    public function status(): JsonResponse
    {
        $integration = TelegramIntegration::where('user_id', auth()->id())->first();

        if (!$integration) {
            return response()->json([
                'success' => true,
                'connected' => false,
                'integration' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'connected' => true,
            'integration' => [
                'id' => $integration->id,
                'bot_name' => $integration->bot_name,
                'bot_username' => $integration->bot_username,
                'verification_status' => $integration->verification_status,
                'verification_status_label' => $integration->getVerificationStatusLabel(),
                'chat_type' => $integration->chat_type,
                'chat_type_label' => $integration->getChatTypeLabel(),
                'group_title' => $integration->group_title,
                'chat_username' => $integration->chat_username,
                'chat_id' => $integration->chat_id,
                'is_enabled' => $integration->is_enabled,
                'last_verified_at' => $integration->last_verified_at?->toIso8601String(),
                'created_at' => $integration->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $request->validate(['is_enabled' => ['required', 'boolean']]);

        $integration = TelegramIntegration::where('user_id', auth()->id())->first();

        if (!$integration) {
            return response()->json([
                'success' => false,
                'message' => 'No Telegram integration found.',
            ], 404);
        }

        $integration->is_enabled = $request->boolean('is_enabled');
        $integration->save();

        $status = $integration->is_enabled ? 'enabled' : 'disabled';

        Log::info("Telegram integration {$status}", [
            'user_id' => auth()->id(),
            'integration_id' => $integration->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Telegram integration {$status} successfully.",
            'data' => $integration,
        ]);
    }

    public function sendTestMessage(Request $request)
    {
        $integration = TelegramIntegration::where('user_id', auth()->id())->first();

        if (!$integration) {
            $message = 'No Telegram integration found. Please connect your bot first.';

            if ($request->inertia()) {
                return redirect()->back()->with('error', $message);
            }

            return response()->json(['success' => false, 'message' => $message], 404);
        }

        if (!$integration->isVerified()) {
            $message = 'Telegram integration is not yet verified. Please send /start to your bot to verify.';

            if ($request->inertia()) {
                return redirect()->back()->with('error', $message);
            }

            return response()->json(['success' => false, 'message' => $message], 400);
        }

        $testMessage = "✅ Telegram integration connected successfully\n\nThis is a test message from your ecommerce system.";

        SendTelegramMessageJob::dispatch($integration, $testMessage)
            ->onQueue('default');

        Log::info('SendTelegramMessageJob dispatched for test', [
            'user_id' => auth()->id(),
            'integration_id' => $integration->id,
        ]);

        if ($request->inertia()) {
            return redirect()->back()->with('success', 'Test message queued successfully. Please check your Telegram chat shortly.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Test message queued successfully.',
        ]);
    }
}
