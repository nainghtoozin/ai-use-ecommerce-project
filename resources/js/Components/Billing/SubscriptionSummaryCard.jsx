import StatusBadge from '@/Components/Billing/StatusBadge';
import { CURRENCY_SYMBOL } from '@/Utils/currency';

function formatBytes(mb) {
    if (mb === null || mb === undefined) return null;
    if (mb >= 1024) return (mb / 1024).toFixed(1) + ' GB';
    return mb + ' MB';
}

export default function SubscriptionSummaryCard({ subscription }) {
    if (!subscription) return null;

    const plan = subscription.plan;
    const interval = subscription.billing_interval === 'yearly' ? '/yr' : '/mo';
    const price = plan?.monthly_price ?? plan?.yearly_price;
    const formatMoney = (v) => v !== null && v !== undefined ? CURRENCY_SYMBOL + Number(v).toFixed(2) : null;

    const summaryRows = [
        { label: 'Plan', value: plan?.name || '—' },
        { label: 'Status', value: <StatusBadge status={subscription.status} size="sm" /> },
        { label: 'Billing', value: <span className="capitalize">{subscription.billing_interval || '—'}</span> },
        { label: 'Price', value: price !== null ? <span>{formatMoney(price)}<span className="text-gray-400 font-normal">{interval}</span></span> : '—' },
        { label: 'Started', value: subscription.starts_at || '—' },
        { label: 'Expires', value: subscription.expires_at || '—' },
    ];

    if (subscription.trial_ends_at) {
        summaryRows.push({ label: 'Trial Ends', value: subscription.trial_ends_at });
    }

    if (subscription.cancelled_at) {
        summaryRows.push({ label: 'Cancelled', value: subscription.cancelled_at });
    }

    if (subscription.suspended_at) {
        summaryRows.push({ label: 'Suspended', value: subscription.suspended_at });
    }

    return (
        <div className="bg-white rounded-xl border border-gray-200">
            <div className="px-6 py-4 border-b border-gray-100">
                <h3 className="text-base font-semibold text-gray-900">Subscription Summary</h3>
            </div>
            <div className="p-6">
                <dl className="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-4">
                    {summaryRows.map(({ label, value }) => (
                        <div key={label}>
                            <dt className="text-xs font-medium text-gray-400 uppercase tracking-wider">{label}</dt>
                            <dd className="mt-1 text-sm font-semibold text-gray-900">{value}</dd>
                        </div>
                    ))}
                </dl>
            </div>
        </div>
    );
}
