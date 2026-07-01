<?php

namespace App\Services\Payment\Platform;

use App\Data\Currency;
use App\Enums\Payment\TransactionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionAuditLog;

class PaymentAuditService
{
    public function log(
        Subscription $subscription,
        string $event,
        string $gateway,
        ?string $transactionId = null,
        ?float $amount = null,
        Currency|string|null $currency = null,
        ?TransactionStatus $status = null,
        array $metadata = [],
    ): SubscriptionAuditLog {
        $currencyCode = $currency instanceof Currency ? $currency->code() : $currency;

        return SubscriptionAuditLog::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'event' => 'payment.' . $event,
            'actor_type' => 'system',
            'actor_id' => null,
            'old_plan_id' => $subscription->plan_id,
            'new_plan_id' => $subscription->plan_id,
            'old_status' => $subscription->status,
            'new_status' => $subscription->status,
            'reason' => sprintf(
                'Gateway: %s | Transaction: %s | Amount: %s %s | Status: %s',
                $gateway,
                $transactionId ?? 'N/A',
                $amount ? number_format($amount, 2) : 'N/A',
                $currencyCode ?? '',
                $status?->value ?? 'N/A',
            ),
            'metadata' => array_merge($metadata, [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'currency' => $currencyCode,
                'payment_status' => $status?->value,
            ]),
        ]);
    }
}
