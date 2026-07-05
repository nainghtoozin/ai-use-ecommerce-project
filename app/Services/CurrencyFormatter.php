<?php

namespace App\Services;

use App\Enums\CurrencyCode;
use App\Models\PlatformSetting;
use App\Models\WebsiteInfo;

class CurrencyFormatter
{
    public function format(
        float|int|string $amount,
        string $currencyCode = 'MMK',
        string $symbol = '',
        string $position = 'before',
        int $decimalPlaces = 0,
    ): string {
        $amount = (float) $amount;
        $code = CurrencyCode::tryFrom(strtoupper($currencyCode));
        $defaultSymbol = $code ? $code->symbol() : '';
        $defaultDecimals = $code ? $code->decimalPlaces() : 0;
        $useSymbol = $symbol ?: $defaultSymbol;
        $useDecimals = $decimalPlaces >= 0 ? $decimalPlaces : $defaultDecimals;
        $formatted = number_format($amount, $useDecimals);
        if ($position === 'after') {
            return $formatted . ' ' . $useSymbol;
        }
        return $useSymbol . $formatted;
    }

    public function formatPlatform(float|int|string $amount): string
    {
        $settings = PlatformSetting::current();
        return $this->format(
            amount: $amount,
            currencyCode: $settings->platform_currency_code ?? 'MMK',
            symbol: $settings->platform_currency_symbol ?? '',
            position: $settings->platform_currency_position ?? 'before',
            decimalPlaces: $settings->platform_decimal_places ?? 0,
        );
    }

    public function formatMerchant(float|int|string $amount, ?WebsiteInfo $websiteInfo = null): string
    {
        $info = $websiteInfo ?? WebsiteInfo::getSettings();
        return $this->format(
            amount: $amount,
            currencyCode: $info->currency_code ?? 'MMK',
            symbol: $info->currency_symbol ?? '',
            position: $info->currency_position ?? 'before',
            decimalPlaces: $info->decimal_places ?? 0,
        );
    }
}
