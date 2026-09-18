import { useMemo } from 'react';
import { usePage } from '@inertiajs/react';

const DEFAULTS = {
    product_info: { show_category: true, show_brand: true, show_product_type: true, show_sku: true },
    pricing: { show_current_price: true, show_original_price: true, show_savings: true, show_discount_percentage: true },
    stock: { display_mode: 'status', low_stock_threshold: 10, show_out_of_stock: true },
    actions: { show_add_to_cart: true, show_view_product: true, show_wishlist: true },
};

const STOCK_MODES = ['status', 'quantity', 'status_quantity', 'hidden'];

function bool(value, fallback) {
    if (value === undefined || value === null) return fallback;
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value !== 0;
    if (typeof value === 'string') {
        const v = value.trim().toLowerCase();
        if (['1', 'true', 'yes', 'on'].includes(v)) return true;
        if (['0', 'false', 'no', 'off', ''].includes(v)) return false;
    }
    return fallback;
}

function pick(source, defaults) {
    const out = {};
    for (const key of Object.keys(defaults)) {
        out[key] = bool(source?.[key], defaults[key]);
    }
    return out;
}

export function useProductDisplay() {
    const { storefront } = usePage().props;

    return useMemo(() => {
        const stored = storefront?.product_display || {};
        const threshold = Number(stored.stock?.low_stock_threshold);

        return {
            product_info: pick(stored.product_info, DEFAULTS.product_info),
            pricing: { ...pick(stored.pricing, DEFAULTS.pricing), show_current_price: true },
            stock: {
                display_mode: STOCK_MODES.includes(stored.stock?.display_mode) ? stored.stock.display_mode : 'status',
                low_stock_threshold: Number.isFinite(threshold) && threshold >= 0 ? threshold : 10,
                show_out_of_stock: bool(stored.stock?.show_out_of_stock, true),
            },
            actions: pick(stored.actions, DEFAULTS.actions),
        };
    }, [storefront?.product_display]);
}

export function displayUnit(product) {
    return product?.unit?.short_name || product?.unit?.name || null;
}

export function formatUnits(units, product) {
    const unit = displayUnit(product);
    return unit ? `${units} ${unit}` : `${units}`;
}
