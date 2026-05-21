import { useState, useCallback } from 'react';
import { usePage, router } from '@inertiajs/react';

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

export function useWishlist() {
    const { props } = usePage();
    const [processingId, setProcessingId] = useState(null);

    const wishlistCount = props.wishlist_count || 0;

    const toggleWishlist = useCallback(async (productId, isWishlisted) => {
        setProcessingId(productId);

        try {
            const method = isWishlisted ? 'DELETE' : 'POST';
            const response = await fetch(`/wishlist/${productId}`, {
                method,
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Content-Type': 'application/json',
                },
            });

            const data = await response.json();

            if (data.wishlist_count !== undefined) {
                window.dispatchEvent(
                    new CustomEvent('wishlist-updated', { detail: { count: data.wishlist_count } })
                );
            }

            return data;
        } catch (error) {
            console.error('Wishlist toggle error:', error);
            return { error: 'Failed to update wishlist' };
        } finally {
            setProcessingId(null);
        }
    }, []);

    const removeFromWishlist = useCallback(async (productId) => {
        setProcessingId(productId);

        try {
            const response = await fetch(`/wishlist/${productId}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': getCsrfToken() },
            });

            const data = await response.json();

            if (data.wishlist_count !== undefined) {
                window.dispatchEvent(
                    new CustomEvent('wishlist-updated', { detail: { count: data.wishlist_count } })
                );
            }

            return data;
        } catch (error) {
            console.error('Remove from wishlist error:', error);
            return { error: 'Failed to remove from wishlist' };
        } finally {
            setProcessingId(null);
        }
    }, []);

    const moveToCart = useCallback(async (productId) => {
        setProcessingId(productId);

        try {
            const response = await fetch(`/wishlist/move-to-cart/${productId}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': getCsrfToken() },
            });

            const data = await response.json();

            if (data.cart_count !== undefined) {
                window.dispatchEvent(
                    new CustomEvent('cart-updated', { detail: { count: data.cart_count } })
                );
            }

            return data;
        } catch (error) {
            console.error('Move to cart error:', error);
            return { error: 'Failed to move to cart' };
        } finally {
            setProcessingId(null);
        }
    }, []);

    const moveAllToCart = useCallback(async () => {
        try {
            const response = await fetch('/wishlist/move-all-to-cart', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': getCsrfToken() },
            });

            const data = await response.json();

            if (data.cart_count !== undefined) {
                window.dispatchEvent(
                    new CustomEvent('cart-updated', { detail: { count: data.cart_count } })
                );
            }

            return data;
        } catch (error) {
            console.error('Move all to cart error:', error);
            return { error: 'Failed to move all to cart' };
        }
    }, []);

    const clearWishlist = useCallback(async () => {
        try {
            const response = await fetch('/wishlist/clear', {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': getCsrfToken() },
            });

            const data = await response.json();

            if (data.wishlist_count !== undefined) {
                window.dispatchEvent(
                    new CustomEvent('wishlist-updated', { detail: { count: data.wishlist_count } })
                );
            }

            return data;
        } catch (error) {
            console.error('Clear wishlist error:', error);
            return { error: 'Failed to clear wishlist' };
        }
    }, []);

    const navigateToWishlist = useCallback(() => {
        router.get('/wishlist');
    }, []);

    return {
        wishlistCount,
        processingId,
        toggleWishlist,
        removeFromWishlist,
        moveToCart,
        moveAllToCart,
        clearWishlist,
        navigateToWishlist,
    };
}
