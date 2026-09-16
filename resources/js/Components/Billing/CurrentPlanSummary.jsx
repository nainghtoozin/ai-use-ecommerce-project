import { usePage } from '@inertiajs/react';
import StatusBadge from '@/Components/Billing/StatusBadge';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';

function formatBytes(mb) {
    if (mb === null || mb === undefined) return null;
    if (mb >= 1024) return (mb / 1024).toFixed(1) + ' GB';
    return mb + ' MB';
}

export default function CurrentPlanSummary({ subscription, primary, secondaryLinks = [] }) {
    const pc = getPlatformCurrencyConfig(usePage().props.platform_setting);
    if (!subscription) return null;

    const plan = subscription.plan;
    const interval = subscription.billing_interval === 'yearly' ? '/yr' : '/mo';
    const price = subscription.price ?? plan?.monthly_price ?? plan?.yearly_price ?? null;
    const isFree = price === 0 || price === '0' || price === null;

    const dateLabel = subscription.status === 'expired' || subscription.status === 'canceled'
        ? 'Expired on'
        : subscription.status === 'suspended'
            ? 'Suspended on'
            : 'Renews on';
    const dateValue = subscription.status === 'suspended'
        ? (subscription.suspended_at || '—')
        : (subscription.next_billing_date || subscription.expires_at || '—');

    return (
        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 sm:p-6">
            <div className="flex flex-col lg:flex-row lg:items-center gap-5">
                <div className="flex-1 min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100">{plan?.name || 'No plan'}</h2>
                        <StatusBadge status={subscription.status} size="sm" />
                    </div>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {isFree
                            ? 'Free forever'
                            : <><span className="text-xl font-bold text-gray-900 dark:text-gray-100">{formatCurrency(price, pc)}</span><span>{interval} · billed {subscription.billing_interval || 'monthly'}</span></>}
                    </p>
                    <dl className="mt-4 flex flex-wrap gap-x-8 gap-y-3">
                        <div>
                            <dt className="text-[11px] font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider">{dateLabel}</dt>
                            <dd className="mt-0.5 text-sm font-semibold text-gray-900 dark:text-gray-100">{dateValue}</dd>
                        </div>
                        {subscription.on_trial && subscription.trial_ends_at && (
                            <div>
                                <dt className="text-[11px] font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider">Trial ends</dt>
                                <dd className="mt-0.5 text-sm font-semibold text-blue-600">{subscription.trial_ends_at}{subscription.trial_days_remaining > 0 ? ` (${subscription.trial_days_remaining}d left)` : ''}</dd>
                            </div>
                        )}
                        {subscription.pending_plan && (
                            <div>
                                <dt className="text-[11px] font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider">Scheduled change</dt>
                                <dd className="mt-0.5 text-sm font-semibold text-amber-600">{subscription.pending_plan.name} on {subscription.pending_plan_effective_at}</dd>
                            </div>
                        )}
                    </dl>
                </div>
                {(primary || secondaryLinks.length > 0) && (
                    <div className="flex flex-col gap-2 shrink-0 lg:items-end">
                        {primary && (
                            <button
                                type="button"
                                onClick={primary.onClick}
                                className={`inline-flex items-center justify-center px-5 py-2.5 rounded-lg text-sm font-semibold text-white transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 ${primary.tone === 'emerald' ? 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500' : 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500'}`}
                            >
                                {primary.label}
                            </button>
                        )}
                        {secondaryLinks.length > 0 && (
                            <div className="flex flex-wrap gap-x-4 gap-y-1">
                                {secondaryLinks.map((link) => (
                                    <a key={link.href} href={link.href} className="text-xs font-medium text-blue-600 hover:text-blue-800">
                                        {link.label}
                                    </a>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
