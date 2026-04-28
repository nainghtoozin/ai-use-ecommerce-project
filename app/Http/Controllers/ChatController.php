<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(): View
    {
        $currentUserId = Auth::id();

        $conversations = Message::where('sender_id', $currentUserId)
            ->orWhere('receiver_id', $currentUserId)
            ->selectRaw('LEAST(sender_id, receiver_id) as user1, GREATEST(sender_id, receiver_id) as user2, MAX(id) as max_id')
            ->groupBy('user1', 'user2')
            ->orderBy('max_id', 'desc')
            ->get();

        $userIds = [];
        foreach ($conversations as $conv) {
            $userIds[] = $conv->user1 === $currentUserId ? $conv->user2 : $conv->user1;
        }

        $users = User::whereIn('id', array_unique($userIds))
            ->where('id', '!=', $currentUserId)
            ->get(['id', 'name']);

        $result = [];
        foreach ($users as $user) {
            $lastMessage = Message::conversation($currentUserId, $user->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $unreadCount = Message::where('sender_id', $user->id)
                ->where('receiver_id', $currentUserId)
                ->where('is_read', false)
                ->count();

            $result[] = [
                'user' => $user,
                'last_message' => $lastMessage,
                'unread_count' => $unreadCount,
            ];
        }

        if (empty($result)) {
            $admin = User::where('role', 'admin')->first();
            if ($admin && $admin->id !== $currentUserId) {
                $result[] = [
                    'user' => $admin,
                    'last_message' => null,
                    'unread_count' => 0,
                ];
            }
        }

        return view('chat', ['conversations' => $result]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'receiver_id' => 'required|integer|exists:users,id',
            'message' => 'required|string|max:5000',
        ]);

        $senderId = Auth::id();
        $receiverId = (int) $request->receiver_id;

        if ($senderId === $receiverId) {
            return response()->json(['error' => 'Cannot send message to yourself'], 400);
        }

        $message = Message::create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => $request->message,
        ]);

        $message->load('sender:id,name');

        event(new MessageSent($message));

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    public function fetchMessages(int $userId): JsonResponse
    {
        $currentUserId = Auth::id();

        $messages = Message::conversation($currentUserId, $userId)
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get(['id', 'sender_id', 'receiver_id', 'message', 'created_at']);

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    public function markAsRead(int $userId): JsonResponse
    {
        $currentUserId = Auth::id();

        Message::where('receiver_id', $currentUserId)
            ->where('sender_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function getUnreadCount(): JsonResponse
    {
        $currentUserId = Auth::id();

        $count = Message::where('receiver_id', $currentUserId)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    public function getAdminUsers(): JsonResponse
    {
        $users = User::where('role', '!=', 'admin')
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        $result = [];
        foreach ($users as $user) {
            $unreadCount = Message::where('receiver_id', Auth::id())
                ->where('sender_id', $user->id)
                ->where('is_read', false)
                ->count();

            $result[] = [
                'user' => $user,
                'unread_count' => $unreadCount,
            ];
        }

        return response()->json(['users' => $result]);
    }
}