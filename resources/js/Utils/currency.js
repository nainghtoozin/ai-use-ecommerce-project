const DEFAULT_SYMBOLS = {
    MMK: 'Ks', USD: '$', THB: '\u0e3f', SGD: 'S$', EUR: '\u20ac',
};

export function getPlatformCurrencyConfig(platform_setting) {
    return {
        code: platform_setting?.platform_currency_code || 'MMK',
        symbol: platform_setting?.platform_currency_symbol || DEFAULT_SYMBOLS[platform_setting?.platform_currency_code] || 'Ks',
        position: platform_setting?.platform_currency_position || 'before',
        decimals: platform_setting?.platform_decimal_places ?? 0,
    };
}

export function getCurrencyConfig(platform_setting, website_info) {
    if (website_info?.currency_code) {
        return {
            code: website_info.currency_code,
            symbol: website_info.currency_symbol || DEFAULT_SYMBOLS[website_info.currency_code] || '',
            position: website_info.currency_position || 'before',
            decimals: website_info.decimal_places ?? 0,
        };
    }
    return {
        code: platform_setting?.platform_currency_code || 'MMK',
        symbol: platform_setting?.platform_currency_symbol || DEFAULT_SYMBOLS[platform_setting?.platform_currency_code] || 'Ks',
        position: platform_setting?.platform_currency_position || 'before',
        decimals: platform_setting?.platform_decimal_places ?? 0,
    };
}

export function formatCurrency(amount, {
    code = 'MMK',
    symbol,
    position = 'before',
    decimals,
} = {}) {
    if (amount === null || amount === undefined) return '\u2014';
    const num = Number(amount);
    const resolvedDecimals = decimals !== undefined ? decimals : (code === 'MMK' ? 0 : 2);
    const formatted = num.toLocaleString('en-US', {
        minimumFractionDigits: resolvedDecimals,
        maximumFractionDigits: resolvedDecimals,
    });
    const sym = symbol || DEFAULT_SYMBOLS[code] || code;
    return position === 'after' ? `${formatted} ${sym}` : `${sym}${formatted}`;
}
