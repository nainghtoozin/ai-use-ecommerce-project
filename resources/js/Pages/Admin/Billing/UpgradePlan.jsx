import { useState, useMemo } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatusBadge from '@/Components/Billing/StatusBadge';
import PlanFeatureMatrix from '@/Components/Billing/PlanFeatureMatrix';
import UpgradeDialog from '@/Components/Billing/UpgradeDialog';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';
import { X, Sparkles, TrendingUp, Lightbulb, Star, Zap, HelpCircle, ShieldCheck } from 'lucide-react';
import PlanFeatureList from '@/Components/Billing/PlanFeatureList';

function formatBytes(v) {
    if (v === null || v === undefined) return null;
    if (v >= 1024) return (v / 1024).toFixed(1) + ' GB';
    return v + ' MB';
}

const limitLabels = {
    product_limit: 'Products',
    staff_limit: 'Staff Accounts',
    storage_limit: 'Storage',
    orders_monthly_limit: 'Monthly Orders',
    coupon_limit: 'Coupons',
    promotion_limit: 'Promotions',
    flash_sale_limit: 'Flash Sales',
};

const planMeta = {
    free: { audience: 'For small stores just getting started', badge: null, color: 'gray' },
    starter: { audience: 'For growing businesses ready to scale', badge: 'most_popular', color: 'blue' },
    business: { audience: 'For established stores needing full power', badge: 'best_value', color: 'purple' },
};

function UpgradeRecommendations({ usage, plans }) {
    const recs = useMemo(() => {
        if (!usage || !plans) return [];
        const result = [];
        const checks = [
            { key: 'product_limit', threshold: 80, slug: 'starter', label: 'product limit' },
            { key: 'storage_limit', threshold: 80, slug: 'business', label: 'storage' },
            { key: 'staff_limit', threshold: 80, slug: 'starter', label: 'staff accounts' },
            { key: 'orders_monthly_limit', threshold: 80, slug: 'starter', label: 'monthly orders' },
        ];
        for (const check of checks) {
            const u = usage[check.key];
            if (u && !u.is_unlimited && u.limit > 0 && u.percent >= check.threshold) {
                const target = plans.find(p => p.slug === check.slug);
                result.push({
                    message: `Your ${check.label} is almost reached (${u.percent}%).`,
                    target: target?.name || check.slug,
                    planSlug: check.slug,
                    percent: u.percent,
                });
            }
        }
        return result;
    }, [usage, plans]);

    if (recs.length === 0) return null;

    return (
        <div className="bg-gradient-to-r from-amber-50 to-orange-50 rounded-xl border border-amber-200 p-5">
            <div className="flex items-start gap-3">
                <div className="p-1.5 rounded-lg bg-amber-100 flex-shrink-0">
                    <Lightbulb className="w-5 h-5 text-amber-600" />
                </div>
                <div className="flex-1">
                    <p className="text-sm font-semibold text-amber-800 mb-2">Upgrade Recommendations</p>
                    <ul className="space-y-1.5">
                        {recs.map((r, i) => (
                            <li key={i} className="flex items-center gap-2 text-sm text-amber-700">
                                <TrendingUp className="w-3.5 h-3.5 text-amber-500 flex-shrink-0" />
                                <span>{r.message} <span className="font-semibold">Upgrade to {r.target}</span></span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </div>
    );
}

function PlanCard({ plan, isRecommended, onUpgradeClick, allFeatureDefs, featureCategories }) {
    const pc = getPlatformCurrencyConfig(usePage().props.platform_setting);
    const price = plan.monthly_price;
    const isCurrent = plan.is_current;
    const meta = planMeta[plan.slug] || { audience: '', badge: null, color: 'gray' };
    const savingsPct = plan.yearly_savings_percent;
    const yearlySavings = plan.monthly_price && plan.yearly_price
        ? (parseFloat(plan.monthly_price) * 12) - parseFloat(plan.yearly_price)
        : 0;
    const hasSavings = yearlySavings > 0;

    const badge = (() => {
        if (isCurrent) return { label: 'Current Plan', classes: 'bg-blue-600 text-white' };
        if (isRecommended) return { label: 'Recommended', classes: 'bg-emerald-600 text-white' };
        if (meta.badge === 'most_popular') return { label: 'Most Popular', classes: 'bg-blue-600 text-white' };
        if (meta.badge === 'best_value') return { label: 'Best Value', classes: 'bg-purple-600 text-white' };
        if (hasSavings) return { label: `Save ${formatCurrency(yearlySavings, pc)}/yr (${savingsPct}% off)`, classes: 'bg-emerald-100 text-emerald-700' };
        return null;
    })();

    const borderClass = isCurrent ? 'border-blue-500 ring-2 ring-blue-500/20'
        : isRecommended ? 'border-emerald-400 ring-2 ring-emerald-400/20'
        : meta.badge === 'best_value' ? 'border-purple-400 ring-2 ring-purple-400/20'
        : 'border-gray-200 hover:border-gray-300';

    return (
        <div className={`relative rounded-2xl border-2 p-6 flex flex-col transition-all duration-200 bg-white ${borderClass} hover:shadow-lg`} role="region" aria-label={`${plan.name} plan`}>
            {badge && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2 z-10">
                    <span className={`px-3 py-1 text-xs font-semibold rounded-full shadow-sm ${badge.classes}`}>{badge.label}</span>
                </div>
            )}

            <div className="mb-5 mt-1">
                <div className="flex items-center gap-2 mb-1">
                    <h3 className={`text-xl font-bold ${isCurrent ? 'text-blue-700' : 'text-gray-900'}`}>{plan.name}</h3>
                    {meta.badge === 'best_value' && !isCurrent && <Sparkles className="w-4 h-4 text-purple-400" />}
                </div>
                <p className="text-sm text-gray-500">{meta.audience}</p>
                <div className="mt-4 flex items-baseline gap-1">
                    <span className="text-4xl font-extrabold text-gray-900">
                        {price === 0 ? 'Free' : price !== null ? formatCurrency(price, pc) : '—'}
                    </span>
                    {price !== null && price > 0 && (
                        <span className="text-sm text-gray-400">/month</span>
                    )}
                </div>
                {!isCurrent && hasSavings && (
                    <p className="text-xs text-emerald-600 font-medium mt-1">
                        {formatCurrency(plan.yearly_price, pc)}/year — Save {savingsPct}%
                    </p>
                )}
            </div>

            <div className="flex-1 mb-6">
                <PlanFeatureList plan={plan} allFeatureDefs={allFeatureDefs || []} featureCategories={featureCategories || []} />
            </div>

            <button
                type="button"
                onClick={() => onUpgradeClick(plan)}
                disabled={isCurrent}
                aria-label={isCurrent ? `You are on the ${plan.name} plan` : `Upgrade to ${plan.name}`}
                className={`w-full py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 ${
                    isCurrent
                        ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                        : isRecommended
                            ? 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500 shadow-sm hover:shadow-md'
                            : meta.badge === 'best_value'
                                ? 'bg-purple-600 text-white hover:bg-purple-700 focus:ring-purple-500 shadow-sm hover:shadow-md'
                                : 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 shadow-sm hover:shadow-md'
                }`}
            >
                {isCurrent ? 'Current Plan' : isRecommended ? 'Upgrade — Recommended' : 'Upgrade'}
            </button>
        </div>
    );
}

const comingSoonKeys = ['gift_cards', 'loyalty_points', 'referral_system'];

export default function AdminBillingUpgradePlan({ currentPlan, subscription, plans, usage, allFeatureDefs, featureCategories }) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogTarget, setDialogTarget] = useState(null);
    const [dialogFeatureKey, setDialogFeatureKey] = useState(null);

    const recommendedSlug = useMemo(() => {
        if (!usage || !plans) return null;
        if (usage?.product_limit?.percent >= 80) return 'starter';
        if (usage?.staff_limit?.percent >= 80) return 'starter';
        if (usage?.storage_limit?.percent >= 80) return 'business';
        if (usage?.orders_monthly_limit?.percent >= 80) return 'starter';
        return null;
    }, [usage, plans]);

    const openUpgradeFlow = (plan) => {
        if (plan.is_current) return;
        setDialogTarget(plan);
        setDialogFeatureKey(null);
        setDialogOpen(true);
    };

    const handleLockedFeature = (featureKey, planSlug) => {
        const plan = plans?.find(p => p.slug === planSlug);
        if (plan && !plan.is_current) {
            const upgradeHint = allFeatureDefs?.find(f => f.key === featureKey)?.upgradeHint;
            const betterPlan = upgradeHint
                ? plans?.find(p => p.name === upgradeHint)
                : plans?.find(p => !p.is_current && p.slug !== 'free');
            setDialogTarget(betterPlan || plan);
            setDialogFeatureKey(featureKey);
            setDialogOpen(true);
        }
    };

    const handleStartUpgrade = () => {
        setDialogOpen(false);
    };

    return (
        <AdminLayout>
            <Head title="Plan Selection & Upgrade" />

            <div className="p-6 lg:p-8 space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Plan Selection & Upgrade</h1>
                        <p className="text-sm text-gray-500 mt-1">Compare plans and choose the right one for your business</p>
                    </div>
                </div>

                {subscription && (
                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="px-6 py-4 border-b border-gray-100">
                            <h3 className="text-base font-semibold text-gray-900">Current Subscription</h3>
                        </div>
                        <div className="p-6">
                            <div className="flex flex-wrap items-center gap-x-6 gap-y-3">
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-gray-400 uppercase tracking-wider">Plan</span>
                                    <span className="text-sm font-semibold text-gray-900">{subscription.plan?.name || 'N/A'}</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-gray-400 uppercase tracking-wider">Status</span>
                                    <StatusBadge status={subscription.status} size="sm" />
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-gray-400 uppercase tracking-wider">Billing</span>
                                    <span className="text-sm font-semibold text-gray-900 capitalize">{subscription.billing_interval || '—'}</span>
                                </div>
                                {subscription.on_trial && subscription.trial_ends_at && (
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs text-gray-400 uppercase tracking-wider">Trial Ends</span>
                                        <span className="text-sm font-semibold text-blue-600">{subscription.trial_ends_at}</span>
                                    </div>
                                )}
                                {subscription.expires_at && (
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs text-gray-400 uppercase tracking-wider">Expires</span>
                                        <span className="text-sm font-semibold text-gray-900">{subscription.expires_at}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {subscription && subscription.on_trial && subscription.trial_days_remaining > 0 && subscription.trial_days_remaining <= 7 && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div className="flex items-start gap-3">
                            <div className="p-1.5 rounded-lg bg-amber-100 flex-shrink-0">
                                <Zap className="w-4 h-4 text-amber-600" />
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-amber-800">Trial expires {subscription.trial_days_remaining === 1 ? 'today' : `in ${subscription.trial_days_remaining} days`}</p>
                                <p className="text-xs text-amber-600 mt-0.5">Choose a plan below to continue using all features after your trial ends.</p>
                            </div>
                        </div>
                    </div>
                )}

                <UpgradeRecommendations usage={usage} plans={plans} />

                {!plans || plans.length === 0 ? (
                    <div className="bg-white rounded-xl border border-gray-200 p-8 text-center">
                        <ShieldCheck className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <h3 className="text-base font-semibold text-gray-900 mb-1">No Plans Available</h3>
                        <p className="text-sm text-gray-500 max-w-md mx-auto">
                            There are no active plans available at the moment. Please contact support for assistance.
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {plans.map((plan) => (
                            <PlanCard
                                key={plan.slug}
                                plan={plan}
                                isRecommended={!plan.is_current && recommendedSlug === plan.slug}
                                onUpgradeClick={openUpgradeFlow}
                                allFeatureDefs={allFeatureDefs}
                                featureCategories={featureCategories}
                            />
                        ))}
                    </div>
                )}

                {plans && plans.length > 1 && featureCategories && (
                    <div>
                        <div className="flex items-center gap-2 mb-4">
                            <h2 className="text-base font-semibold text-gray-900">Full Plan Comparison</h2>
                            <span className="text-xs text-gray-400">See every feature across all plans</span>
                        </div>
                        <PlanFeatureMatrix
                            plans={plans}
                            featureCategories={featureCategories}
                            allFeatureDefs={allFeatureDefs}
                            onLockedFeatureClick={handleLockedFeature}
                        />
                    </div>
                )}

                {currentPlan && (
                    <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200 p-5">
                        <div className="flex items-start gap-3">
                            <div className="p-1.5 rounded-lg bg-blue-100 flex-shrink-0">
                                <HelpCircle className="w-5 h-5 text-blue-600" />
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-blue-800">What happens after clicking Upgrade?</p>
                                <p className="text-sm text-blue-700 mt-1">
                                    You will be guided through the upgrade process, including plan comparison and feature details.
                                    Payment submission will be available in a future update.
                                </p>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <UpgradeDialog
                isOpen={dialogOpen}
                onClose={() => setDialogOpen(false)}
                currentPlan={plans?.find(p => p.is_current) || currentPlan || null}
                targetPlan={dialogTarget}
                featureKey={dialogFeatureKey}
                allFeatureDefs={allFeatureDefs}
            />
        </AdminLayout>
    );
}
