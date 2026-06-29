import { useEffect, useRef } from 'react';
import { X, ArrowRight, Check } from 'lucide-react';
import { router } from '@inertiajs/react';
import { adminUrl } from '@/Utils/adminUrl';
import { CURRENCY_SYMBOL } from '@/Utils/currency';

export default function UpgradeDialog({ isOpen, onClose, currentPlan, targetPlan, featureKey, allFeatureDefs }) {
    const dialogRef = useRef(null);
    const previousFocusRef = useRef(null);

    useEffect(() => {
        if (isOpen) {
            previousFocusRef.current = document.activeElement;
            setTimeout(() => dialogRef.current?.focus(), 50);
        } else if (previousFocusRef.current) {
            previousFocusRef.current.focus();
        }
    }, [isOpen]);

    useEffect(() => {
        if (!isOpen) return;
        const handler = (e) => {
            if (e.key === 'Escape') onClose();
            if (e.key === 'Tab') {
                const focusable = dialogRef.current?.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );
                if (!focusable?.length) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (e.shiftKey && document.activeElement === first) { last.focus(); e.preventDefault(); }
                else if (!e.shiftKey && document.activeElement === last) { first.focus(); e.preventDefault(); }
            }
        };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    const featureLabel = featureKey
        ? allFeatureDefs?.find(f => f.key === featureKey)?.label || featureKey
        : null;

    const targetName = targetPlan?.name || (featureKey ? 'a higher-tier plan' : 'another plan');

    const handleUpgrade = () => {
        router.get(adminUrl('/admin/billing'), {}, { preserveState: false });
        onClose();
    };

    const diffFeatures = () => {
        if (!currentPlan || !targetPlan || !allFeatureDefs) return [];
        const currentKeys = currentPlan.features?.filter(f => f.enabled).map(f => f.key) || [];
        const targetKeys = targetPlan.features?.filter(f => f.enabled).map(f => f.key) || [];
        const gained = targetKeys.filter(k => !currentKeys.includes(k));
        return allFeatureDefs.filter(d => gained.includes(d.key)).slice(0, 5);
    };

    const gainedFeatures = diffFeatures();

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-label={`Upgrade to ${targetName}`}
        >
            <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />

            <div
                ref={dialogRef}
                tabIndex={-1}
                className="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto animate-slide-up focus:outline-none"
            >
                <button
                    type="button"
                    onClick={onClose}
                    className="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors z-10"
                    aria-label="Close dialog"
                >
                    <X className="w-5 h-5" />
                </button>

                <div className="px-6 pt-6 pb-4 border-b border-gray-100">
                    {featureLabel && (
                        <p className="text-xs font-medium text-amber-600 mb-2">Feature Unlock</p>
                    )}
                    <h2 className="text-xl font-bold text-gray-900">
                        Upgrade to {targetName}
                    </h2>
                    {featureLabel && (
                        <p className="text-sm text-gray-500 mt-1">
                            Unlock <strong>{featureLabel}</strong> and more.
                        </p>
                    )}
                </div>

                <div className="p-6 space-y-5">
                    <div className="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                        <div>
                            <p className="text-xs text-gray-400">Current</p>
                            <p className="text-base font-semibold text-gray-900">{currentPlan?.name || '—'}</p>
                        </div>
                        <ArrowRight className="w-5 h-5 text-gray-400 flex-shrink-0" />
                        <div className="text-right">
                            <p className="text-xs text-gray-400">Target</p>
                            <p className="text-base font-semibold text-blue-600">{targetName}</p>
                        </div>
                    </div>

                    {gainedFeatures.length > 0 && (
                        <div>
                            <p className="text-sm font-medium text-gray-700 mb-2">You will gain access to:</p>
                            <ul className="space-y-2">
                                {gainedFeatures.map(f => (
                                    <li key={f.key} className="flex items-start gap-2 text-sm">
                                        <Check className="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                                        <span className="text-gray-600">{f.label}</span>
                                    </li>
                                ))}
                            </ul>
                            {targetPlan && allFeatureDefs && gainedFeatures.length < (
                                targetPlan.features?.filter(f => f.enabled).length - (currentPlan?.features?.filter(f => f.enabled).length || 0)
                            ) && (
                                <p className="text-xs text-gray-400 mt-2">
                                    And more features available on the {targetName} plan.
                                </p>
                            )}
                        </div>
                    )}

                    {targetPlan && currentPlan && (
                        <div className="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                            <span className="text-sm text-gray-700">Monthly price</span>
                            {targetPlan.monthly_price !== null ? (
                                <span className="text-lg font-bold text-gray-900">
                                    {`${CURRENCY_SYMBOL}${targetPlan.monthly_price}`}
                                    <span className="text-sm font-normal text-gray-400">/month</span>
                                </span>
                            ) : (
                                <span className="text-sm text-gray-500">Contact sales</span>
                            )}
                        </div>
                    )}

                    <div className="flex gap-3 pt-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={handleUpgrade}
                            className="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            Upgrade Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
