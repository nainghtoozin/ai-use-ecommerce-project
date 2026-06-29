import { Check, X, Infinity, HelpCircle, Sparkles } from 'lucide-react';

function FeatureIcon({ enabled, isUnlimited, isComingSoon }) {
    if (isComingSoon) {
        return <HelpCircle className="w-4 h-4 text-gray-300" aria-label="Coming soon" />;
    }
    if (isUnlimited) {
        return <Infinity className="w-4 h-4 text-blue-500" aria-label="Unlimited" />;
    }
    if (enabled) {
        return <Check className="w-4 h-4 text-green-500" aria-label="Enabled" />;
    }
    return <X className="w-4 h-4 text-gray-300" aria-label="Not available" />;
}

function LimitValue({ value, isUnlimited }) {
    if (isUnlimited) {
        return <Infinity className="w-4 h-4 text-blue-500 inline" aria-label="Unlimited" />;
    }
    if (value === null || value === undefined) return '—';
    if (value === 0) return '0';
    return value.toLocaleString();
}

function StorageLabel(mb) {
    if (mb === null || mb === undefined) return null;
    if (mb >= 1024) return (mb / 1024).toFixed(1) + ' GB';
    return mb + ' MB';
}

const limitRows = [
    { key: 'product_limit', label: 'Products', format: (v, u) => <LimitValue value={v} isUnlimited={u} /> },
    { key: 'staff_limit', label: 'Staff Accounts', format: (v, u) => <LimitValue value={v} isUnlimited={u} /> },
    { key: 'storage_limit', label: 'Storage', format: (v, u) => u ? <Infinity className="w-4 h-4 text-blue-500 inline" /> : <>{StorageLabel(v) || '—'}</> },
    { key: 'orders_monthly_limit', label: 'Monthly Orders', format: (v, u) => <LimitValue value={v} isUnlimited={u} /> },
    { key: 'coupon_limit', label: 'Coupons', format: (v, u) => <LimitValue value={v} isUnlimited={u} /> },
    { key: 'promotion_limit', label: 'Promotions', format: (v, u) => <LimitValue value={v} isUnlimited={u} /> },
    { key: 'flash_sale_limit', label: 'Flash Sales', format: (v, u) => <LimitValue value={v} isUnlimited={u} /> },
];

export default function PlanFeatureMatrix({ plans, featureCategories, allFeatureDefs, onLockedFeatureClick }) {
    const handleLockedClick = (featureKey, planSlug) => {
        if (onLockedFeatureClick) {
            onLockedFeatureClick(featureKey, planSlug);
        }
    };

    const isLocked = (plan, featureKey) => {
        const feat = plan.features?.find(f => f.key === featureKey);
        return feat ? !feat.enabled : true;
    };

    const isComingSoon = (key) => {
        return ['gift_cards', 'loyalty_points', 'referral_system'].includes(key);
    };

    return (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-100">
                <h3 className="text-base font-semibold text-gray-900">Plan Comparison</h3>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full text-sm" role="table" aria-label="Plan feature comparison">
                    <thead>
                        <tr className="border-b border-gray-100">
                            <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-48" scope="col">Feature</th>
                            {plans.map(p => (
                                <th key={p.slug} className={`px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider ${p.is_current ? 'text-blue-600' : 'text-gray-500'}`} scope="col">
                                    <div>{p.name}</div>
                                    {p.is_current && <div className="text-[10px] text-blue-500 font-normal normal-case mt-0.5">Current</div>}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        <tr className="border-b border-gray-100 bg-gray-50/50">
                            <td colSpan={plans.length + 1} className="px-6 py-2">
                                <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Limits</span>
                            </td>
                        </tr>
                        {limitRows.map(({ key, label, format }) => (
                            <tr key={key} className="border-b border-gray-50 hover:bg-gray-50/50">
                                <td className="px-6 py-3 text-gray-700 font-medium">{label}</td>
                                {plans.map(p => {
                                    const val = p.limits?.[key];
                                    const unlimited = val === null;
                                    return (
                                        <td key={p.slug} className="px-4 py-3 text-center text-gray-600">
                                            {format(val, unlimited)}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}

                        {featureCategories.map(cat => (
                            <tbody key={cat.label}>
                                <tr className="border-b border-gray-100 bg-gray-50/50">
                                    <td colSpan={plans.length + 1} className="px-6 py-2">
                                        <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider">{cat.label}</span>
                                    </td>
                                </tr>
                                {cat.features?.map(feat => {
                                    const comingSoon = isComingSoon(feat.key);
                                    return (
                                        <tr key={feat.key} className="border-b border-gray-50 hover:bg-gray-50/50">
                                            <td className="px-6 py-3">
                                                <div className="flex items-center gap-2">
                                                    <span className={`text-gray-700 ${comingSoon ? 'text-gray-400' : ''}`}>
                                                        {feat.label}
                                                    </span>
                                                    {comingSoon && (
                                                        <span className="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-400 font-medium">
                                                            Soon
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            {plans.map(p => {
                                                const enabled = p.features?.find(f => f.key === feat.key)?.enabled ?? false;
                                                const locked = !enabled && !comingSoon;
                                                return (
                                                    <td key={p.slug} className="px-4 py-3 text-center">
                                                        {comingSoon ? (
                                                            <HelpCircle className="w-4 h-4 text-gray-300 mx-auto" aria-label="Coming soon" />
                                                        ) : (
                                                            <button
                                                                type="button"
                                                                onClick={() => locked ? handleLockedClick(feat.key, p.slug) : null}
                                                                className={`inline-flex items-center justify-center ${locked ? 'cursor-pointer group' : 'cursor-default'}`}
                                                                aria-label={locked ? `${feat.label} is locked on ${p.name}. Click to upgrade.` : `${feat.label} is enabled on ${p.name}`}
                                                                tabIndex={locked ? 0 : -1}
                                                            >
                                                                {enabled ? (
                                                                    <Check className="w-4 h-4 text-green-500" />
                                                                ) : (
                                                                    <span className="group-hover:scale-110 transition-transform">
                                                                        <X className="w-4 h-4 text-gray-300 group-hover:text-amber-400" />
                                                                    </span>
                                                                )}
                                                            </button>
                                                        )}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
