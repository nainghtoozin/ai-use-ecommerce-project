<?php

namespace App\Listeners;

use App\Events\Payments\PaymentIntentCompleted;
use App\Services\TenantCleanupService;
use Illuminate\Support\Facades\Log;

class CancelTenantCleanupOnPaymentCompleted
{
    public function __construct(
        private readonly TenantCleanupService $cleanup,
    ) {}

    public function handle(PaymentIntentCompleted $event): void
    {
        $tenantId = $event->intent->tenant_id;

        if (!$tenantId) {
            return;
        }

        try {
            $this->cleanup->cancelForTenant($tenantId, 'Payment approved — pending cleanup cancelled.');
        } catch (\Throwable $e) {
            // Best-effort: cleanup cancellation must never break payment processing.
            Log::warning('Failed to cancel tenant cleanup after payment.', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
