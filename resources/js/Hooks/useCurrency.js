import { useCallback, useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';

export default function useCurrency() {
    const { platform_setting, website_info } = usePage().props;

    const config = useMemo(() => getCurrencyConfig(platform_setting, website_info),
        [platform_setting, website_info]);

    const formatAmount = useCallback((amount, overrides = {}) =>
        formatCurrency(amount, { ...config, ...overrides }),
    [config]);

    return {
        formatAmount,
        currencyCode: config.code,
        currencySymbol: config.symbol,
        currencyPosition: config.position,
        decimalPlaces: config.decimals,
    };
}
