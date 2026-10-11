import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';
import { ArrowLeft, ArrowRight, Check, AlertTriangle, Calendar, CreditCard, Zap, Receipt } from 'lucide-react';

function InfoRow({ label, value }) {
    return (
        <div className="flex items-center justify-between py-2.5">
            <span className="text-sm text-gray-500 dark:text-gray-400">{label}</span>
            <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{value || '—'}</span>
        </div>
    );
}

export default function AdminBillingPlanChange({ currentPlan, targetPlan, subscription, proration, scheduledChange, pendingPayment, isTrial = false, trialEndsAt = null }) {
    const pc = getPlatformCurrencyConfig(usePage().props.platform_setting);
    const [submitting, setSubmitting] = useState(false);
    const [scheduledDecision, setScheduledDecision] = useState(null);

    const isTrialConversion = !!proration?.is_trial_conversion;
    const isUpgrade = proration?.is_upgrade;
    const isDowngrade = proration?.is_downgrade;
    const hasFutureExpiry = subscription?.expires_at && new Date(subscription.expires_at) > new Date();
    const hasScheduledChange = !!scheduledChange;

    const handleIntervalChange = (interval) => {
        router.post(adminUrl('/admin/billing/change-plan/preview'), {
            plan_id: targetPlan.id,
            billing_interval: interval,
        }, { preserveScroll: true, preserveState: true });
    };

    const handleConfirm = () => {
        if (isUpgrade && hasScheduledChange && !scheduledDecision) {
            return;
        }
        setSubmitting(true);

        const url = isUpgrade
            ? adminUrl('/admin/billing/change-plan/submit')
            : adminUrl('/admin/billing/change-plan/execute');

        router.post(url, {
            plan_id: targetPlan.id,
            billing_interval: proration?.interval || 'monthly',
            scheduled_change_action: isUpgrade ? (scheduledDecision || 'keep') : undefined,
        }, {
            preserveScroll: true,
            onSuccess: () => setSubmitting(false),
            onError: () => setSubmitting(false),
        });
    };

    const confirmDisabled = submitting || (isUpgrade && hasScheduledChange && !scheduledDecision);

    return (
        <AdminLayout>
            <Head title={isTrialConversion ? `Convert to ${targetPlan?.name || ''}` : `${isUpgrade ? 'Upgrade' : 'Downgrade'} to ${targetPlan?.name || ''}`} />

            <div className="p-6 lg:p-8 space-y-6 max-w-3xl mx-auto">
                <div className="flex items-center gap-4">
                    <button
                        onClick={() => router.get(adminUrl('/admin/billing/upgrade'))}
                        className="p-2 rounded-lg hover:bg-gray-100 dark:bg-gray-800 transition-colors"
                    >
                        <ArrowLeft className="w-5 h-5 text-gray-500 dark:text-gray-400" />
                    </button>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">{isTrialConversion ? 'Convert to Paid Plan' : isUpgrade ? 'Upgrade Plan' : 'Downgrade Plan'}</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Review the changes before confirming</p>
                    </div>
                </div>

                {isTrialConversion && (
                    <div className="rounded-xl border border-blue-200 bg-blue-50 p-5">
                        <div className="flex items-start gap-3">
                            <div className="p-1.5 rounded-lg bg-blue-100 flex-shrink-0">
                                <Zap className="w-5 h-5 text-blue-600" />
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-blue-800">Trial → Paid conversion</p>
                                <p className="text-sm text-blue-700 mt-1">
                                    Trials have no unused paid value, so no credit is applied.
                                    {trialEndsAt ? ` Your trial ends ${trialEndsAt}.` : ''} A full billing period of {targetPlan?.name} begins after admin approval.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {hasScheduledChange && isUpgrade && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-5">
                        <div className="flex items-start gap-3">
                            <div className="p-1.5 rounded-lg bg-amber-100 flex-shrink-0">
                                <AlertTriangle className="w-5 h-5 text-amber-600" />
                            </div>
                            <div className="flex-1">
                                <p className="text-sm font-semibold text-amber-800">Scheduled plan change detected</p>
                                <p className="text-sm text-amber-700 mt-1">
                                    You have a scheduled change to <strong>{scheduledChange?.name}</strong> on {scheduledChange?.effective_at}.
                                    Choose how to handle it before upgrading.
                                </p>
                                <div className="mt-3 space-y-2">
                                    <label className="flex items-start gap-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            name="scheduled_decision"
                                            value="cancel"
                                            checked={scheduledDecision === 'cancel'}
                                            onChange={() => setScheduledDecision('cancel')}
                                            className="mt-0.5"
                                        />
                                        <span className="text-sm text-amber-800">Cancel the scheduled change and upgrade now</span>
                                    </label>
                                    <label className="flex items-start gap-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            name="scheduled_decision"
                                            value="keep"
                                            checked={scheduledDecision === 'keep'}
                                            onChange={() => setScheduledDecision('keep')}
                                            className="mt-0.5"
                                        />
                                        <span className="text-sm text-amber-800">Keep the scheduled change (it will still apply at {scheduledChange?.effective_at})</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {isDowngrade && hasFutureExpiry && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-5">
                        <div className="flex items-start gap-3">
                            <div className="p-1.5 rounded-lg bg-amber-100 flex-shrink-0">
                                <Calendar className="w-5 h-5 text-amber-600" />
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-amber-800">Scheduled Downgrade</p>
                                <p className="text-sm text-amber-700 mt-1">
                                    Your plan will change to <strong>{targetPlan?.name}</strong> at the end of your current billing period ({subscription?.expires_at || 'N/A'}).
                                    You will continue to enjoy your current plan features until then.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {pendingPayment?.is_upgrade && (
                    <div className="rounded-xl border border-blue-200 bg-blue-50 p-5">
                        <div className="flex items-start gap-3">
                            <div className="p-1.5 rounded-lg bg-blue-100 flex-shrink-0">
                                <Receipt className="w-5 h-5 text-blue-600" />
                            </div>
                            <div className="flex-1">
                                <p className="text-sm font-semibold text-blue-800">Upgrade payment pending</p>
                                <p className="text-sm text-blue-700 mt-1">
                                    You already have a pending upgrade payment ({pendingPayment.reference_number}).
                                </p>
                                <button
                                    onClick={() => router.get(adminUrl('/admin/billing/payment'), { intent: pendingPayment.reference_number })}
                                    className="mt-2 text-sm font-semibold text-blue-700 underline"
                                >
                                    View pending payment
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                    <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Plan Comparison</h2>
                    </div>
                    <div className="p-6">
                        <div className="flex items-center justify-between gap-4">
                            <div className="flex-1 bg-gray-50 dark:bg-gray-950 rounded-xl p-5 border border-gray-200 dark:border-gray-800">
                                <p className="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Current</p>
                                <p className="text-lg font-bold text-gray-900 dark:text-gray-100">{currentPlan?.name || '—'}</p>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    {formatCurrency(proration?.current_price || 0, pc)}/{proration?.interval === 'yearly' ? 'year' : 'mo'}
                                </p>
                            </div>
                            <div className="flex-shrink-0">
                                <ArrowRight className="w-6 h-6 text-gray-400 dark:text-gray-500" />
                            </div>
                            <div className="flex-1 bg-blue-50 rounded-xl p-5 border border-blue-200">
                                <p className="text-xs text-blue-400 uppercase tracking-wider mb-1">Target</p>
                                <p className="text-lg font-bold text-blue-700">{targetPlan?.name || '—'}</p>
                                <p className="text-sm text-blue-500 mt-1">
                                    {formatCurrency(proration?.target_price || 0, pc)}/{proration?.interval === 'yearly' ? 'year' : 'mo'}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                    <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <CreditCard className="w-4 h-4 text-gray-400 dark:text-gray-500" /> {isTrialConversion ? 'Trial Conversion Summary' : isUpgrade ? 'Prorated Upgrade Summary' : 'Billing Summary'}
                        </h2>
                    </div>
                    <div className="px-6 py-4 divide-y divide-gray-50">
                        <InfoRow label="Billing Interval" value={
                            <select
                                value={proration?.interval || 'monthly'}
                                onChange={(e) => handleIntervalChange(e.target.value)}
                                className="text-sm font-semibold text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-800 rounded-lg px-2 py-1 bg-white dark:bg-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            >
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        } />
                        <InfoRow label="Current Plan" value={currentPlan?.name || '—'} />
                        <InfoRow label={isTrialConversion ? 'Trial Ends' : 'Current Expiry'} value={subscription?.expires_at || 'N/A'} />

                        {isTrialConversion && (
                            <>
                                <InfoRow label="Unused trial credit" value={<span className="text-gray-500">None (trial)</span>} />
                                <InfoRow label={`${targetPlan?.name} price (${proration?.interval === 'yearly' ? 'yearly' : 'monthly'})`} value={formatCurrency(proration?.target_prorated || 0, pc)} />
                                <InfoRow label="Amount Due Now" value={
                                    <span className="text-lg font-bold text-red-600">{formatCurrency(proration?.amount_due || 0, pc)}</span>
                                } />
                                <InfoRow label="Effective Date" value={
                                    <span className="text-emerald-600 font-semibold">After payment approval (full billing period)</span>
                                } />
                            </>
                        )}

                        {isUpgrade && !isTrialConversion && (
                            <>
                                <InfoRow
                                    label="Remaining in period"
                                    value={`${proration?.remaining_days ?? proration?.days_remaining ?? 0} days (${Math.round((proration?.remaining_fraction || 0) * 100)}%)`}
                                />
                                <InfoRow label="Unused current-plan credit" value={
                                    <span className="text-emerald-600">−{formatCurrency(proration?.credit_amount || 0, pc)}</span>
                                } />
                                <InfoRow label={`${targetPlan?.name} prorated cost`} value={formatCurrency(proration?.target_prorated || 0, pc)} />
                                <InfoRow label="Amount Due Now" value={
                                    <span className="text-lg font-bold text-red-600">{formatCurrency(proration?.amount_due || 0, pc)}</span>
                                } />
                                <InfoRow label="Effective Date" value={
                                    <span className="text-emerald-600 font-semibold">{proration?.effective_date || 'Immediately after approval'}</span>
                                } />
                            </>
                        )}

                        {isDowngrade && (
                            <>
                                <InfoRow label="Target Plan" value={targetPlan?.name || '—'} />
                                <InfoRow label="Price Difference" value={
                                    <span className="text-emerald-600">−{formatCurrency(Math.abs(proration?.price_difference || 0), pc)}</span>
                                } />
                                <InfoRow label="Effective Date" value={
                                    hasFutureExpiry
                                        ? <span className="text-amber-600 font-semibold">{subscription?.expires_at || 'End of billing period'}</span>
                                        : <span className="text-emerald-600">Immediate</span>
                                } />
                            </>
                        )}
                    </div>
                </div>

                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
                    <div className="flex items-start gap-3">
                        <div className={`p-1.5 rounded-lg flex-shrink-0 ${isUpgrade ? 'bg-emerald-100' : 'bg-amber-100'}`}>
                            {isUpgrade
                                ? <Zap className="w-5 h-5 text-emerald-600" />
                                : <AlertTriangle className="w-5 h-5 text-amber-600" />
                            }
                        </div>
                        <div>
                            <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {isUpgrade ? 'What happens next?' : 'Before you downgrade:'}
                            </p>
                            <ul className="mt-2 space-y-1.5">
                                {isTrialConversion ? (
                                    <>
                                        <li className="text-sm text-gray-600 dark:text-gray-400 flex items-start gap-2">
                                            <Check className="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                                            You will submit a manual payment of {formatCurrency(proration?.amount_due || 0, pc)}
                                        </li>
                                        <li className="text-sm text-gray-600 dark:text-gray-400 flex items-start gap-2">
                                            <Check className="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                                            Your trial ends and a full billing period of {targetPlan?.name} starts after admin approval
                                        </li>
                                        <li className="text-sm text-gray-600 dark:text-gray-400 flex items-start gap-2">
                                            <Check className="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                                            No trial-to-paid credit is applied (trials have no paid value)
                                        </li>
                                    </>
                                ) : isUpgrade ? (
                                    <>
                                        <li className="text-sm text-gray-600 dark:text-gray-400 flex items-start gap-2">
                                            <Check className="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                                            You will submit a manual payment of {formatCurrency(proration?.amount_due || 0, pc)}
                                        </li>
                                        <li className="text-sm text-gray-600 dark:text-gray-400 flex items-start gap-2">
                                            <Check className="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                                            Your plan is upgraded only after an admin approves the payment
                                        </li>
                                        <li className="text-sm text-gray-600 dark:text-gray-400 flex items-start gap-2">
                                            <Check className="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                                            Your current expiry date ({subscription?.expires_at || 'unchanged'}) is preserved
                                        </li>
                                    </>
                                ) : (
                                    <>
                                        {hasFutureExpiry ? (
                                            <li className="text-sm text-gray-600 dark:text-gray-400 flex items-start gap-2">
                                                <Check className="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                                                The change will take effect on {subscription?.expires_at || 'the end of the billing period'}
                                            </li>
                                        ) : (
                                            <li className="text-sm text-gray-600 dark:text-gray-400 flex items-start gap-2">
                                                <Check className="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                                                Your plan will change immediately
                                            </li>
                                        )}
                                        <li className="text-sm text-gray-600 dark:text-gray-400 flex items-start gap-2">
                                            <Check className="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                                            Some features may become unavailable after the change
                                        </li>
                                    </>
                                )}
                            </ul>
                        </div>
                    </div>
                </div>

                <div className="flex gap-3">
                    <button
                        onClick={() => router.get(adminUrl('/admin/billing/upgrade'))}
                        className="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 rounded-lg hover:bg-gray-200 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        onClick={handleConfirm}
                        disabled={confirmDisabled}
                        className={`flex-1 px-4 py-2.5 text-sm font-semibold text-white rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 ${
                            isUpgrade
                                ? 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500'
                                : 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500'
                        } disabled:opacity-50 disabled:cursor-not-allowed`}
                    >
                        {submitting ? 'Processing...' : isUpgrade ? `Confirm & Pay ${formatCurrency(proration?.amount_due || 0, pc)}` : `Confirm Downgrade to ${targetPlan?.name}`}
                    </button>
                </div>
            </div>
        </AdminLayout>
    );
}
