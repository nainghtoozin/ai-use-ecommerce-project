import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatusBadge from '@/Components/Billing/StatusBadge';
import SubscriptionSummaryCard from '@/Components/Billing/SubscriptionSummaryCard';
import UsageCard from '@/Components/Billing/UsageCard';
import FeatureAvailability from '@/Components/Billing/FeatureAvailability';
import QuickActions from '@/Components/Billing/QuickActions';
import ActivityTimeline from '@/Components/Billing/ActivityTimeline';
import PlanCards from '@/Components/Billing/PlanCards';
import UpgradeDialog from '@/Components/Billing/UpgradeDialog';
import { adminUrl } from '@/Utils/adminUrl';
import { CURRENCY_SYMBOL } from '@/Utils/currency';

function formatBytes(v) {
    if (v === null || v === undefined) return null;
    if (v >= 1024) return (v / 1024).toFixed(1) + ' GB';
    return v + ' MB';
}

const limitRows = [
    { key: 'product_limit', label: 'Products' },
    { key: 'staff_limit', label: 'Staff Accounts' },
    { key: 'storage_limit', label: 'Storage', format: formatBytes },
    { key: 'orders_monthly_limit', label: 'Monthly Orders' },
    { key: 'coupon_limit', label: 'Coupons' },
    { key: 'promotion_limit', label: 'Promotions' },
    { key: 'flash_sale_limit', label: 'Flash Sales' },
];

export default function AdminBillingIndex({ subscription, usage, plans, featureCategories, allFeatureDefs, auditLogs }) {
    const { auth } = usePage().props;
    const permissions = auth?.user?.permissions || [];
    const can = (perm) => permissions.includes(perm);

    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogTarget, setDialogTarget] = useState(null);
    const [dialogFeatureKey, setDialogFeatureKey] = useState(null);

    const currentPlan = plans?.find(p => p.is_current) || null;

    const openUpgradeDialog = (plan) => {
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

    const handleRenew = () => {
        router.post(adminUrl('/admin/billing/renew'), {}, { preserveScroll: true });
    };

    const handleUpgrade = () => {
        window.location.href = adminUrl('/admin/billing/upgrade');
    };

    return (
        <AdminLayout>
            <Head title="Billing & Subscription" />

            <div className="p-6 lg:p-8 space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-gray-900">Billing & Subscription</h1>
                            {subscription && <StatusBadge status={subscription.status} />}
                        </div>
                        <p className="text-sm text-gray-500 mt-1">Manage your subscription plan, limits, and billing information</p>
                    </div>
                    <div className="flex items-center gap-2">
                        {currentPlan && currentPlan.slug !== 'free' && (
                            <button
                                onClick={handleUpgrade}
                                className="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                Upgrade Plan
                            </button>
                        )}
                        {subscription && ['expired', 'past_due', 'canceled'].includes(subscription.status) && can('billing.renew') && (
                            <button
                                onClick={handleRenew}
                                className="px-4 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            >
                                Renew Now
                            </button>
                        )}
                    </div>
                </div>

                {subscription && subscription.on_trial && subscription.trial_days_remaining > 0 && (
                    <div className={`rounded-xl border p-4 ${subscription.trial_days_remaining <= 3 ? 'bg-amber-50 border-amber-200' : 'bg-blue-50 border-blue-200'}`}>
                        <div className="flex items-start gap-3">
                            <div className={`p-1.5 rounded-lg ${subscription.trial_days_remaining <= 3 ? 'bg-amber-100' : 'bg-blue-100'}`}>
                                <svg className={`w-4 h-4 ${subscription.trial_days_remaining <= 3 ? 'text-amber-600' : 'text-blue-600'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <div>
                                <p className={`text-sm font-semibold ${subscription.trial_days_remaining <= 3 ? 'text-amber-800' : 'text-blue-800'}`}>
                                    Trial Period — {subscription.trial_days_remaining} day{subscription.trial_days_remaining !== 1 ? 's' : ''} remaining
                                </p>
                                <p className={`text-xs mt-0.5 ${subscription.trial_days_remaining <= 3 ? 'text-amber-600' : 'text-blue-600'}`}>
                                    {subscription.trial_ends_at ? `Your trial ends on ${subscription.trial_ends_at}. ` : ''}
                                    Upgrade to a paid plan to continue using all features.
                                </p>
                            </div>
                            {subscription.trial_days_remaining <= 3 && (
                                <button
                                    onClick={handleUpgrade}
                                    className="ml-auto px-3 py-1.5 bg-amber-600 text-white rounded-lg text-xs font-medium hover:bg-amber-700 transition-colors flex-shrink-0"
                                >
                                    Upgrade Now
                                </button>
                            )}
                        </div>
                    </div>
                )}

                {subscription && ['expired', 'past_due', 'canceled'].includes(subscription.status) && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4">
                        <div className="flex items-start gap-3">
                            <div className="p-1.5 rounded-lg bg-red-100">
                                <svg className="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
                            </div>
                            <div className="flex-1">
                                <p className="text-sm font-semibold text-red-800">
                                    {subscription.status === 'expired' && 'Your subscription has expired.'}
                                    {subscription.status === 'past_due' && 'Your payment is past due.'}
                                    {subscription.status === 'canceled' && 'Your subscription has been canceled.'}
                                </p>
                                {subscription.status === 'expired' && subscription.days_since_expiry > 0 && (
                                    <p className="text-xs text-red-600 mt-0.5">Expired {subscription.days_since_expiry} day{subscription.days_since_expiry > 1 ? 's' : ''} ago</p>
                                )}
                                <p className="text-xs text-red-600 mt-0.5">Renew your subscription to restore full access to your store.</p>
                            </div>
                            {can('billing.renew') && (
                                <button
                                    onClick={handleRenew}
                                    className="ml-auto px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs font-medium hover:bg-red-700 transition-colors flex-shrink-0"
                                >
                                    Renew Now
                                </button>
                            )}
                        </div>
                    </div>
                )}

                {subscription && subscription.status === 'suspended' && (
                    <div className="rounded-xl border border-yellow-200 bg-yellow-50 p-4">
                        <div className="flex items-start gap-3">
                            <div className="p-1.5 rounded-lg bg-yellow-100">
                                <svg className="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-yellow-800">Your subscription has been suspended.</p>
                                <p className="text-xs text-yellow-600 mt-0.5">Please contact support to resolve this issue.</p>
                            </div>
                        </div>
                    </div>
                )}

                <SubscriptionSummaryCard subscription={subscription} />

                {subscription && !['expired', 'past_due', 'canceled', 'suspended'].includes(subscription.status) && (
                    <div>
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-base font-semibold text-gray-900">Usage & Limits</h2>
                            {currentPlan && (
                                <span className="text-xs text-gray-400">{currentPlan.name} plan</span>
                            )}
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            {limitRows.map(({ key, label, format }) => {
                                const u = usage?.[key];
                                return (
                                    <UsageCard
                                        key={key}
                                        label={label}
                                        current={u?.current ?? 0}
                                        limit={u?.limit ?? null}
                                        isUnlimited={u?.is_unlimited ?? false}
                                        format={format}
                                    />
                                );
                            })}
                        </div>
                    </div>
                )}

                {subscription && !['expired', 'past_due', 'canceled', 'suspended'].includes(subscription.status) && featureCategories && (
                    <FeatureAvailability
                        featureCategories={featureCategories}
                        allFeatureDefs={allFeatureDefs}
                        currentPlan={currentPlan}
                    />
                )}

                <QuickActions subscription={subscription} onRenew={handleRenew} can={can} />

                {subscription && (
                    <ActivityTimeline logs={auditLogs} />
                )}

                {plans && plans.length > 1 && (
                    <div>
                        <PlanCards plans={plans} onUpgrade={openUpgradeDialog} />
                    </div>
                )}

            </div>

            <UpgradeDialog
                isOpen={dialogOpen}
                onClose={() => setDialogOpen(false)}
                currentPlan={currentPlan}
                targetPlan={dialogTarget}
                featureKey={dialogFeatureKey}
                allFeatureDefs={allFeatureDefs}
            />
        </AdminLayout>
    );
}
