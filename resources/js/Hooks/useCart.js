import { useState, useCallback } from 'react';
import { usePage } from '@inertiajs/react';
import { csrfHeaders, parseResponse } from '@/Utils/csrf';

function cartUrl(path) {
    if (typeof window === 'undefined') return path;
    const match = window.location.pathname.match(/^\/store\/([^/]+)\//);
    return match ? `/store/${match[1]}${path}` : path;
}

export function useCart() {
    const { props } = usePage();
    const [loading, setLoading] = useState(false);
    const [addingId, setAddingId] = useState(null);
    
    const cartCount = props.cart?.count || 0;
    
    const addToCart = useCallback(async (productId, quantity = 1, variantId = null, meta = null) => {
        setAddingId(productId);
        setLoading(true);
        
        try {
            const response = await fetch(cartUrl('/cart/add'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...csrfHeaders(),
                },
                body: JSON.stringify({ product_id: productId, quantity, variant_id: variantId }),
            });
            
            const data = await parseResponse(response);
            
            if (!response.ok) {
                const message = data.error || data.message || `Request failed with status ${response.status}`;
                window.dispatchEvent(new CustomEvent('cart-toast', { detail: { type: 'error', message } }));
                return { error: message };
            }

            if (data.count !== undefined) {
                window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: data.count } }));
            }

            window.dispatchEvent(new CustomEvent('cart-toast', { detail: {
                type: 'success',
                message: 'Added to Cart',
                name: meta?.name || null,
                variantName: meta?.variantName || null,
                image: meta?.image || null,
                quantity: quantity || 1,
            } }));

            return data;
        } catch (error) {
            console.error('Add to cart error:', error);
            window.dispatchEvent(new CustomEvent('cart-toast', { detail: { type: 'error', message: 'Failed to add to cart.' } }));
            return { error: 'Failed to add to cart' };
        } finally {
            setAddingId(null);
            setLoading(false);
        }
    }, []);
    
    const updateQuantity = useCallback(async (productId, quantity, meta = null) => {
        setLoading(true);
        
        try {
            const response = await fetch(cartUrl(`/cart/${productId}`), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    ...csrfHeaders(),
                },
                body: JSON.stringify({ quantity }),
            });
            
            const data = await parseResponse(response);
            
            if (!response.ok) {
                const message = data.error || data.message || `Request failed with status ${response.status}`;
                window.dispatchEvent(new CustomEvent('cart-toast', { detail: { type: 'error', title: 'Update Failed', message } }));
                return { error: message };
            }

            if (data.count !== undefined) {
                window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: data.count } }));
            }

            window.dispatchEvent(new CustomEvent('cart-toast', { detail: {
                type: 'success', title: 'Quantity Updated',
                message: meta?.message || 'Cart updated.',
                toastKey: `qty:${productId}`,
                cartHref: null,
            } }));

            return data;
        } catch (error) {
            console.error('Update cart error:', error);
            window.dispatchEvent(new CustomEvent('cart-toast', { detail: { type: 'error', title: 'Update Failed', message: 'Unable to update the cart. Please try again.' } }));
            return { error: 'Failed to update cart' };
        } finally {
            setLoading(false);
        }
    }, []);
    
    const removeItem = useCallback(async (productId, meta = null) => {
        setLoading(true);
        
        try {
            const response = await fetch(cartUrl(`/cart/${productId}`), {
                method: 'DELETE',
                headers: csrfHeaders(),
            });
            
            const data = await parseResponse(response);
            
            if (!response.ok) {
                const message = data.error || data.message || `Request failed with status ${response.status}`;
                window.dispatchEvent(new CustomEvent('cart-toast', { detail: { type: 'error', title: 'Remove Failed', message } }));
                return { error: message };
            }

            if (data.count !== undefined) {
                window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: data.count } }));
            }

            window.dispatchEvent(new CustomEvent('cart-toast', { detail: {
                type: 'success', title: 'Item Removed',
                message: meta?.message || 'Item removed from your cart.',
                toastKey: 'cart-remove',
                cartHref: null,
            } }));

            return data;
        } catch (error) {
            console.error('Remove from cart error:', error);
            window.dispatchEvent(new CustomEvent('cart-toast', { detail: { type: 'error', title: 'Remove Failed', message: 'Unable to remove this item.' } }));
            return { error: 'Failed to remove item' };
        } finally {
            setLoading(false);
        }
    }, []);
    
    const clearCart = useCallback(async () => {
        setLoading(true);
        
        try {
            const response = await fetch(cartUrl('/cart/clear'), {
                method: 'DELETE',
                headers: csrfHeaders(),
            });
            
            const data = await parseResponse(response);
            
            if (!response.ok) {
                const message = data.error || data.message || `Request failed with status ${response.status}`;
                window.dispatchEvent(new CustomEvent('cart-toast', { detail: { type: 'error', title: 'Clear Cart Failed', message } }));
                return { error: message };
            }

            window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: 0 } }));

            window.dispatchEvent(new CustomEvent('cart-toast', { detail: {
                type: 'success', title: 'Cart Cleared',
                message: 'All items have been removed from your cart.',
                toastKey: 'cart-clear',
                cartHref: null,
            } }));

            return data;
        } catch (error) {
            console.error('Clear cart error:', error);
            window.dispatchEvent(new CustomEvent('cart-toast', { detail: { type: 'error', title: 'Clear Cart Failed', message: 'Unable to clear your cart.' } }));
            return { error: 'Failed to clear cart' };
        } finally {
            setLoading(false);
        }
    }, []);
    
    return {
        cartCount,
        loading,
        addingId,
        addToCart,
        updateQuantity,
        removeItem,
        clearCart,
    };
}