import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

const statusConfig = {
    active:    { label: 'Active',        bg: 'bg-emerald-50 border-emerald-200',      badge: 'bg-emerald-100 text-emerald-700',  icon: 'bi-check-circle-fill' },
    trialing:  { label: 'Trialing',      bg: 'bg-blue-50 border-blue-200',            badge: 'bg-blue-100 text-blue-700',        icon: 'bi-star-fill' },
    past_due:  { label: 'Past Due',      bg: 'bg-amber-50 border-amber-200',          badge: 'bg-amber-100 text-amber-700',      icon: 'bi-exclamation-triangle-fill' },
    expired:   { label: 'Expired',       bg: 'bg-red-50 border-red-200',              badge: 'bg-red-100 text-red-700',          icon: 'bi-x-circle-fill' },
    canceled:  { label: 'Canceled',      bg: 'bg-gray-50 border-gray-200',            badge: 'bg-gray-100 text-gray-700',        icon: 'bi-slash-circle-fill' },
    suspended: { label: 'Suspended',     bg: 'bg-yellow-50 border-yellow-200',        badge: 'bg-yellow-100 text-yellow-700',    icon: 'bi-pause-circle-fill' },
};

export default function AdminBillingIndex({ subscription, auditLogs }) {
    const { auth } = usePage().props;
    const permissions = auth?.user?.permissions || [];
    const can = (perm) => permissions.includes(perm);
    const cfg = statusConfig[subscription?.status] || statusConfig.expired;

    const formatMoney = (amount) => {
        if (amount === null || amount === undefined) return '—';
        return Number(amount).toLocaleString() + ' MMK';
    };

    const handleRenew = () => {
        router.post(adminUrl('/admin/billing/renew'), {}, {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout>
            <Head title="Billing" />

            <div className="p-6 lg:p-8 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Billing & Subscription</h1>
                        <p className="text-sm text-gray-500 mt-1">Manage your subscription plan and billing information</p>
                    </div>
                </div>

                {!subscription && (
                    <div className="bg-white rounded-xl border border-gray-200 p-8 text-center">
                        <i className="bi bi-credit-card text-4xl text-gray-300"></i>
                        <p className="text-gray-500 mt-2">No subscription found for your store.</p>
                        <p className="text-sm text-gray-400 mt-1">Please contact your account manager.</p>
                    </div>
                )}

                {subscription && (
                    <>
                        <div className={`rounded-xl border p-6 ${cfg.bg}`}>
                            <div className="flex items-center gap-4">
                                <div className="p-3 rounded-full bg-white/80">
                                    <i className={`bi ${cfg.icon} text-2xl`}></i>
                                </div>
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <h2 className="text-lg font-semibold text-gray-900">Subscription Status</h2>
                                        <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${cfg.badge}`}>
                                            {cfg.label}
                                        </span>
                                    </div>
                                    <p className="text-sm text-gray-600 mt-1">
                                        {subscription.status === 'expired' && (
                                            subscription.days_since_expiry > 0
                                                ? `Expired ${subscription.days_since_expiry} day${subscription.days_since_expiry > 1 ? 's' : ''} ago`
                                                : 'Expired today'
                                        )}
                                        {subscription.status === 'active' && (
                                            subscription.days_until_expiry > 0
                                                ? `Expires in ${subscription.days_until_expiry} day${subscription.days_until_expiry > 1 ? 's' : ''}`
                                                : 'Active'
                                        )}
                                        {subscription.status === 'trialing' && 'Trial period'}
                                        {subscription.status === 'past_due' && 'Payment is past due'}
                                        {subscription.status === 'canceled' && 'No longer active'}
                                        {subscription.status === 'suspended' && 'Access suspended'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div className="bg-white rounded-xl border border-gray-200">
                                <div className="px-6 py-4 border-b border-gray-100">
                                    <h3 className="text-base font-semibold text-gray-900">Plan Details</h3>
                                </div>
                                <div className="p-6 space-y-4">
                                    <div>
                                        <p className="text-sm text-gray-500">Plan</p>
                                        <p className="text-lg font-semibold text-gray-900">{subscription.plan?.name || '—'}</p>
                                        {subscription.plan?.description && (
                                            <p className="text-sm text-gray-500 mt-0.5">{subscription.plan.description}</p>
                                        )}
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-500">Price</p>
                                        <p className="text-lg font-semibold text-gray-900">
                                            {formatMoney(subscription.price)}
                                            <span className="text-sm font-normal text-gray-500">
                                                /{subscription.billing_interval === 'yearly' ? 'year' : 'month'}
                                            </span>
                                        </p>
                                    </div>
                                    <div className="pt-3 border-t border-gray-100">
                                        <p className="text-sm font-medium text-gray-700 mb-2">Plan Limits</p>
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-gray-500">Products</span>
                                                <span className="font-medium text-gray-900">
                                                    {subscription.plan?.product_limit === null ? 'Unlimited' : subscription.plan?.product_limit}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-gray-500">Staff Accounts</span>
                                                <span className="font-medium text-gray-900">
                                                    {subscription.plan?.staff_limit === null ? 'Unlimited' : subscription.plan?.staff_limit}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-gray-500">Storage</span>
                                                <span className="font-medium text-gray-900">
                                                    {subscription.plan?.storage_limit === null ? 'Unlimited' : `${subscription.plan.storage_limit} MB`}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="bg-white rounded-xl border border-gray-200">
                                <div className="px-6 py-4 border-b border-gray-100">
                                    <h3 className="text-base font-semibold text-gray-900">Billing Information</h3>
                                </div>
                                <div className="p-6 space-y-4">
                                    <div>
                                        <p className="text-sm text-gray-500">Billing Cycle</p>
                                        <p className="text-base font-medium text-gray-900 capitalize">{subscription.billing_interval || '—'}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-500">Start Date</p>
                                        <p className="text-base font-medium text-gray-900">{subscription.starts_at || '—'}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-500">Expiry Date</p>
                                        <p className="text-base font-medium text-gray-900">{subscription.expires_at || '—'}</p>
                                    </div>
                                    {subscription.trial_ends_at && (
                                        <div>
                                            <p className="text-sm text-gray-500">Trial Ends</p>
                                            <p className="text-base font-medium text-gray-900">
                                                {subscription.trial_ends_at}
                                                {subscription.trial_days_remaining > 0 && (
                                                    <span className="text-sm font-normal text-gray-500 ml-1">
                                                        ({subscription.trial_days_remaining} day{subscription.trial_days_remaining > 1 ? 's' : ''} left)
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                    )}
                                    {subscription.cancelled_at && (
                                        <div>
                                            <p className="text-sm text-gray-500">Canceled On</p>
                                            <p className="text-base font-medium text-gray-900">{subscription.cancelled_at}</p>
                                        </div>
                                    )}
                                    {subscription.suspended_at && (
                                        <div>
                                            <p className="text-sm text-gray-500">Suspended On</p>
                                            <p className="text-base font-medium text-gray-900">{subscription.suspended_at}</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {can('billing.renew') && ['expired', 'past_due', 'canceled'].includes(subscription.status) && (
                            <div className="bg-white rounded-xl border border-gray-200">
                                <div className="px-6 py-4 border-b border-gray-100">
                                    <h3 className="text-base font-semibold text-gray-900">Renew Subscription</h3>
                                </div>
                                <div className="p-6">
                                    <p className="text-sm text-gray-600 mb-4">
                                        {subscription.status === 'expired' && 'Your subscription has expired. Renew now to restore full access to your store.'}
                                        {subscription.status === 'past_due' && 'Your payment is past due. Renew to continue using your subscription.'}
                                        {subscription.status === 'canceled' && 'Your subscription has been canceled. Renew to reactivate your store.'}
                                    </p>
                                    <button
                                        onClick={handleRenew}
                                        className="px-6 py-3 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
                                    >
                                        Renew Now
                                    </button>
                                </div>
                            </div>
                        )}

                        {auditLogs && auditLogs.length > 0 && (
                            <div className="bg-white rounded-xl border border-gray-200">
                                <div className="px-6 py-4 border-b border-gray-100">
                                    <h3 className="text-base font-semibold text-gray-900">Activity History</h3>
                                </div>
                                <div className="p-6">
                                    <div className="space-y-3">
                                        {auditLogs.map((log) => (
                                            <div key={log.id} className="flex items-start gap-3 text-sm">
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-medium text-gray-900 capitalize">
                                                            {log.event === 'trial_started' ? 'Trial Started' :
                                                             log.event === 'plan_changed' ? 'Plan Changed' :
                                                             log.event === 'renewed' ? 'Renewed' :
                                                             log.event === 'activated' ? 'Activated' :
                                                             log.event === 'canceled' ? 'Canceled' :
                                                             log.event === 'suspended' ? 'Suspended' :
                                                             log.event === 'past_due' ? 'Past Due' :
                                                             log.event === 'expired' ? 'Expired' :
                                                             log.event === 'trial_ended' ? 'Trial Ended' :
                                                             log.event}
                                                        </span>
                                                        <span className="text-gray-400">{log.created_at}</span>
                                                    </div>
                                                    {log.reason && (
                                                        <p className="text-gray-500 mt-0.5">{log.reason}</p>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>
        </AdminLayout>
    );
}
