<?php

namespace App\Services\Webhook;

use App\Contracts\Webhook\PaymentGatewayAdapter;
use InvalidArgumentException;

class WebhookRouter
{
    private array $adapters = [];

    public function register(PaymentGatewayAdapter $adapter): void
    {
        $this->adapters[$adapter->getGatewayName()] = $adapter;
    }

    public function resolve(string $gateway): PaymentGatewayAdapter
    {
        if (!isset($this->adapters[$gateway])) {
            throw new InvalidArgumentException("Unknown webhook gateway: {$gateway}");
        }

        return $this->adapters[$gateway];
    }

    public function has(string $gateway): bool
    {
        return isset($this->adapters[$gateway]);
    }

    public function getRegisteredGateways(): array
    {
        return array_keys($this->adapters);
    }
}
