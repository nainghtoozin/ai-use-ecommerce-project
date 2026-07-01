<?php

namespace App\Enums;

enum CurrencyCode: string
{
    case MMK = 'MMK';
    case USD = 'USD';
    case THB = 'THB';
    case SGD = 'SGD';
    case EUR = 'EUR';

    public function label(): string
    {
        return match ($this) {
            self::MMK => 'Myanmar Kyat',
            self::USD => 'US Dollar',
            self::THB => 'Thai Baht',
            self::SGD => 'Singapore Dollar',
            self::EUR => 'Euro',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::MMK => 'K',
            self::USD => '$',
            self::THB => '฿',
            self::SGD => 'S$',
            self::EUR => '€',
        };
    }

    public function decimalPlaces(): int
    {
        return match ($this) {
            self::MMK => 0,
            self::USD => 2,
            self::THB => 2,
            self::SGD => 2,
            self::EUR => 2,
        };
    }

    public function isActive(): bool
    {
        return match ($this) {
            default => true,
        };
    }
}
