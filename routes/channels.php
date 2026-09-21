<?php

use App\Models\Account;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

$typedDirection = function ($q, int $fromId, string $fromType, int $toId, string $toType): void {
    $q->where('sender_id', $fromId)->where('receiver_id', $toId)
        ->where(function ($q) use ($fromType, $toType) {
            $q->where(function ($q) use ($fromType, $toType) {
                $q->where('sender_type', $fromType)->where('receiver_type', $toType);
            })->orWhere(function ($q) {
                $q->whereNull('sender_type')->whereNull('receiver_type');
            });
        });
};

$conversationBetween = function (int $aId, string $aType, int $bId, string $bType) use ($typedDirection): bool {
    return Message::where(function ($q) use ($typedDirection, $aId, $aType, $bId, $bType) {
        $typedDirection($q, $aId, $aType, $bId, $bType);
    })->orWhere(function ($q) use ($typedDirection, $aId, $aType, $bId, $bType) {
        $typedDirection($q, $bId, $bType, $aId, $aType);
    })->exists();
};

Broadcast::channel('chat.user.{userId}', function ($user, $userId) use ($conversationBetween) {
    if ($user instanceof User && (int) $user->id === (int) $userId) {
        return true;
    }

    return $conversationBetween((int) $user->id, $user::class, (int) $userId, User::class);
});

Broadcast::channel('chat.account.{accountId}', function ($user, $accountId) use ($conversationBetween) {
    if ($user instanceof Account && (int) $user->id === (int) $accountId) {
        return true;
    }

    return $conversationBetween((int) $user->id, $user::class, (int) $accountId, Account::class);
});

Broadcast::channel('notifications.user.{userId}', function ($user, $userId) {
    return $user instanceof User && (int) $user->id === (int) $userId;
});

Broadcast::channel('notifications.account.{accountId}', function ($user, $accountId) {
    return $user instanceof Account && (int) $user->id === (int) $accountId;
});
