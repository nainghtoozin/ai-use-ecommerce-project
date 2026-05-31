<?php

namespace App\Services;

use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class SubscriptionExpiryService
{
    public function process(): array
    {
        $result = [
            'active_expired' => 0,
            'trial_ended' => 0,
            'total' => 0,
        ];

        Subscription::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunk(100, function ($subscriptions) use (&$result) {
                foreach ($subscriptions as $sub) {
                    $sub->update([
                        'status' => 'expired',
                        'notes' => $sub->notes
                            ? $sub->notes . "\n[" . now() . "] Auto-expired (past expires_at)."
                            : "[" . now() . "] Auto-expired (past expires_at).",
                    ]);
                    $result['active_expired']++;
                    $result['total']++;
                }
            });

        Subscription::where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->chunk(100, function ($subscriptions) use (&$result) {
                foreach ($subscriptions as $sub) {
                    $sub->update([
                        'status' => 'expired',
                        'notes' => $sub->notes
                            ? $sub->notes . "\n[" . now() . "] Trial ended — auto-expired."
                            : "[" . now() . "] Trial ended — auto-expired.",
                    ]);
                    $result['trial_ended']++;
                    $result['total']++;
                }
            });

        if ($result['total'] > 0) {
            Log::info('Subscription expiry processed', $result);
        }

        return $result;
    }
}
