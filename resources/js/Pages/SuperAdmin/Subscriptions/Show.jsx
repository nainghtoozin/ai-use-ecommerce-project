import { useState } from 'react';
import { Link, router, Head, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function ShowSubscription({ subscription, history, usage, plans, intervals, currentInterval }) {
    const { props } = usePage();
    const flash = props?.flash || {};
    const [showChangePlan, setShowChangePlan] = useState(false);
    const [showRenew, setShowRenew] = useState(false);
    const [showCancel, setShowCancel] = useState(false);
    const [renewDate, setRenewDate] = useState('');
    const [renewNotes, setRenewNotes] = useState('');
    const [cancelReason, setCancelReason] = useState('');
    const [changePlanId, setChangePlanId] = useState('');
    const [changeReason, setChangeReason] = useState('');
    const [changeBillingInterval, setChangeBillingInterval] = useState(currentInterval || 'monthly');
    const [processing, setProcessing] = useState(false);

    const downgradeWarnings = flash?.downgrade_warnings;

    const statusColors = {
        trialing: 'bg-blue-100 text-blue-800',
        active: 'bg-green-100 text-green-800',
        past_due: 'bg-yellow-100 text-yellow-800',
        canceled: 'bg-gray-100 text-gray-800',
        expired: 'bg-red-100 text-red-800',
    };

    const canChangePlan = ['active', 'trialing', 'past_due'].includes(subscription.status);
    const canRenew = ['active', 'past_due', 'canceled'].includes(subscription.status);
    const canCancel = ['active', 'trialing', 'past_due'].includes(subscription.status);
    const canSuspend = !['expired'].includes(subscription.status);
    const canAssignPlans = plans?.length > 0;

    function handleChangePlan(e) {
        e.preventDefault();
        if (!changePlanId) return;
        setProcessing(true);
        router.put(`/superadmin/subscriptions/${subscription.id}/change-plan`, {
            plan_id: changePlanId,
            billing_interval: changeBillingInterval,
            reason: changeReason,
        }, {
            preserveState: true,
            onSuccess: () => { setShowChangePlan(false); setChangePlanId(''); setChangeReason(''); setChangeBillingInterval(currentInterval || 'monthly'); setProcessing(false); },
            onError: () => setProcessing(false),
        });
    }

    function handleRenew(e) {
        e.preventDefault();
        if (!renewDate) return;
        setProcessing(true);
        router.post(`/superadmin/subscriptions/${subscription.id}/renew`, {
            expires_at: renewDate,
            notes: renewNotes,
        }, {
            preserveState: true,
            onSuccess: () => { setShowRenew(false); setRenewDate(''); setRenewNotes(''); setProcessing(false); },
            onError: () => setProcessing(false),
        });
    }

    function handleCancel(e) {
        e.preventDefault();
        setProcessing(true);
        router.post(`/superadmin/subscriptions/${subscription.id}/cancel`, {
            reason: cancelReason,
        }, {
            preserveState: true,
            onSuccess: () => { setShowCancel(false); setCancelReason(''); setProcessing(false); },
            onError: () => setProcessing(false),
        });
    }

    function handleSuspend() {
        if (!window.confirm('Suspend this subscription? The merchant will lose access immediately.')) return;
        setProcessing(true);
        router.post(`/superadmin/subscriptions/${subscription.id}/suspend`, {}, {
            preserveState: true,
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">
            Subscription #{subscription.id} — {subscription.tenant?.name}
        </h2>}>
            <Head title={`Subscription #${subscription.id}`} />

            <div className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                    {/* Downgrade warnings */}
                    {downgradeWarnings && downgradeWarnings.length > 0 && (
                        <div className="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg">
                            <div className="flex">
                                <div className="flex-shrink-0">⚠️</div>
                                <div className="ml-3">
                                    <p className="text-sm font-medium text-yellow-800">Downgrade Warning</p>
                                    <ul className="mt-1 text-sm text-yellow-700 list-disc list-inside">
                                        {downgradeWarnings.map((w, i) => <li key={i}>{w}</li>)}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Status + Actions */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div className="flex items-center gap-3">
                                <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${statusColors[subscription.status] || 'bg-gray-100 text-gray-800'}`}>
                                    {subscription.status}
                                </span>
                                <span className="text-sm text-gray-500">
                                    Plan: <strong>{subscription.plan?.name}</strong>
                                </span>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {canChangePlan && canAssignPlans && (
                                    <button
                                        onClick={() => setShowChangePlan(!showChangePlan)}
                                        className="px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                                    >
                                        Change Plan
                                    </button>
                                )}
                                {canRenew && (
                                    <button
                                        onClick={() => setShowRenew(!showRenew)}
                                        className="px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                                    >
                                        Renew
                                    </button>
                                )}
                                {canCancel && (
                                    <button
                                        onClick={() => setShowCancel(!showCancel)}
                                        className="px-3 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                )}
                                {canSuspend && (
                                    <button
                                        onClick={handleSuspend}
                                        disabled={processing}
                                        className="px-3 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50"
                                    >
                                        Suspend Now
                                    </button>
                                )}
                                <Link
                                    href="/superadmin/subscriptions"
                                    className="px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    Back
                                </Link>
                            </div>
                        </div>
                    </div>

                    {/* Change Plan Form */}
                    {showChangePlan && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Change Plan</h3>
                            <form onSubmit={handleChangePlan} className="space-y-4">
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">New Plan</label>
                                        <select
                                            value={changePlanId}
                                            onChange={(e) => setChangePlanId(e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                            required
                                        >
                                            <option value="">Select plan...</option>
                                            {plans.filter(p => p.id !== subscription.plan_id).map((plan) => (
                                                <option key={plan.id} value={plan.id}>
                                                    {plan.name} {plan.monthly_price ? `(monthly: ${plan.monthly_price})` : ''} {plan.yearly_price ? `/ yearly: ${plan.yearly_price}` : ''}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Billing Cycle</label>
                                        <select
                                            value={changeBillingInterval}
                                            onChange={(e) => setChangeBillingInterval(e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                        >
                                            {intervals?.map((interval) => (
                                                <option key={interval} value={interval}>
                                                    {interval === 'monthly' ? 'Monthly' : 'Yearly'}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Reason (optional)</label>
                                        <input
                                            type="text"
                                            value={changeReason}
                                            onChange={(e) => setChangeReason(e.target.value)}
                                            placeholder="e.g. Merchant requested upgrade"
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                        />
                                    </div>
                                </div>
                                <div className="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setShowChangePlan(false)}
                                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing || !changePlanId}
                                        className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        {processing ? 'Saving...' : 'Change Plan'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    )}

                    {/* Renew Form */}
                    {showRenew && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Renew Subscription</h3>
                            <form onSubmit={handleRenew} className="space-y-4">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">New Expiry Date</label>
                                        <input
                                            type="date"
                                            value={renewDate}
                                            onChange={(e) => setRenewDate(e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                                        <input
                                            type="text"
                                            value={renewNotes}
                                            onChange={(e) => setRenewNotes(e.target.value)}
                                            placeholder="e.g. Paid via bank transfer"
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                        />
                                    </div>
                                </div>
                                <div className="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setShowRenew(false)}
                                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing || !renewDate}
                                        className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
                                    >
                                        {processing ? 'Processing...' : 'Renew'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    )}

                    {/* Cancel Form */}
                    {showCancel && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Cancel Subscription</h3>
                            <div className="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4 rounded">
                                <p className="text-sm text-yellow-700">
                                    Merchant retains access until the current expiration date. No refunds are processed.
                                </p>
                            </div>
                            <form onSubmit={handleCancel} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Reason (optional)</label>
                                    <input
                                        type="text"
                                        value={cancelReason}
                                        onChange={(e) => setCancelReason(e.target.value)}
                                        placeholder="e.g. Merchant closing store"
                                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                    />
                                </div>
                                <div className="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setShowCancel(false)}
                                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                                    >
                                        Keep Active
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 disabled:opacity-50"
                                    >
                                        {processing ? 'Processing...' : 'Confirm Cancellation'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    )}

                    {/* Details */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">Subscription Details</h3>
                        <dl className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <dt className="text-sm text-gray-500">Tenant</dt>
                                <dd className="text-sm font-medium text-gray-900">
                                    <Link href={`/superadmin/tenants/${subscription.tenant_id}`} className="text-blue-600 hover:text-blue-900">
                                        {subscription.tenant?.name}
                                    </Link>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-gray-500">Plan</dt>
                                <dd className="text-sm font-medium text-gray-900">{subscription.plan?.name || '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-sm text-gray-500">Billing Cycle</dt>
                                <dd className="text-sm font-medium text-gray-900">
                                    {subscription.billing_interval === 'yearly' ? 'Yearly' : 'Monthly'}
                                    {subscription.plan?.yearly_price && subscription.billing_interval === 'yearly' && (
                                        <span className="text-xs text-green-600 ml-1">
                                            (saves {Math.round((1 - subscription.plan.yearly_price / (subscription.plan.monthly_price * 12)) * 100)}%)
                                        </span>
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-gray-500">Status</dt>
                                <dd className="text-sm">
                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColors[subscription.status] || 'bg-gray-100 text-gray-800'}`}>
                                        {subscription.status}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-gray-500">Started</dt>
                                <dd className="text-sm font-medium text-gray-900">
                                    {subscription.starts_at ? new Date(subscription.starts_at).toLocaleDateString() : '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-gray-500">Expires</dt>
                                <dd className="text-sm font-medium text-gray-900">
                                    {subscription.expires_at ? new Date(subscription.expires_at).toLocaleDateString() : 'Never'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-gray-500">Trial Ends</dt>
                                <dd className="text-sm font-medium text-gray-900">
                                    {subscription.trial_ends_at ? new Date(subscription.trial_ends_at).toLocaleDateString() : '—'}
                                </dd>
                            </div>
                            {subscription.cancelled_at && (
                                <div>
                                    <dt className="text-sm text-gray-500">Canceled</dt>
                                    <dd className="text-sm font-medium text-gray-900">
                                        {new Date(subscription.cancelled_at).toLocaleString()}
                                    </dd>
                                </div>
                            )}
                        </dl>

                        {subscription.notes && (
                            <div className="mt-4">
                                <dt className="text-sm text-gray-500 mb-1">Notes</dt>
                                <dd className="text-sm text-gray-700 bg-gray-50 rounded-lg p-3 whitespace-pre-wrap">{subscription.notes}</dd>
                            </div>
                        )}
                    </div>

                    {/* Usage vs Limits */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">Usage vs Plan Limits</h3>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="bg-gray-50 rounded-lg p-4">
                                <div className="flex justify-between items-center mb-2">
                                    <span className="text-sm text-gray-600">Products</span>
                                    <span className="text-sm font-medium">
                                        {usage.products}
                                        {subscription.plan?.product_limit !== null && subscription.plan?.product_limit !== undefined
                                            ? ` / ${subscription.plan.product_limit}`
                                            : ' / Unlimited'}
                                    </span>
                                </div>
                                {subscription.plan?.product_limit !== null && subscription.plan?.product_limit !== undefined && (
                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            className={`h-2 rounded-full ${usage.products > subscription.plan.product_limit ? 'bg-red-500' : 'bg-blue-500'}`}
                                            style={{ width: `${Math.min(100, (usage.products / subscription.plan.product_limit) * 100)}%` }}
                                        ></div>
                                    </div>
                                )}
                            </div>
                            <div className="bg-gray-50 rounded-lg p-4">
                                <div className="flex justify-between items-center mb-2">
                                    <span className="text-sm text-gray-600">Staff (Admins)</span>
                                    <span className="text-sm font-medium">
                                        {usage.staff}
                                        {subscription.plan?.staff_limit !== null && subscription.plan?.staff_limit !== undefined
                                            ? ` / ${subscription.plan.staff_limit}`
                                            : ' / Unlimited'}
                                    </span>
                                </div>
                                {subscription.plan?.staff_limit !== null && subscription.plan?.staff_limit !== undefined && (
                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            className={`h-2 rounded-full ${usage.staff > subscription.plan.staff_limit ? 'bg-red-500' : 'bg-blue-500'}`}
                                            style={{ width: `${Math.min(100, (usage.staff / subscription.plan.staff_limit) * 100)}%` }}
                                        ></div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* History */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">Subscription History ({history.length})</h3>
                        {history.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plan</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Started</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expired</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {history.map((h) => (
                                            <tr key={h.id} className={`hover:bg-gray-50 ${h.id === subscription.id ? 'bg-blue-50' : ''}`}>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {h.id === subscription.id
                                                        ? <span className="font-medium text-blue-600">#{h.id} (current)</span>
                                                        : `#${h.id}`
                                                    }
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{h.plan?.name || '—'}</td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColors[h.status] || 'bg-gray-100 text-gray-800'}`}>
                                                        {h.status}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {h.starts_at ? new Date(h.starts_at).toLocaleDateString() : '—'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {h.expires_at ? new Date(h.expires_at).toLocaleDateString() : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No history records.</p>
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
