import UsageProgressBar from '@/Components/Billing/UsageProgressBar';
import { CURRENCY_SYMBOL } from '@/Utils/currency';

const statusConfig = {
    active:    { label: 'Active',        classes: 'bg-emerald-50 border-emerald-200 text-emerald-700' },
    trialing:  { label: 'Trialing',      classes: 'bg-blue-50 border-blue-200 text-blue-700' },
    past_due:  { label: 'Past Due',      classes: 'bg-amber-50 border-amber-200 text-amber-700' },
    expired:   { label: 'Expired',       classes: 'bg-red-50 border-red-200 text-red-700' },
    canceled:  { label: 'Canceled',      classes: 'bg-gray-50 border-gray-200 text-gray-600' },
    suspended: { label: 'Suspended',     classes: 'bg-yellow-50 border-yellow-200 text-yellow-700' },
};

function formatBytes(mb) {
    if (mb === null || mb === undefined) return null;
    if (mb >= 1024) return (mb / 1024).toFixed(1) + ' GB';
    return mb + ' MB';
}

export default function CurrentPlanCard({ subscription, usage }) {
    const plan = subscription?.plan;
    const cfg = statusConfig[subscription?.status] || statusConfig.expired;
    const price = plan?.monthly_price ?? plan?.yearly_price;
    const interval = subscription?.billing_interval === 'yearly' ? '/year' : '/month';
    const formatMoney = (v) => v !== null && v !== undefined ? CURRENCY_SYMBOL + Number(v).toFixed(2) : null;

    const limitRows = [
        { key: 'product_limit', label: 'Products', format: null },
        { key: 'staff_limit', label: 'Staff', format: null },
        { key: 'storage_limit', label: 'Storage', format: (v) => formatBytes(v) ?? 'Unlimited' },
        { key: 'orders_monthly_limit', label: 'Monthly Orders', format: null },
        { key: 'coupon_limit', label: 'Coupons', format: null },
        { key: 'promotion_limit', label: 'Promotions', format: null },
    ];

    return (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div className="p-6">
                <div className="flex items-start justify-between mb-5">
                    <div>
                        <div className="flex items-center gap-3 mb-1">
                            <h2 className="text-xl font-bold text-gray-900">{plan?.name || 'No Plan'}</h2>
                            {subscription && (
                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${cfg.classes}`}>
                                    {cfg.label}
                                </span>
                            )}
                        </div>
                        {plan?.description && (
                            <p className="text-sm text-gray-500">{plan.description}</p>
                        )}
                    </div>
                    <div className="text-right">
                        {price !== null && (
                            <div className="text-2xl font-bold text-gray-900">
                                {formatMoney(price)}
                                <span className="text-sm font-normal text-gray-400">{interval}</span>
                            </div>
                        )}
                    </div>
                </div>

                {subscription?.on_trial && subscription?.trial_days_remaining > 0 && (
                    <div className="mb-5 p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium text-blue-800">Trial Period</p>
                            <p className="text-xs text-blue-600">
                                {subscription.trial_days_remaining} day{subscription.trial_days_remaining !== 1 ? 's' : ''} remaining
                                {subscription.trial_ends_at ? ` — ends ${subscription.trial_ends_at}` : ''}
                            </p>
                        </div>
                    </div>
                )}

                {subscription && ['expired', 'past_due', 'canceled'].includes(subscription.status) && (
                    <div className="mb-5 p-3 bg-red-50 border border-red-100 rounded-lg">
                        <p className="text-sm font-medium text-red-800">
                            {subscription.status === 'expired' && 'Your subscription has expired.'}
                            {subscription.status === 'past_due' && 'Your payment is past due.'}
                            {subscription.status === 'canceled' && 'Your subscription has been canceled.'}
                        </p>
                        {subscription.status === 'expired' && subscription.days_since_expiry > 0 && (
                            <p className="text-xs text-red-600 mt-0.5">
                                Expired {subscription.days_since_expiry} day{subscription.days_since_expiry > 1 ? 's' : ''} ago
                            </p>
                        )}
                    </div>
                )}

                {subscription && !['expired', 'past_due', 'canceled', 'suspended'].includes(subscription.status) && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {limitRows.map(({ key, label }) => {
                            const u = usage?.[key];
                            return (
                                <UsageProgressBar
                                    key={key}
                                    label={label}
                                    current={u?.current ?? 0}
                                    limit={u?.limit ?? null}
                                    isUnlimited={u?.is_unlimited ?? false}
                                />
                            );
                        })}
                    </div>
                )}
            </div>

            {subscription && (
                <div className="border-t border-gray-100 bg-gray-50/50 px-6 py-3 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                    <div>
                        <span className="text-gray-400">Billing</span>
                        <p className="font-medium text-gray-700 capitalize">{subscription.billing_interval || '—'}</p>
                    </div>
                    <div>
                        <span className="text-gray-400">Start</span>
                        <p className="font-medium text-gray-700">{subscription.starts_at || '—'}</p>
                    </div>
                    <div>
                        <span className="text-gray-400">Expires</span>
                        <p className="font-medium text-gray-700">{subscription.expires_at || '—'}</p>
                    </div>
                    {subscription?.trial_ends_at && (
                        <div>
                            <span className="text-gray-400">Trial Ends</span>
                            <p className="font-medium text-gray-700">{subscription.trial_ends_at}</p>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
