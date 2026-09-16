import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatusBadge from '@/Components/Billing/StatusBadge';
import UsageCard from '@/Components/Billing/UsageCard';
import ActivityTimeline from '@/Components/Billing/ActivityTimeline';
import CurrentPlanSummary from '@/Components/Billing/CurrentPlanSummary';
import PlanPicker, { getRecommendedSlug } from '@/Components/Billing/PlanPicker';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';
import { usePermission } from '@/Hooks/usePermission';


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

export default function AdminBillingIndex({ subscription, usage, plans, featureCategories, allFeatureDefs, auditLogs, pendingPayment }) {
    const { can } = usePermission();
    const pc = getPlatformCurrencyConfig(usePage().props.platform_setting);

    const [billingInterval, setBillingInterval] = useState(subscription?.billing_interval || 'monthly');

    const currentPlan = plans?.find(p => p.is_current) || null;
    const showRenew = subscription && ['expired', 'past_due', 'canceled'].includes(subscription.status) && can('billing.renew');
    const recommendedSlug = getRecommendedSlug(usage, plans);

    const handleRenew = () => {
        router.post(adminUrl('/admin/billing/renew'), {}, { preserveScroll: true });
    };

    const scrollToPlans = () => {
        document.getElementById('plans')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const handleChoose = (plan, { action, interval }) => {
        if (action === 'upgrade') {
            router.get(adminUrl(`/admin/billing/checkout/${plan.slug}`), { billing_cycle: interval });
        } else {
            router.post(adminUrl('/admin/billing/change-plan/preview'), { plan_id: plan.id, billing_interval: interval });
        }
    };

    return (
        <AdminLayout>
            <Head title="Billing & Subscription" />

            <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-6xl mx-auto">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Billing & Subscription</h1>
                            {subscription && <StatusBadge status={subscription.status} />}
                        </div>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage your subscription plan and billing</p>
                    </div>
                    {showRenew && (
                        <button
                            onClick={handleRenew}
                            className="px-4 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 self-start sm:self-auto"
                        >
                            Renew Now
                        </button>
                    )}
                </div>

                {subscription && subscription.on_trial && subscription.trial_days_remaining > 0 && (
                    <div className={`rounded-xl border p-4 ${subscription.trial_days_remaining <= 3 ? 'bg-amber-50 border-amber-200' : 'bg-blue-50 border-blue-200'}`}>
                        <div className="flex items-start gap-3">
                            <div className={`p-1.5 rounded-lg ${subscription.trial_days_remaining <= 3 ? 'bg-amber-100' : 'bg-blue-100'}`}>
                                <svg className={`w-4 h-4 ${subscription.trial_days_remaining <= 3 ? 'text-amber-600' : 'text-blue-600'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <div className="flex-1">
                                <p className={`text-sm font-semibold ${subscription.trial_days_remaining <= 3 ? 'text-amber-800' : 'text-blue-800'}`}>
                                    Trial Period — {subscription.trial_days_remaining} day{subscription.trial_days_remaining !== 1 ? 's' : ''} remaining
                                </p>
                                <p className={`text-xs mt-0.5 ${subscription.trial_days_remaining <= 3 ? 'text-amber-600' : 'text-blue-600'}`}>
                                    {subscription.trial_ends_at ? `Your trial ends on ${subscription.trial_ends_at}. ` : ''}
                                    Choose a plan below to continue using all features.
                                </p>
                            </div>
                            <button
                                onClick={scrollToPlans}
                                className="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition-colors flex-shrink-0"
                            >
                                View Plans
                            </button>
                        </div>
                    </div>
                )}

                {pendingPayment && (
                    <div className="rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <div className="flex items-start gap-3">
                            <div className="p-1.5 rounded-lg bg-blue-100 flex-shrink-0">
                                <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <div className="flex-1">
                                <p className="text-sm font-semibold text-blue-800">
                                    {pendingPayment.status === 'waiting_review' ? 'Payment pending approval' : 'Payment in progress'}
                                </p>
                                <p className="text-xs text-blue-600 mt-0.5">
                                    {pendingPayment.plan_name ? `${pendingPayment.plan_name} · ` : ''}{formatCurrency(pendingPayment.amount, pc)}{pendingPayment.reference_number ? ` · Ref ${pendingPayment.reference_number}` : ''}
                                </p>
                            </div>
                            <a
                                href={adminUrl(`/admin/billing/payment?intent=${pendingPayment.reference_number}`)}
                                className="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition-colors flex-shrink-0"
                            >
                                View Payment
                            </a>
                        </div>
                    </div>
                )}

                {subscription?.pending_plan && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div className="flex items-start gap-3">
                            <div className="p-1.5 rounded-lg bg-amber-100 flex-shrink-0">
                                <svg className="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                            <div className="flex-1">
                                <p className="text-sm font-semibold text-amber-800">Scheduled Plan Change</p>
                                <p className="text-xs text-amber-600 mt-0.5">
                                    Your plan will change to <strong>{subscription.pending_plan.name}</strong> on {subscription.pending_plan_effective_at}.
                                </p>
                            </div>
                            <button
                                onClick={() => router.post(adminUrl('/admin/billing/change-plan/cancel'))}
                                className="ml-auto px-3 py-1.5 bg-white dark:bg-gray-900 text-red-600 border border-red-200 rounded-lg text-xs font-medium hover:bg-red-50 transition-colors flex-shrink-0"
                            >
                                Cancel
                            </button>
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

                <CurrentPlanSummary
                    subscription={subscription}
                    primary={showRenew
                        ? { label: 'Renew Now', tone: 'emerald', onClick: handleRenew }
                        : subscription && !['suspended'].includes(subscription.status)
                            ? { label: 'View Plans', tone: 'blue', onClick: scrollToPlans }
                            : null}
                    secondaryLinks={[
                        { label: 'Payment history', href: adminUrl('/admin/billing/payment-history') },
                        { label: 'Invoices', href: adminUrl('/admin/billing/invoices') },
                    ]}
                />

                {showRenew && !subscription.extra_renewal_used_at && (
                    <p className="text-xs text-gray-500 dark:text-gray-400 -mt-3">
                        One-time free renewal available — renewing now requires no payment. After it is used, renewals follow the normal payment path.
                    </p>
                )}

                {subscription && !['expired', 'past_due', 'canceled', 'suspended'].includes(subscription.status) && (
                    <div>
                        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Usage & Limits</h2>
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

                {plans && plans.length > 0 && (
                    <div id="plans" className="scroll-mt-6">
                        <PlanPicker
                            plans={plans}
                            subscription={subscription}
                            currentPlan={currentPlan}
                            interval={billingInterval}
                            onIntervalChange={setBillingInterval}
                            onChoose={handleChoose}
                            recommendedSlug={recommendedSlug}
                            allFeatureDefs={allFeatureDefs}
                        />
                    </div>
                )}

                {subscription && (
                    <ActivityTimeline logs={auditLogs} />
                )}
            </div>
        </AdminLayout>
    );
}
