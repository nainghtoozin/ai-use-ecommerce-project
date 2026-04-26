<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Notifications\NewChatMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string|max:5000',
        ]);

        $senderId = Auth::id();
        $receiverId = $request->receiver_id;

        if ($senderId === $receiverId) {
            return response()->json(['error' => 'Cannot send message to yourself'], 400);
        }

        $message = Message::create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => $request->message,
            'is_read' => false,
        ]);

        $receiver = User::find($receiverId);
        if ($receiver) {
            $receiver->notify(new NewChatMessageNotification($message));
        }

        return response()->json([
            'success' => true,
            'message' => $message->load(['sender', 'receiver']),
        ]);
    }

    public function fetchMessages($userId)
    {
        $currentUserId = Auth::id();

        $messages = Message::conversation($currentUserId, $userId)
            ->orderBy('created_at', 'asc')
            ->get(['id', 'sender_id', 'receiver_id', 'message', 'is_read', 'created_at']);

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    public function markAsRead($userId)
    {
        $currentUserId = Auth::id();

        Message::where('receiver_id', $currentUserId)
            ->where('sender_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function getUnreadCount()
    {
        $currentUserId = Auth::id();

        $count = Message::where('receiver_id', $currentUserId)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    public function index()
    {
        $currentUserId = Auth::id();

        $conversations = Message::where('sender_id', $currentUserId)
            ->orWhere('receiver_id', $currentUserId)
            ->selectRaw('
                LEAST(sender_id, receiver_id) as user1,
                GREATEST(sender_id, receiver_id) as user2,
                MAX(id) as max_id
            ')
            ->groupBy('user1', 'user2')
            ->orderBy('max_id', 'desc')
            ->get();

        $userIds = [];
        foreach ($conversations as $conv) {
            $userIds[] = $conv->user1 === $currentUserId ? $conv->user2 : $conv->user1;
        }

        $users = User::whereIn('id', array_unique($userIds))
            ->where('id', '!=', $currentUserId)
            ->get(['id', 'name', 'email']);

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

    public function getAdminUsers()
    {
        $users = User::where('role', '!=', 'admin')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'email']);

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