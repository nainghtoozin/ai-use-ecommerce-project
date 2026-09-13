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
    
    const addToCart = useCallback(async (productId, quantity = 1, variantId = null) => {
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
                return { error: data.error || `Request failed with status ${response.status}` };
            }

            if (data.count !== undefined) {
                window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: data.count } }));
            }

            return data;
        } catch (error) {
            console.error('Add to cart error:', error);
            return { error: 'Failed to add to cart' };
        } finally {
            setAddingId(null);
            setLoading(false);
        }
    }, []);
    
    const updateQuantity = useCallback(async (productId, quantity) => {
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
                return { error: data.error || `Request failed with status ${response.status}` };
            }

            if (data.count !== undefined) {
                window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: data.count } }));
            }

            return data;
        } catch (error) {
            console.error('Update cart error:', error);
            return { error: 'Failed to update cart' };
        } finally {
            setLoading(false);
        }
    }, []);
    
    const removeItem = useCallback(async (productId) => {
        setLoading(true);
        
        try {
            const response = await fetch(cartUrl(`/cart/${productId}`), {
                method: 'DELETE',
                headers: csrfHeaders(),
            });
            
            const data = await parseResponse(response);
            
            if (!response.ok) {
                return { error: data.error || `Request failed with status ${response.status}` };
            }

            if (data.count !== undefined) {
                window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: data.count } }));
            }

            return data;
        } catch (error) {
            console.error('Remove from cart error:', error);
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
                return { error: data.error || `Request failed with status ${response.status}` };
            }

            window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: 0 } }));

            return data;
        } catch (error) {
            console.error('Clear cart error:', error);
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