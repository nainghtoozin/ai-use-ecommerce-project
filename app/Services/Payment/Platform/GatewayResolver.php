<?php

namespace App\Services\Payment\Platform;

use App\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

class GatewayResolver
{
    private array $gateways = [];

    public function __construct(array $gateways = [])
    {
        foreach ($gateways as $gateway) {
            $this->register($gateway);
        }
    }

    public function register(PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$gateway->getName()] = $gateway;
    }

    public function resolve(?string $name): PaymentGatewayInterface
    {
        $key = $name ?? config('payments.default', 'manual');

        if (!isset($this->gateways[$key])) {
            throw new InvalidArgumentException("Payment gateway \"{$key}\" is not registered.");
        }

        return $this->gateways[$key];
    }

    public function all(): array
    {
        return $this->gateways;
    }

    public function available(): array
    {
        return array_filter($this->gateways, fn(PaymentGatewayInterface $g) => $g->isAvailable());
    }

    public function configured(): array
    {
        return array_filter($this->gateways, fn(PaymentGatewayInterface $g) => $g->isConfigured());
    }

    public function supports(string $name): bool
    {
        return isset($this->gateways[$name]);
    }
}
