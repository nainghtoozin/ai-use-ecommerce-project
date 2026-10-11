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

export default function AdminBillingIndex({ subscription, usage, plans, featureCategories, allFeatureDefs, auditLogs, pendingPayment, trialDays, renewal, cancellation, trial }) {
    const { can } = usePermission();
    const pc = getPlatformCurrencyConfig(usePage().props.platform_setting);

    const [billingInterval, setBillingInterval] = useState(subscription?.billing_interval || 'monthly');
    const [cancelOpen, setCancelOpen] = useState(false);
    const [cancelReason, setCancelReason] = useState('');
    const [cancelComment, setCancelComment] = useState('');
    const [cancelSubmitting, setCancelSubmitting] = useState(false);

    const currentPlan = plans?.find(p => p.is_current) || null;
    const recommendedSlug = getRecommendedSlug(usage, plans);

    const isFreePlan = currentPlan?.monthly_price === 0 && currentPlan?.yearly_price === 0;
    const onTrial = subscription?.on_trial || trial?.is_trial || false;
    const trialCanRenew = !!trial?.can_renew;
    const trialRemaining = trial?.renewals_remaining;
    const trialRenewalAllowed = trial?.renewal_allowed !== false;

    const renewalThresholdDays = renewal?.threshold_days ?? 7;
    const renewalDue = !!renewal?.due;
    const activePaid = !!renewal?.active_paid;
    const daysUntilExpiry = renewal?.days_until_expiry;
    const renewalPending = renewal?.pending || null;
    const isSuspended = subscription?.status === 'suspended';
    const isTrialExpired = !!trial?.trial_ended && !onTrial;

    // Authoritative backend eligibility: within the renewal threshold OR lapsed,
    // and no valid renewal request already pending. Never frontend-only.
    const renewalEligible = !!renewal?.can_renew;

    // Primary renewal action — only when due (<= threshold) or lapsed.
    const canRenewPlan = renewalEligible && can('billing.view');

    // Same eligibility governs the Manage Subscription renewal action.
    const canManageRenewal = renewalEligible && can('billing.view');

    const showRenew = canRenewPlan && can('billing.renew');

    const activeCancellation = cancellation?.active || null;
    const latestCancellation = cancellation?.latest || null;
    const canCancel = !!subscription && can('billing.manage') && !activeCancellation
        && ['active', 'trialing', 'past_due'].includes(subscription.status);

    const handleRenew = () => {
        router.post(adminUrl('/admin/billing/renew'), {}, { preserveScroll: true });
    };

    const handleRenewPlan = () => {
        router.get(adminUrl('/admin/billing/renew-plan'));
    };

    const submitCancellation = (e) => {
        e.preventDefault();
        if (!cancelReason) return;
        setCancelSubmitting(true);
        router.post(adminUrl('/admin/billing/cancel-request'), {
            reason: cancelReason,
            comment: cancelComment || null,
        }, {
            preserveScroll: true,
            onFinish: () => setCancelSubmitting(false),
            onSuccess: () => { setCancelOpen(false); setCancelReason(''); setCancelComment(''); },
        });
    };

    const withdrawCancellation = () => {
        if (window.confirm('Withdraw your pending cancellation request?')) {
            router.post(adminUrl('/admin/billing/cancel-request/withdraw'), {}, { preserveScroll: true });
        }
    };

    const scrollToPlans = () => {
        document.getElementById('plans')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const handleChoose = (plan, { interval }) => {
        router.post(adminUrl('/admin/billing/change-plan/preview'), { plan_id: plan.id, billing_interval: interval });
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
                            onClick={handleRenewPlan}
                            className="px-4 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 self-start sm:self-auto"
                        >
                            Renew Now
                        </button>
                    )}
                </div>

                {subscription && (
                    <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">Subscription Actions</p>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Renew, change, or cancel your subscription.
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {onTrial && trialCanRenew && (
                                    <button
                                        onClick={handleRenew}
                                        className="px-3 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors"
                                    >
                                        Extend Trial{trialRemaining != null ? ` (${trialRemaining} left)` : ''}
                                    </button>
                                )}
                                {onTrial && !trialCanRenew && (
                                    <button
                                        onClick={scrollToPlans}
                                        className="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
                                    >
                                        Choose Paid Plan
                                    </button>
                                )}
                                {!onTrial && canRenewPlan && (
                                    <button
                                        onClick={handleRenewPlan}
                                        className="px-3 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors"
                                    >
                                        Renew Current Plan
                                    </button>
                                )}
                                {!onTrial && !isSuspended && (
                                    <button
                                        onClick={scrollToPlans}
                                        className="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
                                    >
                                        Change Plan
                                    </button>
                                )}
                            </div>
                        </div>

                        {onTrial && !trialCanRenew && (
                            <p className="text-xs text-amber-600 dark:text-amber-400 mt-3">
                                {!trialRenewalAllowed
                                    ? 'Trial renewal is disabled by the platform. Choose a paid plan to keep using all features after your trial ends.'
                                    : 'Your trial renewal limit has been reached. Choose a paid plan to keep using all features after your trial ends.'}
                            </p>
                        )}

                        {onTrial && trialCanRenew && (
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-3">
                                Your trial can be extended
                                {trialRemaining != null ? ` up to ${trialRemaining} more time${trialRemaining !== 1 ? 's' : ''}` : ' (unlimited)'}
                                {trial?.trial_ends_at ? ` — current trial ends ${trial.trial_ends_at}.` : '.'}
                            </p>
                        )}

                        {!onTrial && activePaid && renewalDue && (
                            <p className="text-xs text-amber-600 dark:text-amber-400 mt-3">
                                Your subscription renews in {daysUntilExpiry} day{daysUntilExpiry !== 1 ? 's' : ''}
                                {subscription.expires_at ? ` (${subscription.expires_at})` : ''}. Renew within {renewalThresholdDays} days to avoid interruption.
                            </p>
                        )}

                        {renewal && (
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-3">
                                Renewal price: <span className="font-semibold text-gray-700 dark:text-gray-300">
                                    {formatCurrency(renewal.price, pc)}
                                </span> per {renewal.billing_interval || 'monthly'} cycle. Payment is collected manually and your plan extends only after admin approval.
                            </p>
                        )}
                    </div>
                )}

                {subscription && !onTrial && !isFreePlan && (
                    <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">Manage Subscription</p>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {renewalPending
                                        ? 'A renewal payment is awaiting review. Renewal stays locked until it is resolved.'
                                        : `Renew your plan, or request cancellation (effective at the end of your paid period). Renewal opens within ${renewalThresholdDays} days of expiry.`}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {renewalPending ? (
                                    <a
                                        href={adminUrl(`/admin/billing/payment?intent=${renewalPending.reference_number}`)}
                                        className="px-3 py-2 bg-white dark:bg-gray-900 text-blue-700 border border-blue-300 rounded-lg text-sm font-medium hover:bg-blue-50 transition-colors"
                                    >
                                        View Pending Renewal
                                    </a>
                                ) : canManageRenewal && (
                                    <button
                                        onClick={handleRenewPlan}
                                        className="px-3 py-2 bg-white dark:bg-gray-900 text-emerald-700 border border-emerald-300 rounded-lg text-sm font-medium hover:bg-emerald-50 transition-colors"
                                    >
                                        {renewalDue ? 'Renew Current Plan' : 'Renew Plan'}
                                    </button>
                                )}
                                {activeCancellation ? (
                                    <button
                                        onClick={withdrawCancellation}
                                        className="px-3 py-2 bg-white dark:bg-gray-900 text-amber-700 border border-amber-300 rounded-lg text-sm font-medium hover:bg-amber-50 transition-colors"
                                    >
                                        Withdraw Cancellation
                                    </button>
                                ) : canCancel && (
                                    <button
                                        onClick={() => setCancelOpen(true)}
                                        className="px-3 py-2 bg-white dark:bg-gray-900 text-red-600 border border-red-200 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors"
                                    >
                                        Cancel Subscription
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {activeCancellation && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                            <div className="flex-1">
                                <p className="text-sm font-semibold text-amber-800">Cancellation requested — pending review</p>
                                <p className="text-xs text-amber-700 mt-0.5">
                                    Reason: {activeCancellation.reason_label}
                                    {activeCancellation.comment ? ` — ${activeCancellation.comment}` : ''}
                                </p>
                                <p className="text-xs text-amber-600 mt-0.5">Your store stays active until the request is reviewed and the existing expiry is reached.</p>
                            </div>
                            <button
                                onClick={withdrawCancellation}
                                className="px-3 py-1.5 bg-white dark:bg-gray-900 text-amber-700 border border-amber-300 rounded-lg text-xs font-medium hover:bg-amber-100 transition-colors flex-shrink-0"
                            >
                                Withdraw Request
                            </button>
                        </div>
                    </div>
                )}

                {!activeCancellation && latestCancellation && latestCancellation.status !== 'pending' && (
                    <div className={`rounded-xl border p-4 ${latestCancellation.status === 'approved' ? 'border-red-200 bg-red-50' : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950'}`}>
                        <p className={`text-sm font-semibold ${latestCancellation.status === 'approved' ? 'text-red-800' : 'text-gray-700 dark:text-gray-300'}`}>
                            Cancellation request {latestCancellation.status}
                        </p>
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Reason: {latestCancellation.reason_label}
                            {latestCancellation.review_note ? ` — Review note: ${latestCancellation.review_note}` : ''}
                        </p>
                        {latestCancellation.status === 'approved' && latestCancellation.effective_at && (
                            <p className="text-xs text-red-700 mt-0.5">
                                Your subscription remains active until {latestCancellation.effective_at}, then it will not renew.
                            </p>
                        )}
                    </div>
                )}

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
                                    {trialCanRenew
                                        ? 'You can extend your trial or choose a paid plan to continue.'
                                        : 'Trial renewal is not available. Choose a paid plan to continue using all features.'}
                                </p>
                            </div>
                            {trialCanRenew ? (
                                <button
                                    onClick={handleRenew}
                                    className="px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 transition-colors flex-shrink-0"
                                >
                                    Extend Trial
                                </button>
                            ) : (
                                <button
                                    onClick={scrollToPlans}
                                    className="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition-colors flex-shrink-0"
                                >
                                    Choose Paid Plan
                                </button>
                            )}
                        </div>
                    </div>
                )}

                {subscription && isTrialExpired && !activePaid && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <p className="text-sm font-semibold text-amber-800">Your trial has ended</p>
                                <p className="text-xs text-amber-700 mt-0.5">
                                    {subscription.trial_ends_at ? `Your trial ended on ${subscription.trial_ends_at}. ` : ''}
                                    Choose a paid plan to continue using all features.
                                </p>
                            </div>
                            <button
                                onClick={scrollToPlans}
                                className="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition-colors flex-shrink-0"
                            >
                                Choose Paid Plan
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
                                    onClick={handleRenewPlan}
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
                        ? { label: 'Renew Now', tone: 'emerald', onClick: handleRenewPlan }
                        : subscription && !['suspended'].includes(subscription.status)
                            ? { label: 'View Plans', tone: 'blue', onClick: scrollToPlans }
                            : null}
                    secondaryLinks={[
                        { label: 'Payment history', href: adminUrl('/admin/billing/payment-history') },
                        { label: 'Invoices', href: adminUrl('/admin/billing/invoices') },
                    ]}
                />

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
                            trialDays={trialDays}
                        />
                    </div>
                )}

                {subscription && (
                    <ActivityTimeline logs={auditLogs} />
                )}
            </div>

            {cancelOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-black/50" onClick={() => setCancelOpen(false)} />
                    <div className="relative w-full max-w-md rounded-2xl bg-white dark:bg-gray-900 shadow-xl overflow-hidden">
                        <div className="px-5 pt-5 pb-3 border-b border-gray-100 dark:border-gray-800">
                            <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">Cancel Subscription</h3>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Your store continues until the request is reviewed and your existing expiry is reached.
                            </p>
                        </div>
                        <form onSubmit={submitCancellation} className="p-5 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Reason <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={cancelReason}
                                    onChange={(e) => setCancelReason(e.target.value)}
                                    className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm"
                                    required
                                >
                                    <option value="">Select a reason…</option>
                                    {Object.entries(cancellation?.reasons || {}).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Additional comment {cancelReason === 'other' && <span className="text-red-500">*</span>}
                                </label>
                                <textarea
                                    value={cancelComment}
                                    onChange={(e) => setCancelComment(e.target.value)}
                                    rows={3}
                                    required={cancelReason === 'other'}
                                    placeholder={cancelReason === 'other' ? 'Please explain…' : 'Optional'}
                                    className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm"
                                />
                            </div>
                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setCancelOpen(false)}
                                    className="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                                >
                                    Keep Subscription
                                </button>
                                <button
                                    type="submit"
                                    disabled={!cancelReason || cancelSubmitting}
                                    className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 disabled:opacity-50 transition-colors"
                                >
                                    Submit Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
