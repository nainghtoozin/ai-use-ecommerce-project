<?php

namespace App\Http\Controllers;

use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationPreferenceService $preferenceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->simplePaginate($perPage)
            ->through(fn ($notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Notification',
                'message' => $notification->data['message'] ?? '',
                'order_id' => $notification->data['order_id'] ?? null,
                'action_url' => $notification->data['action_url'] ?? null,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at?->toIso8601String(),
                'created_at_human' => $notification->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => $notifications->items(),
            'next_page_url' => $notifications->nextPageUrl(),
            'prev_page_url' => $notifications->previousPageUrl(),
        ]);
    }

    public function page(Request $request): \Inertia\Response
    {
        $perPage = (int) $request->query('per_page', 10);
        if (!in_array($perPage, [10, 20, 50], true)) {
            $perPage = 10;
        }

        $status = $request->query('status', 'all');
        $category = $request->query('category', 'all');

        $query = $request->user()->notifications()->latest();

        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($category !== 'all') {
            $query->where(function ($q) use ($category) {
                foreach ($this->customerCategoryKeywords($category) as $keyword) {
                    $q->orWhere('data->title', 'like', '%' . $keyword . '%');
                }
            });
        }

        $notifications = $query->paginate($perPage)->through(fn ($notification) => [
            'id' => $notification->id,
            'title' => $notification->data['title'] ?? 'Notification',
            'message' => $notification->data['message'] ?? '',
            'order_id' => $notification->data['order_id'] ?? null,
            'action_url' => $notification->data['action_url'] ?? null,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_human' => $notification->created_at?->diffForHumans(),
            'category' => $this->customerCategoryFor($notification->data['title'] ?? ''),
        ]);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'filters' => [
                'status' => $status,
                'category' => $category,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function adminPage(Request $request): \Inertia\Response
    {
        $perPage = (int) $request->query('per_page', 10);
        if (!in_array($perPage, [10, 20, 50], true)) {
            $perPage = 10;
        }

        $status = $request->query('status', 'all');
        $category = $request->query('category', 'all');

        $query = $request->user()->notifications()->latest();

        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($category !== 'all') {
            $query->where(function ($q) use ($category) {
                foreach ($this->adminCategoryKeywords($category) as $keyword) {
                    $q->orWhere('data->title', 'like', '%' . $keyword . '%');
                }
            });
        }

        $notifications = $query->paginate($perPage)->through(fn ($notification) => [
            'id' => $notification->id,
            'title' => $notification->data['title'] ?? 'Notification',
            'message' => $notification->data['message'] ?? '',
            'order_id' => $notification->data['order_id'] ?? null,
            'action_url' => $notification->data['action_url'] ?? null,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_human' => $notification->created_at?->diffForHumans(),
            'category' => $this->adminCategoryFor($notification->data['title'] ?? ''),
        ]);

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'filters' => [
                'status' => $status,
                'category' => $category,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function counts(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notificationId): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true, 'unread_count' => 0]);
    }

    private function customerCategoryFor(string $title): string
    {
        $t = mb_strtolower($title);
        if (str_contains($t, 'cancel') || str_contains($t, 'return') || str_contains($t, 'refund')) {
            return 'cancellation_returns';
        }
        if (str_contains($t, 'account') || str_contains($t, 'security') || str_contains($t, 'password')) {
            return 'account_security';
        }
        if (str_contains($t, 'promo') || str_contains($t, 'offer') || str_contains($t, 'campaign')) {
            return 'promotions';
        }
        return 'order_updates';
    }

    private function customerCategoryKeywords(string $category): array
    {
        return match ($category) {
            'order_updates' => ['Order', 'Payment'],
            'cancellation_returns' => ['Cancel', 'Return', 'Refund'],
            'account_security' => ['Account', 'Security', 'Password'],
            'promotions' => ['Promo', 'Offer', 'Campaign'],
            default => [],
        };
    }

    private function adminCategoryFor(string $title): string
    {
        $t = mb_strtolower($title);
        if (str_contains($t, 'new order')) {
            return 'new_orders';
        }
        if (str_contains($t, 'payment')) {
            return 'payment';
        }
        if (str_contains($t, 'cancel') || str_contains($t, 'return') || str_contains($t, 'refund')) {
            return 'cancellation_returns';
        }
        if (str_contains($t, 'customer')) {
            return 'customer_activity';
        }
        if (str_contains($t, 'stock') || str_contains($t, 'inventory')) {
            return 'inventory';
        }
        if (str_contains($t, 'system') || str_contains($t, 'alert')) {
            return 'system';
        }
        return 'order_updates';
    }

    private function adminCategoryKeywords(string $category): array
    {
        return match ($category) {
            'new_orders' => ['New Order'],
            'payment' => ['Payment'],
            'order_updates' => ['Order', 'Shipped', 'Delivered', 'Processing', 'Confirmed'],
            'cancellation_returns' => ['Cancel', 'Return', 'Refund'],
            'customer_activity' => ['Customer', 'Registration'],
            'inventory' => ['Stock', 'Inventory'],
            'system' => ['System', 'Alert'],
            default => [],
        };
    }

    public function preferences(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'preferences' => $user->notification_preferences,
            'allowed_types' => $user->getAllowedNotificationTypes(),
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $allowedTypes = $user->getAllowedNotificationTypes();

        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['boolean'],
        ]);

        $prefs = $user->notification_preferences ?? [];

        foreach ($validated['preferences'] as $type => $value) {
            if (in_array($type, $allowedTypes, true)) {
                $prefs[$type] = (bool) $value;
            }
        }

        $user->notification_preferences = $prefs;
        $user->save();

        return response()->json([
            'success' => true,
            'preferences' => $user->fresh()->notification_preferences,
        ]);
    }
}
