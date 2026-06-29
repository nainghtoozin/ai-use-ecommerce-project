import { Check, X, Infinity, HelpCircle } from 'lucide-react';
import { Link, usePage } from '@inertiajs/react';

function FeatureIcon({ enabled, isUnlimited, isComingSoon }) {
    if (isComingSoon) {
        return <HelpCircle className="w-4 h-4 text-gray-300 mx-auto" aria-label="Coming soon" />;
    }
    if (isUnlimited) {
        return <Infinity className="w-4 h-4 text-blue-500 mx-auto" aria-label="Unlimited" />;
    }
    if (enabled) {
        return <Check className="w-4 h-4 text-green-500 mx-auto" aria-label="Enabled" />;
    }
    return <X className="w-4 h-4 text-gray-300 mx-auto" aria-label="Not available" />;
}

function LimitValue({ value, isUnlimited }) {
    if (isUnlimited) {
        return <span className="text-blue-500 font-medium">Unlimited</span>;
    }
    if (value === null || value === undefined) return <span className="text-gray-400">—</span>;
    if (value === 0) return <span className="text-gray-400">0</span>;
    return <span className="text-gray-700">{value.toLocaleString()}</span>;
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

export default function FeatureComparisonMatrix({ plans, featureCategories }) {
    const { auth } = usePage().props;
    const safePlans = Array.isArray(plans) ? plans.filter(Boolean) : [];

    if (safePlans.length === 0) return null;

    const safeFeatureCategories = Array.isArray(featureCategories) ? featureCategories : [];

    const isComingSoon = (key) => {
        return ['gift_cards', 'loyalty_points', 'referral_system'].includes(key);
    };

    return (
        <section className="py-16 sm:py-20 lg:py-24 bg-gray-50">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center max-w-2xl mx-auto mb-14">
                    <h2 className="text-3xl sm:text-4xl font-bold text-gray-900">
                        Compare Plans
                    </h2>
                    <p className="mt-4 text-gray-500 text-lg">
                        Find the perfect plan for your business.
                    </p>
                </div>

                <div className="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm" role="table" aria-label="Plan feature comparison">
                            <thead>
                                <tr className="border-b border-gray-100 bg-gray-50/50">
                                    <th className="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider w-48" scope="col">Feature</th>
                                    {safePlans.map(p => (
                                        <th key={p.slug} className="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider text-gray-500" scope="col">
                                            {p.name}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                <tr className="border-b border-gray-100">
                                    <td colSpan={safePlans.length + 1} className="px-6 py-3">
                                        <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Limits</span>
                                    </td>
                                </tr>
                                {limitRows.map(({ key, label, format }) => (
                                    <tr key={key} className="border-b border-gray-50 hover:bg-gray-50/50">
                                        <td className="px-6 py-3 text-gray-700 font-medium">{label}</td>
                                        {safePlans.map(p => {
                                            const limits = p.limits || {};
                                            const val = limits[key];
                                            const unlimited = val === null;
                                            return (
                                                <td key={p.slug} className="px-4 py-3 text-center text-gray-600">
                                                    {format(val, unlimited)}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                            {safeFeatureCategories.map(cat => {
                                if (!cat || typeof cat !== 'object') return null;
                                const catFeatures = Array.isArray(cat.features) ? cat.features : [];
                                return (
                                    <tbody key={cat.label || Math.random()}>
                                        <tr className="border-b border-gray-100 bg-gray-50/50">
                                            <td colSpan={safePlans.length + 1} className="px-6 py-3">
                                                <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider">{cat.label || ''}</span>
                                            </td>
                                        </tr>
                                        {catFeatures.map(feat => {
                                            if (!feat || typeof feat !== 'object') return null;
                                            const featKey = feat.key || '';
                                            const featLabel = feat.label || '';
                                            const comingSoon = isComingSoon(featKey);
                                            return (
                                                <tr key={featKey} className="border-b border-gray-50 hover:bg-gray-50/50">
                                                    <td className="px-6 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <span className={`text-gray-700 ${comingSoon ? 'text-gray-400' : ''}`}>
                                                                {featLabel}
                                                            </span>
                                                            {comingSoon && (
                                                                <span className="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-400 font-medium">
                                                                    Soon
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    {safePlans.map(p => {
                                                        const planFeatures = Array.isArray(p.features) ? p.features : [];
                                                        const featureEntry = planFeatures.find(f => f && f.key === featKey);
                                                        const enabled = featureEntry ? featureEntry.enabled : false;
                                                        return (
                                                            <td key={p.slug} className="px-4 py-3 text-center">
                                                                <FeatureIcon
                                                                    enabled={enabled}
                                                                    isUnlimited={false}
                                                                    isComingSoon={comingSoon}
                                                                />
                                                            </td>
                                                        );
                                                    })}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                );
                            })}
                        </table>
                    </div>
                </div>

                <div className="text-center mt-8">
                    {auth?.user ? (
                        <Link
                            href="/admin/billing"
                            className="inline-flex items-center gap-2 px-6 py-2.5 bg-gray-900 text-white font-medium text-sm rounded-lg hover:bg-gray-800 transition-colors"
                        >
                            View Full Billing Details
                        </Link>
                    ) : (
                        <Link
                            href="/create-store"
                            className="inline-flex items-center gap-2 px-6 py-2.5 bg-gray-900 text-white font-medium text-sm rounded-lg hover:bg-gray-800 transition-colors"
                        >
                            Start Your Free Trial
                        </Link>
                    )}
                </div>
            </div>
        </section>
    );
}
