<?php

namespace App\Services\Payment;

use App\Contracts\PaymentProvider;
use InvalidArgumentException;

class PaymentGatewayResolver
{
    private array $providers = [];

    public function __construct(array $providers = [])
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    public function register(PaymentProvider $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    public function resolve(?string $gateway): PaymentProvider
    {
        $key = $gateway ?? config('payments.default', 'manual_transfer');

        if (!isset($this->providers[$key])) {
            throw new InvalidArgumentException("Payment gateway \"{$key}\" is not registered.");
        }

        return $this->providers[$key];
    }

    public function all(): array
    {
        return $this->providers;
    }

    public function available(): array
    {
        return array_filter($this->providers, fn(PaymentProvider $p) => $p->isAvailable());
    }

    public function configured(): array
    {
        return array_filter($this->providers, fn(PaymentProvider $p) => $p->isConfigured());
    }

    public function supports(string $gateway): bool
    {
        return isset($this->providers[$gateway]);
    }
}
