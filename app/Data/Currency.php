<?php

namespace App\Data;

use App\Enums\CurrencyCode;
use InvalidArgumentException;

class Currency
{
    public function __construct(
        public readonly CurrencyCode $code,
        public readonly string $name,
        public readonly string $symbol,
        public readonly int $decimalPlaces,
        public readonly bool $active = true,
    ) {}

    public static function fromCode(string $code): self
    {
        $enum = CurrencyCode::tryFrom(strtoupper($code));

        if (!$enum) {
            throw new InvalidArgumentException("Unsupported currency code: {$code}");
        }

        return new self(
            code: $enum,
            name: $enum->label(),
            symbol: $enum->symbol(),
            decimalPlaces: $enum->decimalPlaces(),
            active: $enum->isActive(),
        );
    }

    public static function fromEnum(CurrencyCode $code): self
    {
        return new self(
            code: $code,
            name: $code->label(),
            symbol: $code->symbol(),
            decimalPlaces: $code->decimalPlaces(),
            active: $code->isActive(),
        );
    }

    public static function default(): self
    {
        return self::fromCode(config('payments.default_currency', 'MMK'));
    }

    public function code(): string
    {
        return $this->code->value;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function is(string|CurrencyCode $code): bool
    {
        $target = $code instanceof CurrencyCode ? $code : CurrencyCode::tryFrom(strtoupper($code));
        return $this->code === $target;
    }

    public function format(float|int $amount): string
    {
        $formatted = number_format($amount, $this->decimalPlaces);

        if ($this->code === CurrencyCode::MMK) {
            return $formatted . ' ' . $this->code->value;
        }

        return $this->symbol . $formatted;
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'decimal_places' => $this->decimalPlaces,
            'active' => $this->active,
        ];
    }

    public static function fromArray(array $data): self
    {
        return self::fromCode($data['code'] ?? 'MMK');
    }
}
