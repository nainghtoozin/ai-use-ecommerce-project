import { useState, useEffect } from 'react';
import { usePage, Link } from '@inertiajs/react';
import { Crown } from 'lucide-react';

export default function FlashMessages() {
    const { flash, auth } = usePage().props;
    const [toasts, setToasts] = useState([]);
    const [locked, setLocked] = useState(null);

    useEffect(() => {
        if (flash?.feature_locked?.feature) {
            setLocked(flash.feature_locked);
            return;
        }

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

    if (locked) {
        const currentPlan = auth?.user?.subscription?.plan_name || 'Free';
        return (
            <div className="fixed inset-0 z-[100] bg-black/50 flex items-center justify-center p-4">
                <div className="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full text-center">
                    <div className="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <Crown className="w-8 h-8 text-amber-600" />
                    </div>
                    <h2 className="text-xl font-semibold text-gray-900 mb-2">Feature Unavailable</h2>
                    <p className="text-gray-600 mb-6">
                        <span className="font-medium">{locked.feature}</span> is not included in your current plan.
                    </p>
                    <div className="bg-gray-50 rounded-lg p-4 mb-6 space-y-2 text-sm">
                        <div className="flex justify-between">
                            <span className="text-gray-500">Feature</span>
                            <span className="font-medium text-gray-900">{locked.feature}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-500">Current Plan</span>
                            <span className="font-medium text-gray-900">{currentPlan}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-500">Required Plan</span>
                            <span className="font-medium text-blue-600">{locked.required_plan}</span>
                        </div>
                    </div>
                    <Link
                        href="/admin/billing"
                        className="inline-flex items-center justify-center px-6 py-3 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        <Crown className="w-4 h-4 mr-2" />
                        Upgrade to {locked.required_plan}
                    </Link>
                </div>
            </div>
        );
    }

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