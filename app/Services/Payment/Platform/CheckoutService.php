<?php

namespace App\Services\Payment\Platform;

use App\Data\Currency;
use App\Enums\Payment\TransactionStatus;

class CheckoutService
{
    public function __construct(
        private readonly SubscriptionPaymentService $paymentService,
        private readonly PaymentAuditService $audit,
    ) {}

    public function initiateCheckout(
        string $gateway,
        float $amount,
        Currency $currency,
        string $description,
        array $metadata = [],
        ?string $returnUrl = null,
        ?string $cancelUrl = null,
    ): array {
        return [
            'gateway' => $gateway,
            'amount' => $amount,
            'currency' => $currency->code(),
            'description' => $description,
            'metadata' => $metadata,
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
        ];
    }

    public function getCheckoutStatus(TransactionStatus $status): string
    {
        return match (true) {
            $status->isSuccess() => 'completed',
            $status->isPending() => 'in_progress',
            $status->isTerminal() => 'failed',
            default => 'unknown',
        };
    }
}
