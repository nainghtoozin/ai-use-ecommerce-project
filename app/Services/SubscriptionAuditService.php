<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionAuditLog;

class SubscriptionAuditService
{
    public static function log(Subscription $subscription, string $event, array $data = []): SubscriptionAuditLog
    {
        $user = auth()->user();

        $actorType = match (true) {
            !is_null($user) && $user->isSuperAdmin() => 'superadmin',
            !is_null($user) => 'merchant',
            default => 'system',
        };

        return SubscriptionAuditLog::create(array_merge([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'event' => $event,
            'actor_type' => $actorType,
            'actor_id' => $user?->id,
            'new_status' => $subscription->status,
        ], $data));
    }
}
