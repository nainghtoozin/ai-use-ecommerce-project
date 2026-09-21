<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TelegramIntegration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TelegramRecipientResolver
{
    public function resolve(?Order $order = null, ?int $tenantId = null): Collection
    {
        $tenantId ??= $order?->tenant_id;

        Log::info('[TelegramRecipientResolver] Starting recipient resolution', [
            'order_id' => $order?->id,
            'tenant_id' => $tenantId,
        ]);

        $query = TelegramIntegration::query()
            ->where('is_enabled', true)
            ->anyVerified();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

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
