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
        $perPage = 20;

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

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function adminPage(Request $request): \Inertia\Response
    {
        $perPage = 20;

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

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()->count(),
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

        return response()->json(['success' => true]);
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
