import { useState, useEffect, useCallback } from 'react';

function cartSlug() {
    if (typeof window === 'undefined') return null;
    const match = window.location.pathname.match(/^\/store\/([^/]+)\//);
    return match ? match[1] : null;
}

export default function CartToast() {
    const [toasts, setToasts] = useState([]);

    const removeToast = useCallback((id) => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
    }, []);

    useEffect(() => {
        const handler = (e) => {
            const detail = e.detail || {};
            const id = Date.now() + Math.random();
            const slug = detail.slug || cartSlug();
            const isError = detail.type === 'error';
            const toast = {
                id,
                type: isError ? 'error' : 'success',
                title: detail.title || detail.message || (isError ? 'Something went wrong.' : 'Added to Cart'),
                message: detail.title ? (detail.message || null) : null,
                name: detail.name || null,
                variantName: detail.variantName || null,
                image: detail.image || null,
                quantity: detail.quantity || 1,
                cartHref: isError ? null : (detail.cartHref !== undefined ? detail.cartHref : (slug ? `/store/${slug}/cart` : '/cart')),
                toastKey: detail.toastKey || null,
            };
            setToasts((prev) => {
                if (toast.toastKey) {
                    const idx = prev.findIndex((t) => t.toastKey === toast.toastKey);
                    if (idx !== -1) {
                        const next = prev.slice();
                        next[idx] = { ...toast, id: prev[idx].id };
                        return next;
                    }
                }
                return [...prev.slice(-2), toast];
            });
        };
        window.addEventListener('cart-toast', handler);
        return () => window.removeEventListener('cart-toast', handler);
    }, []);

    if (toasts.length === 0) return null;

    return (
        <div className="fixed bottom-4 right-4 left-4 sm:left-auto z-[90] flex flex-col gap-2.5 sm:w-80">
            <style>{`@keyframes cart-toast-check-pop { 0% { transform: scale(0.4); } 60% { transform: scale(1.12); } 100% { transform: scale(1); } } .cart-toast-check-pop { animation: cart-toast-check-pop 0.35s ease-out; } @media (prefers-reduced-motion: reduce) { .cart-toast-check-pop { animation: none; } }`}</style>
            {toasts.map((toast) => (
                <CartToastItem key={toast.id} toast={toast} onClose={() => removeToast(toast.id)} />
            ))}
        </div>
    );
}

function CartToastItem({ toast, onClose }) {
    const [isVisible, setIsVisible] = useState(false);
    const [isExiting, setIsExiting] = useState(false);

    useEffect(() => {
        setIsVisible(true);
        const dismissTimer = setTimeout(() => {
            setIsExiting(true);
            setTimeout(() => onClose(), 300);
        }, 4000);
        return () => clearTimeout(dismissTimer);
    }, [onClose]);

    const handleClose = () => {
        setIsExiting(true);
        setTimeout(() => onClose(), 300);
    };

    const isError = toast.type === 'error';
    const secondary = toast.variantName
        ? `${toast.variantName} · Qty ${toast.quantity}`
        : `${toast.quantity} item${toast.quantity !== 1 ? 's' : ''} added to your cart`;

    return (
        <div
            role="status"
            className={`bg-white dark:bg-gray-900 border rounded-xl shadow-lg px-4 py-3 transform transition-all duration-300 ease-out ${
                isError ? 'border-red-200 dark:border-red-900/50' : 'border-gray-200 dark:border-gray-700'
            } ${isVisible && !isExiting ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0'}`}
        >
            <div className="flex items-center gap-2.5">
                <span className={`cart-toast-check-pop w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 ${
                    isError ? 'bg-red-100 dark:bg-red-900/30' : 'bg-green-100 dark:bg-green-900/30'
                }`}>
                    {isError ? (
                        <svg className="w-3.5 h-3.5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12" /></svg>
                    ) : (
                        <svg className="w-3.5 h-3.5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" /></svg>
                    )}
                </span>
                <p className="flex-1 min-w-0 text-sm font-semibold text-gray-900 dark:text-gray-100">{toast.title}</p>
                <button onClick={handleClose} aria-label="Dismiss notification" className="flex-shrink-0 p-1.5 -m-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            {toast.message && (
                <p className="text-[13px] text-gray-600 dark:text-gray-300 mt-1.5 pl-[38px] leading-snug">{toast.message}</p>
            )}
            {!isError && (toast.name || toast.image) && (
                <div className="flex items-center gap-2.5 mt-2 pl-[38px]">
                    {toast.image && (
                        <img src={toast.image} alt="" aria-hidden="true" className="w-9 h-9 rounded-lg object-cover bg-gray-100 dark:bg-gray-800 flex-shrink-0" />
                    )}
                    <div className="min-w-0">
                        {toast.name && <p className="text-[13px] font-medium text-gray-800 dark:text-gray-200 truncate">{toast.name}</p>}
                        <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{secondary}</p>
                    </div>
                </div>
            )}
            {!isError && !toast.name && !toast.image && (
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1 pl-[38px]">{secondary}</p>
            )}
            {!isError && toast.cartHref && (
                <div className="mt-1.5 pl-[38px]">
                    <a href={toast.cartHref} aria-label="View cart" className="inline-flex items-center gap-1 text-xs font-semibold hover:opacity-80 transition-opacity" style={{ color: 'var(--theme-color, #3B82F6)' }}>
                        View Cart
                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M9 5l7 7-7 7" /></svg>
                    </a>
                </div>
            )}
        </div>
    );
}
