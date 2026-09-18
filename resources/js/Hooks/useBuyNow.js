import { useCallback, useRef, useState } from 'react';
import { router } from '@inertiajs/react';

function currentStoreSlug() {
    if (typeof window === 'undefined') return null;
    const match = window.location.pathname.match(/^\/store\/([^/]+)\//);
    return match ? match[1] : null;
}

export function buyNowKey(productId, variantId) {
    return `${productId}:${variantId ?? 0}`;
}

export function useBuyNow() {
    const [buyingKey, setBuyingKey] = useState(null);
    const busyRef = useRef(false);

    const buyNow = useCallback(({ productId, quantity = 1, variantId = null }) => {
        const slug = currentStoreSlug();
        if (!slug || busyRef.current || !productId) return;
        busyRef.current = true;
        setBuyingKey(buyNowKey(productId, variantId));
        router.post(`/store/${slug}/checkout/buy-now`, {
            product_id: productId,
            variant_id: variantId,
            quantity,
        }, {
            onFinish: () => {
                busyRef.current = false;
                setBuyingKey(null);
            },
        });
    }, []);

    return { buyNow, buyingKey };
}
