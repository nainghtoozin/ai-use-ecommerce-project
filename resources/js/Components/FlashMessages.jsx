import { useState, useEffect } from 'react';
import { usePage } from '@inertiajs/react';

export default function FlashMessages() {
    const { flash } = usePage().props;
    const [toasts, setToasts] = useState([]);

    useEffect(() => {
        const newToasts = [];
        
        if (flash?.success) {
            newToasts.push({
                id: Date.now() + 'success',
                type: 'success',
                message: flash.success,
            });
        }
        
        if (flash?.error) {
            newToasts.push({
                id: Date.now() + 'error',
                type: 'error',
                message: flash.error,
            });
        }
        
        if (flash?.warning) {
            newToasts.push({
                id: Date.now() + 'warning',
                type: 'warning',
                message: flash.warning,
            });
        }

        if (newToasts.length > 0) {
            setToasts((prev) => [...prev, ...newToasts]);
        }
    }, [flash]);

    const removeToast = (id) => {
        setToasts((prev) => prev.filter((toast) => toast.id !== id));
    };

    if (toasts.length === 0) return null;

    return (
        <div className="fixed bottom-4 right-4 z-50 flex flex-col gap-3 max-w-sm w-full md:w-auto">
            {toasts.map((toast) => (
                <Toast key={toast.id} toast={toast} onClose={() => removeToast(toast.id)} />
            ))}
        </div>
    );
}

function Toast({ toast, onClose }) {
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

    const config = {
        success: {
            bg: 'bg-emerald-500',
            icon: 'bi-check-circle-fill',
            shadow: 'shadow-emerald-500/20',
        },
        error: {
            bg: 'bg-red-500',
            icon: 'bi-x-circle-fill',
            shadow: 'shadow-red-500/20',
        },
        warning: {
            bg: 'bg-amber-500',
            icon: 'bi-exclamation-triangle-fill',
            shadow: 'shadow-amber-500/20',
        },
    };

    const { bg, icon, shadow } = config[toast.type] || config.success;

    return (
        <div
            className={`
                ${bg} text-white px-4 py-3 rounded-xl shadow-lg flex items-start gap-3
                transform transition-all duration-300 ease-out
                ${shadow}
                ${isVisible && !isExiting ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0'}
            `}
        >
            <i className={`bi ${icon} text-lg mt-0.5 flex-shrink-0`}></i>
            <div className="flex-1 min-w-0">
                <p className="text-sm font-medium">{toast.message}</p>
            </div>
            <button
                onClick={handleClose}
                className="text-white/80 hover:text-white transition-colors flex-shrink-0 p-0.5"
            >
                <i className="bi bi-x-lg text-lg"></i>
            </button>
        </div>
    );
}