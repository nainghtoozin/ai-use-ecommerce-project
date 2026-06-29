<?php

namespace App\Http\Controllers;

use App\Http\Requests\TelegramIntegrationRequest;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\TelegramIntegration;
use App\Services\TelegramNotificationRouter;
use App\Services\TelegramOrderMessageBuilder;
use App\Services\TelegramService;
use App\Services\TelegramSystemAlertMessageBuilder;
use App\Services\TelegramWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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
                    'personal_chat_id' => $integration->personal_chat_id,
                    'personal_chat_username' => $integration->personal_chat_username,
                    'personal_chat_title' => $integration->personal_chat_title,
                    'personal_verified_at' => $integration->personal_verified_at?->toIso8601String(),
                    'personal_status_label' => $integration->getPersonalStatusLabel(),
                    'group_chat_id' => $integration->group_chat_id,
                    'group_chat_title' => $integration->group_chat_title,
                    'group_chat_username' => $integration->group_chat_username,
                    'group_chat_type' => $integration->group_chat_type,
                    'group_verified_at' => $integration->group_verified_at?->toIso8601String(),
                    'group_status_label' => $integration->getGroupStatusLabel(),
                    'group_status_badge' => $integration->getGroupStatusBadge(),
                    'default_destination' => $integration->default_destination ?? 'personal',
                    'order_destination' => $integration->order_destination,
                    'payment_destination' => $integration->payment_destination,
                    'inventory_destination' => $integration->inventory_destination,
                    'system_destination' => $integration->system_destination,
                    'marketing_destination' => $integration->marketing_destination,
                    'manual_destination' => $integration->manual_destination,
                    'category_destinations' => $integration->getCategoryDestinations(),
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
                'personal_chat_id' => $integration->personal_chat_id,
                'personal_chat_username' => $integration->personal_chat_username,
                'personal_chat_title' => $integration->personal_chat_title,
                'personal_verified_at' => $integration->personal_verified_at?->toIso8601String(),
                'personal_status_label' => $integration->getPersonalStatusLabel(),
                'group_chat_id' => $integration->group_chat_id,
                'group_chat_title' => $integration->group_chat_title,
                'group_chat_username' => $integration->group_chat_username,
                'group_chat_type' => $integration->group_chat_type,
                'group_verified_at' => $integration->group_verified_at?->toIso8601String(),
                'group_status_label' => $integration->getGroupStatusLabel(),
                'group_status_badge' => $integration->getGroupStatusBadge(),
                'default_destination' => $integration->default_destination ?? 'personal',
                'order_destination' => $integration->order_destination,
                'payment_destination' => $integration->payment_destination,
                'inventory_destination' => $integration->inventory_destination,
                'system_destination' => $integration->system_destination,
                'marketing_destination' => $integration->marketing_destination,
                'manual_destination' => $integration->manual_destination,
                'category_destinations' => $integration->getCategoryDestinations(),
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

    public function disconnectGroup(Request $request): JsonResponse
    {
        $integration = TelegramIntegration::where('user_id', auth()->id())->first();

        if (!$integration) {
            return response()->json([
                'success' => false,
                'message' => 'No Telegram integration found.',
            ], 404);
        }

        $integration->disconnectGroup();

        Log::info('Group chat disconnected', [
            'user_id' => auth()->id(),
            'integration_id' => $integration->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Group disconnected successfully.',
        ]);
    }

    public function testGroupNotification(Request $request)
    {
        $integration = TelegramIntegration::where('user_id', auth()->id())->first();

        if (!$integration) {
            return response()->json([
                'success' => false,
                'message' => 'No Telegram integration found.',
            ], 404);
        }

        if (!$integration->isGroupVerified()) {
            return response()->json([
                'success' => false,
                'message' => 'No group connected. Add your bot to a group and send a message first.',
            ], 400);
        }

        $testMessage = "✅ Telegram group notifications working\n\nGroup: {$integration->group_chat_title}\nThis is a test notification from your ecommerce system.";

        SendTelegramMessageJob::dispatch($integration, $testMessage)
            ->onQueue('default');

        Log::info('Group notification test dispatched', [
            'user_id' => auth()->id(),
            'integration_id' => $integration->id,
            'group_chat_id' => $integration->group_chat_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Test group notification queued successfully.',
        ]);
    }

    public function reconnectPersonalChat(Request $request): JsonResponse
    {
        $integration = TelegramIntegration::where('user_id', auth()->id())->first();

        if (!$integration) {
            return response()->json([
                'success' => false,
                'message' => 'No Telegram integration found.',
            ], 404);
        }

        $integration->personal_chat_id = null;
        $integration->personal_chat_username = null;
        $integration->personal_chat_title = null;
        $integration->personal_verified_at = null;
        $integration->save();

        Log::info('Personal chat reset for reconnection', [
            'user_id' => auth()->id(),
            'integration_id' => $integration->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Personal chat reset. Send /start to your bot to reconnect.',
        ]);
    }

    public function updateDestination(Request $request, TelegramNotificationRouter $router): JsonResponse
    {
        $integration = TelegramIntegration::where('user_id', auth()->id())->first();

        if (!$integration) {
            return response()->json([
                'success' => false,
                'message' => 'No Telegram integration found.',
            ], 404);
        }

        $validated = $request->validate([
            'default_destination' => ['required', Rule::in(TelegramNotificationRouter::DESTINATIONS)],
            'order_destination' => ['nullable', Rule::in(TelegramNotificationRouter::DESTINATIONS)],
            'payment_destination' => ['nullable', Rule::in(TelegramNotificationRouter::DESTINATIONS)],
            'inventory_destination' => ['nullable', Rule::in(TelegramNotificationRouter::DESTINATIONS)],
            'system_destination' => ['nullable', Rule::in(TelegramNotificationRouter::DESTINATIONS)],
            'marketing_destination' => ['nullable', Rule::in(TelegramNotificationRouter::DESTINATIONS)],
            'manual_destination' => ['nullable', Rule::in(TelegramNotificationRouter::DESTINATIONS)],
        ]);

        $error = $router->validateDestination($integration, $validated['default_destination']);
        if ($error) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        $integration->update($validated);

        Log::info('Telegram notification destinations updated', [
            'user_id' => auth()->id(),
            'integration_id' => $integration->id,
            'destinations' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification destinations updated successfully.',
            'data' => [
                'default_destination' => $integration->default_destination,
                'category_destinations' => $integration->getCategoryDestinations(),
            ],
        ]);
    }

    public function sendTestRouter(Request $request, TelegramNotificationRouter $router): JsonResponse
    {
        $integration = TelegramIntegration::where('user_id', auth()->id())->first();

        if (!$integration) {
            return response()->json([
                'success' => false,
                'message' => 'No Telegram integration found.',
            ], 404);
        }

        $validated = $request->validate([
            'category' => ['required', Rule::in(TelegramNotificationRouter::CATEGORIES)],
        ]);

        $category = $validated['category'];
        $destination = $router->getDestinationForCategory($integration, $category);

        $targets = $router->resolve($integration, $category);

        if (empty($targets)) {
            return response()->json([
                'success' => false,
                'message' => "No targets resolved for '{$category}' notifications (destination: {$destination}). No verified chat available.",
            ], 400);
        }

        $testMessage = "✅ Test Notification\n\nCategory: {$category}\nDestination: {$destination}\nTargets: " . count($targets) . "\nThis is a test from your notification routing system.";

        foreach ($targets as $target) {
            SendTelegramMessageJob::dispatch($integration, $testMessage, $target['chat_id'])
                ->onQueue('default');
        }

        Log::info('Router test notification dispatched', [
            'user_id' => auth()->id(),
            'integration_id' => $integration->id,
            'category' => $category,
            'destination' => $destination,
            'targets' => $targets,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Test '{$category}' notification queued to " . count($targets) . " target(s).",
            'data' => [
                'category' => $category,
                'destination' => $destination,
                'targets' => $targets,
            ],
        ]);
    }

    public function sendSampleOrderNotification(Request $request, TelegramNotificationRouter $router, TelegramOrderMessageBuilder $builder): JsonResponse
    {
        $integration = TelegramIntegration::where('user_id', auth()->id())->first();

        if (!$integration) {
            return response()->json([
                'success' => false,
                'message' => 'No Telegram integration found.',
            ], 404);
        }

        $targets = $router->resolve($integration, 'order');

        if (empty($targets)) {
            return response()->json([
                'success' => false,
                'message' => 'No targets resolved for order notifications. Check your destination settings.',
            ], 400);
        }

        if (!auth()->user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'No tenant found for your account.',
            ], 400);
        }

        $order = Order::where('tenant_id', auth()->user()->tenant_id)
            ->whereNotNull('customer_name')
            ->orderByDesc('id')
            ->first();

        if (!$order) {
            $order = new Order([
                'id' => 99999,
                'customer_name' => 'Sample Customer',
                'phone' => '09-123-456-789',
                'total_amount' => 50000,
                'delivery_fee' => 3000,
                'order_status' => 'pending',
                'payment_status' => 'pending',
                'tenant_id' => auth()->user()->tenant_id,
                'created_at' => now(),
            ]);
            $order->setRelation('items', collect());
            $order->setRelation('paymentMethod', null);
            $order->setRelation('tenant', auth()->user()->tenant);
        }

        $payload = $builder->buildSample($order);

        foreach ($targets as $target) {
            SendTelegramMessageJob::dispatch($integration, $payload->message, $target['chat_id'], $payload->toArray())
                ->onQueue('default');
        }

        Log::info('Sample order notification dispatched', [
            'user_id' => auth()->id(),
            'integration_id' => $integration->id,
            'order_id' => $order->id,
            'targets' => $targets,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sample order notification queued to ' . count($targets) . ' target(s).',
        ]);
    }

    public function previewNotification(Request $request, TelegramNotificationRouter $router): JsonResponse
    {
        $integration = TelegramIntegration::where('user_id', auth()->id())->first();

        if (!$integration) {
            return response()->json(['success' => false, 'message' => 'No Telegram integration found.'], 404);
        }

        $type = $request->validate(['type' => ['required', 'string']])['type'];

        $orderBuilder = app(TelegramOrderMessageBuilder::class);
        $systemBuilder = app(TelegramSystemAlertMessageBuilder::class);
        $tenantId = auth()->user()->tenant_id;

        $order = Order::where('tenant_id', $tenantId)
            ->whereNotNull('customer_name')
            ->orderByDesc('id')
            ->first();

        if (!$order) {
            $order = new Order([
                'customer_name' => 'Sample Customer',
                'phone' => '09-123-456-789',
                'total_amount' => 50000,
                'delivery_fee' => 3000,
                'order_status' => 'pending',
                'payment_status' => 'pending',
                'tenant_id' => $tenantId,
                'created_at' => now(),
            ]);
            $order->setRelation('items', collect());
            $order->setRelation('paymentMethod', null);
            $order->setRelation('tenant', auth()->user()->tenant);
        }

        $payload = match ($type) {
            'order.new' => $orderBuilder->buildNewOrder($order),
            'order.confirmed' => $orderBuilder->buildStatusChange($order, 'confirmed'),
            'order.shipped' => $orderBuilder->buildStatusChange($order, 'shipped'),
            'order.delivered' => $orderBuilder->buildStatusChange($order, 'delivered'),
            'order.cancelled' => $orderBuilder->buildStatusChange($order, 'cancelled_by_admin'),
            'payment.success' => $systemBuilder->paymentSuccess($order),
            'payment.failed' => $systemBuilder->paymentFailed($order, 'Insufficient funds'),
            'payment.proof_uploaded' => $orderBuilder->buildStatusChange($order, 'payment_proof_uploaded'),
            'payment.verified' => $orderBuilder->buildStatusChange($order, 'payment_verified'),
            'payment.rejected' => $orderBuilder->buildStatusChange($order, 'payment_rejected', null, 'Payment does not match order total'),
            'inventory.low_stock' => $systemBuilder->lowStock(new Product(['name' => 'Sample Product', 'sku' => 'SMP-001']), 5),
            'inventory.out_of_stock' => $systemBuilder->outOfStock(new Product(['name' => 'Sample Product', 'sku' => 'SMP-001'])),
            'customer.new' => $systemBuilder->newCustomer(auth()->user()),
            'system.daily_summary' => $systemBuilder->dailySummary([
                'total_orders' => 24,
                'total_revenue' => 1250000,
                'new_customers' => 8,
                'pending_orders' => 3,
                'low_stock_items' => 2,
                'tenant_id' => $tenantId,
            ]),
            'system.queue_failure' => $systemBuilder->queueFailure('SendTelegramMessageJob', 'Connection timed out'),
            'security.alert' => $systemBuilder->securityAlert('New login from unrecognized device', [
                'ip' => '192.168.1.1',
                'device' => 'Chrome on Windows',
                'location' => 'Yangon, Myanmar',
                'time' => now()->toIso8601String(),
            ]),
            'manual.admin' => $systemBuilder->manualAlert('Store will be under maintenance tonight from 2 AM to 4 AM.', 'Admin'),
            default => null,
        };

        if (!$payload) {
            return response()->json(['success' => false, 'message' => "Unknown notification type: {$type}"], 422);
        }

        $destination = $router->getDestinationForCategory($integration, $payload->destination ?? 'system');

        return response()->json([
            'success' => true,
            'data' => [
                'rendered' => $payload->message,
                'raw' => $payload->toArray(),
                'destination' => $destination,
                'category' => $payload->destination,
            ],
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

        if (!$integration->isAnyVerified()) {
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
