<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TelegramIntegration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TelegramRecipientResolver
{
    public function resolve(?Order $order = null): Collection
    {
        Log::info('[TelegramRecipientResolver] Starting recipient resolution', [
            'order_id' => $order?->id,
        ]);

        $query = TelegramIntegration::query()
            ->where('is_enabled', true)
            ->verified();

        // SaaS-ready: scope by tenant/store when relationships exist
        // Example for future multi-tenant support:
        // if ($order && $order->shop_id) {
        //     $query->whereHas('user', function ($q) use ($order) {
        //         $q->whereHas('shops', function ($sq) use ($order) {
        //             $sq->where('shops.id', $order->shop_id);
        //         });
        //     });
        // }

        $integrations = $query->get();

        $count = $integrations->count();
        $ids = $integrations->pluck('id')->toArray();

        Log::info('[TelegramRecipientResolver] Resolution complete', [
            'order_id' => $order?->id,
            'count' => $count,
            'integration_ids' => $ids,
            'skipped' => $count === 0,
        ]);

        return $integrations;
    }
}
