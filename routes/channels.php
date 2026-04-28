<?php

use App\Models\Message;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('chat.{userId}', function ($user, $userId) {
    if ($user->id === (int) $userId) {
        return true;
    }

    $messageExists = Message::where(function ($q) use ($user, $userId) {
        $q->where('sender_id', $user->id)->where('receiver_id', $userId);
    })->orWhere(function ($q) use ($user, $userId) {
        $q->where('sender_id', $userId)->where('receiver_id', $user->id);
    })->exists();

    return $messageExists;
});