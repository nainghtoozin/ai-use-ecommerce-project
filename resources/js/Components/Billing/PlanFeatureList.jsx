import { useState } from 'react';
import { Check } from 'lucide-react';

function formatBytes(v) {
    if (v === null || v === undefined) return null;
    if (v >= 1024) return (v / 1024).toFixed(1) + ' GB';
    return v + ' MB';
}

function formatLimit(v) {
    if (v === null || v === undefined) return null;
    return v.toLocaleString();
}

const LIMIT_CONFIG = [
    { key: 'product_limit', label: 'Products' },
    { key: 'staff_limit', label: 'Staff' },
    { key: 'storage_limit', label: 'Storage', fmt: v => formatBytes(v) },
    { key: 'orders_monthly_limit', label: 'Monthly Orders' },
];

export default function PlanFeatureList({ plan, allFeatureDefs = [], featureCategories = [] }) {
    const [showAll, setShowAll] = useState(false);
    const features = plan?.features || [];
    const limits = plan?.limits || {};

    const enabledKeys = features.filter(f => f.enabled).map(f => f.key);

    const enabledWithLabels = enabledKeys.map(key => ({
        key,
        label: allFeatureDefs.find(d => d.key === key)?.label || key,
    }));

    const visibleFeatures = enabledWithLabels.slice(0, 4);
    const extraCount = enabledWithLabels.length - 4;

    const limitRows = LIMIT_CONFIG
        .map(cfg => ({ ...cfg, val: limits[cfg.key] }))
        .filter(r => r.val !== null);

    return (
        <div className="space-y-4">
            {limitRows.length > 0 && (
                <div className="space-y-1.5">
                    {limitRows.map(r => (
                        <div key={r.key} className="flex items-center justify-between text-xs">
                            <span className="text-gray-500">{r.label}</span>
                            <span className="font-medium text-gray-900">
                                {r.fmt ? r.fmt(r.val) : formatLimit(r.val)}
                            </span>
                        </div>
                    ))}
                </div>
            )}

            <ul className="space-y-2">
                {visibleFeatures.map(f => (
                    <li key={f.key} className="flex items-start gap-2 text-sm">
                        <Check className="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                        <span className="text-gray-600">{f.label}</span>
                    </li>
                ))}
            </ul>

            {extraCount > 0 && (
                <button
                    type="button"
                    onClick={() => setShowAll(!showAll)}
                    className="text-xs text-blue-600 hover:text-blue-700 font-medium focus:outline-none"
                >
                    {showAll ? 'Show less' : `+${extraCount} more features`}
                </button>
            )}

            {extraCount <= 0 && enabledKeys.length <= 4 && (
                <div className="space-y-1.5 pt-1">
                    <p className="text-xs font-medium text-gray-400">Perfect for getting started</p>
                    <p className="text-xs text-gray-400 leading-relaxed">
                        Try the platform risk-free. Upgrade anytime as your business grows.
                    </p>
                </div>
            )}

            {showAll && featureCategories.length > 0 && (
                <div className="pt-3 border-t border-gray-100 space-y-3">
                    {featureCategories.map(cat => {
                        const catFeatures = cat.features.filter(f => enabledKeys.includes(f.key));
                        if (catFeatures.length === 0) return null;
                        return (
                            <div key={cat.label}>
                                <h4 className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">{cat.label}</h4>
                                <ul className="space-y-1">
                                    {catFeatures.map(f => (
                                        <li key={f.key} className="flex items-start gap-2 text-xs">
                                            <Check className="w-3 h-3 text-green-500 flex-shrink-0 mt-0.5" />
                                            <span className="text-gray-600">{f.label}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
