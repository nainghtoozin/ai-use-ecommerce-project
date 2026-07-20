import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';
import { ArrowLeft, ArrowRight, Check, AlertTriangle, Calendar, CreditCard, Zap } from 'lucide-react';

function InfoRow({ label, value }) {
    return (
        <div className="flex items-center justify-between py-2.5">
            <span className="text-sm text-gray-500">{label}</span>
            <span className="text-sm font-semibold text-gray-900">{value || '—'}</span>
        </div>
    );
}

export default function AdminBillingPlanChange({ currentPlan, targetPlan, subscription, proration }) {
    const pc = getPlatformCurrencyConfig(usePage().props.platform_setting);
    const [submitting, setSubmitting] = useState(false);
    const [billingInterval, setBillingInterval] = useState(proration?.interval || 'monthly');

    const isUpgrade = proration?.is_upgrade;
    const isDowngrade = proration?.is_downgrade;
    const hasFutureExpiry = subscription?.expires_at && new Date(subscription.expires_at) > new Date();

    const currentPrice = currentPlan?.[billingInterval === 'yearly' ? 'yearly_price' : 'monthly_price'];
    const targetPrice = targetPlan?.[billingInterval === 'yearly' ? 'yearly_price' : 'monthly_price'];

    const handleConfirm = () => {
        setSubmitting(true);
        router.post(adminUrl('/admin/billing/change-plan/execute'), {
            plan_id: targetPlan.id,
            billing_interval: billingInterval,
        }, {
            preserveScroll: true,
            onSuccess: () => setSubmitting(false),
            onError: () => setSubmitting(false),
        });
    };

    return (
        <AdminLayout>
            <Head title={`${isUpgrade ? 'Upgrade' : 'Downgrade'} to ${targetPlan?.name || ''}`} />

            <div className="p-6 lg:p-8 space-y-6 max-w-3xl mx-auto">
                <div className="flex items-center gap-4">
                    <button
                        onClick={() => router.get(adminUrl('/admin/billing/upgrade'))}
                        className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                    >
                        <ArrowLeft className="w-5 h-5 text-gray-500" />
                    </button>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">{isUpgrade ? 'Upgrade' : 'Downgrade'} Plan</h1>
                        <p className="text-sm text-gray-500 mt-1">Review the changes before confirming</p>
                    </div>
                </div>

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

                <div className="bg-white rounded-xl border border-gray-200">
                    <div className="px-6 py-4 border-b border-gray-100">
                        <h2 className="text-base font-semibold text-gray-900">Plan Comparison</h2>
                    </div>
                    <div className="p-6">
                        <div className="flex items-center justify-between gap-4">
                            <div className="flex-1 bg-gray-50 rounded-xl p-5 border border-gray-200">
                                <p className="text-xs text-gray-400 uppercase tracking-wider mb-1">Current</p>
                                <p className="text-lg font-bold text-gray-900">{currentPlan?.name || '—'}</p>
                                <p className="text-sm text-gray-500 mt-1">
                                    {formatCurrency(currentPrice, pc)}/{billingInterval === 'yearly' ? 'year' : 'mo'}
                                </p>
                            </div>
                            <div className="flex-shrink-0">
                                <ArrowRight className="w-6 h-6 text-gray-400" />
                            </div>
                            <div className="flex-1 bg-blue-50 rounded-xl p-5 border border-blue-200">
                                <p className="text-xs text-blue-400 uppercase tracking-wider mb-1">Target</p>
                                <p className="text-lg font-bold text-blue-700">{targetPlan?.name || '—'}</p>
                                <p className="text-sm text-blue-500 mt-1">
                                    {formatCurrency(targetPrice, pc)}/{billingInterval === 'yearly' ? 'year' : 'mo'}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-200">
                    <div className="px-6 py-4 border-b border-gray-100">
                        <h2 className="text-base font-semibold text-gray-900 flex items-center gap-2">
                            <CreditCard className="w-4 h-4 text-gray-400" /> Billing Summary
                        </h2>
                    </div>
                    <div className="px-6 py-4 divide-y divide-gray-50">
                        <InfoRow label="Billing Interval" value={
                            <select
                                value={billingInterval}
                                onChange={(e) => setBillingInterval(e.target.value)}
                                className="text-sm font-semibold text-gray-900 border border-gray-200 rounded-lg px-2 py-1 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            >
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        } />
                        <InfoRow label="Current Price" value={formatCurrency(proration?.current_price || 0, pc)} />
                        <InfoRow label="Target Price" value={formatCurrency(proration?.target_price || 0, pc)} />
                        <InfoRow label="Price Difference" value={
                            <span className={isUpgrade ? 'text-red-600' : 'text-emerald-600'}>
                                {isUpgrade ? '+' : '-'}{formatCurrency(Math.abs(proration?.price_difference || 0), pc)}
                            </span>
                        } />
                        {proration?.days_remaining > 0 && (
                            <InfoRow label="Days Remaining" value={`${proration.days_remaining} days`} />
                        )}
                        {proration?.credit_amount > 0 && (
                            <InfoRow label="Credit (Unused)" value={formatCurrency(proration.credit_amount, pc)} />
                        )}
                        {isUpgrade && proration?.total_due > 0 && (
                            <InfoRow label="Amount Due Now" value={
                                <span className="text-lg font-bold text-red-600">{formatCurrency(proration.total_due, pc)}</span>
                            } />
                        )}
                        {isUpgrade && (!proration?.total_due || proration.total_due <= 0) && (
                            <InfoRow label="Amount Due" value={<span className="text-emerald-600">No additional charge</span>} />
                        )}
                        {isDowngrade && (
                            <InfoRow label="Effective Date" value={
                                hasFutureExpiry
                                    ? <span className="text-amber-600 font-semibold">{subscription?.expires_at || 'End of billing period'}</span>
                                    : <span className="text-emerald-600">Immediate</span>
                            } />
                        )}
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-6">
                    <div className="flex items-start gap-3 mb-4">
                        <div className={`p-1.5 rounded-lg flex-shrink-0 ${isUpgrade ? 'bg-emerald-100' : 'bg-amber-100'}`}>
                            {isUpgrade
                                ? <Zap className="w-5 h-5 text-emerald-600" />
                                : <AlertTriangle className="w-5 h-5 text-amber-600" />
                            }
                        </div>
                        <div>
                            <p className="text-sm font-semibold text-gray-900">
                                {isUpgrade ? 'What happens next?' : 'Before you downgrade:'}
                            </p>
                            <ul className="mt-2 space-y-1.5">
                                {isUpgrade ? (
                                    <>
                                        {proration?.total_due > 0 && (
                                            <li className="text-sm text-gray-600 flex items-start gap-2">
                                                <Check className="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                                                You will be charged {formatCurrency(proration.total_due, pc)} for the remaining days
                                            </li>
                                        )}
                                        <li className="text-sm text-gray-600 flex items-start gap-2">
                                            <Check className="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                                            Your plan will change immediately to {targetPlan?.name}
                                        </li>
                                        <li className="text-sm text-gray-600 flex items-start gap-2">
                                            <Check className="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                                            All features and limits will be updated right away
                                        </li>
                                    </>
                                ) : (
                                    <>
                                        {hasFutureExpiry ? (
                                            <li className="text-sm text-gray-600 flex items-start gap-2">
                                                <Check className="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                                                The change will take effect on {subscription?.expires_at || 'the end of the billing period'}
                                            </li>
                                        ) : (
                                            <li className="text-sm text-gray-600 flex items-start gap-2">
                                                <Check className="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                                                Your plan will change immediately
                                            </li>
                                        )}
                                        <li className="text-sm text-gray-600 flex items-start gap-2">
                                            <Check className="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                                            Some features may become unavailable after the change
                                        </li>
                                        <li className="text-sm text-gray-600 flex items-start gap-2">
                                            <Check className="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                                            You can cancel the change anytime before it takes effect
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
                        className="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        onClick={handleConfirm}
                        disabled={submitting}
                        className={`flex-1 px-4 py-2.5 text-sm font-semibold text-white rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 ${
                            isUpgrade
                                ? 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500'
                                : 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500'
                        } disabled:opacity-50 disabled:cursor-not-allowed`}
                    >
                        {submitting ? 'Processing...' : isUpgrade ? `Confirm Upgrade to ${targetPlan?.name}` : `Confirm Downgrade to ${targetPlan?.name}`}
                    </button>
                </div>
            </div>
        </AdminLayout>
    );
}
