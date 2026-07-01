<?php

namespace App\Enums\Payment;

enum GatewayType: string
{
    case MANUAL = 'manual';
    case STRIPE = 'stripe';
    case KBZ_PAY = 'kpay';
    case AYA_PAY = 'ayapay';
    case WAVE_PAY = 'wavepay';
    case PAYPAL = 'paypal';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual Transfer',
            self::STRIPE => 'Stripe',
            self::KBZ_PAY => 'KBZ Pay',
            self::AYA_PAY => 'AYA Pay',
            self::WAVE_PAY => 'Wave Pay',
            self::PAYPAL => 'PayPal',
        };
    }

    public function isOffline(): bool
    {
        return $this === self::MANUAL;
    }

    public function isOnline(): bool
    {
        return !$this->isOffline();
    }

    public function requiresWebhook(): bool
    {
        return in_array($this, [self::STRIPE, self::PAYPAL]);
    }

    public static function forSubscription(): array
    {
        return [self::STRIPE, self::PAYPAL, self::MANUAL];
    }
}
