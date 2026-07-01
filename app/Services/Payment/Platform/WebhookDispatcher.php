<?php

namespace App\Services\Payment\Platform;

use App\Contracts\PaymentGatewayInterface;
use App\Services\Payment\DTOs\WebhookResult;

class WebhookDispatcher
{
    public function __construct(
        private readonly GatewayResolver $resolver,
    ) {}

    public function dispatch(
        string $gateway,
        array $payload,
        ?string $signature = null,
    ): WebhookResult {
        $provider = $this->resolver->resolve($gateway);

        return $provider->handleWebhook($payload, $signature);
    }

    public function supportsWebhooks(string $gateway): bool
    {
        try {
            $provider = $this->resolver->resolve($gateway);
            return !$provider instanceof \App\Services\Payment\Platform\Gateways\ManualPaymentGateway;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
