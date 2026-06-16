import { useState, useCallback } from 'react';
import { usePage } from '@inertiajs/react';

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
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify({ product_id: productId, quantity, variant_id: variantId }),
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Flash message will be handled by Inertia if needed
                window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: data.cart_count } }));
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
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify({ quantity }),
            });
            
            const data = await response.json();
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
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
            });
            
            const data = await response.json();
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
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
            });
            
            const data = await response.json();
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