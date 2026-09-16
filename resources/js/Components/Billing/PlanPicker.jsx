import { Check } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';

export function getRecommendedSlug(usage, plans) {
    if (!usage || !plans) return null;
    if (usage?.product_limit?.percent >= 80) return 'starter';
    if (usage?.staff_limit?.percent >= 80) return 'starter';
    if (usage?.storage_limit?.percent >= 80) return 'business';
    if (usage?.orders_monthly_limit?.percent >= 80) return 'starter';
    return null;
}

function formatBytes(mb) {
    if (mb === null || mb === undefined) return null;
    if (mb >= 1024) return (mb / 1024).toFixed(1) + ' GB';
    return mb + ' MB';
}

function prettyLabel(key) {
    return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function PlanPicker({ plans, subscription, currentPlan, interval, onIntervalChange, onChoose, recommendedSlug, allFeatureDefs = [] }) {
    const pc = getPlatformCurrencyConfig(usePage().props.platform_setting);
    const baseInterval = subscription?.billing_interval || 'monthly';

    const priceFor = (plan, cycle) => cycle === 'yearly' ? plan.yearly_price : plan.monthly_price;
    const currentBase = currentPlan ? Number(priceFor(currentPlan, baseInterval) ?? 0) : 0;

    const labelFor = (key) => allFeatureDefs.find((d) => d.key === key)?.label || prettyLabel(key);

    return (
        <div>
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Available Plans</h2>
                <div className="inline-flex items-center self-start rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-1" role="group" aria-label="Billing cycle">
                    {['monthly', 'yearly'].map((cycle) => (
                        <button
                            key={cycle}
                            type="button"
                            onClick={() => onIntervalChange(cycle)}
                            aria-pressed={interval === cycle}
                            className={`px-3 py-1.5 rounded-md text-sm font-medium capitalize transition-colors ${interval === cycle ? 'bg-blue-600 text-white' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}
                        >
                            {cycle}
                        </button>
                    ))}
                </div>
            </div>

            {!plans || plans.length === 0 ? (
                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-8 text-center">
                    <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">No Plans Available</p>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Please contact support for assistance.</p>
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {plans.map((plan) => {
                        const price = priceFor(plan, interval);
                        const numericPrice = Number(price ?? 0);
                        const isCurrent = !!plan.is_current;
                        const isFree = plan.slug === 'free' || numericPrice === 0;
                        const targetBase = Number(priceFor(plan, baseInterval) ?? 0);
                        const action = isCurrent ? 'current' : targetBase <= 0 || targetBase < currentBase ? 'switch' : 'upgrade';
                        const savings = interval === 'yearly' && plan.monthly_price && plan.yearly_price
                            ? (parseFloat(plan.monthly_price) * 12) - parseFloat(plan.yearly_price)
                            : 0;

                        const limitBits = [
                            plan.limits?.product_limit != null ? `${plan.limits.product_limit} products` : null,
                            plan.limits?.staff_limit != null ? `${plan.limits.staff_limit} staff` : null,
                            plan.limits?.storage_limit != null ? `${formatBytes(plan.limits.storage_limit)} storage` : null,
                            plan.limits?.orders_monthly_limit != null ? `${plan.limits.orders_monthly_limit} orders/mo` : null,
                        ].filter(Boolean).slice(0, 3);
                        const enabledFeatures = (plan.features || []).filter((f) => f.enabled).map((f) => labelFor(f.key));
                        const benefits = [...limitBits, ...enabledFeatures].slice(0, 5);
                        const extraCount = [...limitBits, ...enabledFeatures].length - benefits.length;

                        return (
                            <div
                                key={plan.slug}
                                className={`relative rounded-xl border-2 p-5 flex flex-col transition-shadow ${isCurrent ? 'border-blue-500 bg-blue-50/30 dark:bg-blue-900/10 shadow-md shadow-blue-500/10' : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:shadow-md hover:border-gray-300 dark:hover:border-gray-700'}`}
                            >
                                <div className="absolute -top-3 left-1/2 -translate-x-1/2 z-10 flex gap-1">
                                    {isCurrent && (
                                        <span className="px-3 py-1 text-xs font-semibold text-white bg-blue-600 rounded-full shadow-sm whitespace-nowrap">Current Plan</span>
                                    )}
                                    {!isCurrent && recommendedSlug === plan.slug && (
                                        <span className="px-3 py-1 text-xs font-semibold text-emerald-700 bg-emerald-100 rounded-full whitespace-nowrap">Recommended</span>
                                    )}
                                </div>

                                <h3 className="text-base font-bold text-gray-900 dark:text-gray-100">{plan.name}</h3>
                                {plan.description && (
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">{plan.description}</p>
                                )}
                                <div className="mt-2 flex items-baseline gap-1">
                                    <span className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                        {isFree ? 'Free' : price !== null && price !== undefined ? formatCurrency(price, pc) : '—'}
                                    </span>
                                    {!isFree && price !== null && price !== undefined && (
                                        <span className="text-xs text-gray-400 dark:text-gray-500">/{interval === 'yearly' ? 'yr' : 'mo'}</span>
                                    )}
                                </div>
                                {savings > 0 && (
                                    <p className="text-[11px] text-emerald-600 font-medium mt-0.5">Save {formatCurrency(savings, pc)}/yr</p>
                                )}

                                <ul className="flex-1 mt-4 mb-5 space-y-1.5">
                                    {benefits.map((benefit) => (
                                        <li key={benefit} className="flex items-start gap-2 text-[13px] text-gray-600 dark:text-gray-300">
                                            <Check className="w-3.5 h-3.5 text-emerald-500 mt-0.5 shrink-0" />
                                            <span className="truncate" title={benefit}>{benefit}</span>
                                        </li>
                                    ))}
                                    {extraCount > 0 && (
                                        <li className="text-[11px] text-gray-400 dark:text-gray-500 pl-[22px]">+{extraCount} more included</li>
                                    )}
                                </ul>

                                <button
                                    type="button"
                                    disabled={isCurrent}
                                    onClick={() => onChoose && onChoose(plan, { action, interval })}
                                    className={`w-full py-2.5 rounded-lg text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 ${isCurrent ? 'bg-gray-100 dark:bg-gray-800 text-gray-400 cursor-not-allowed' : action === 'upgrade' ? 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500' : 'bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-white focus:ring-gray-500'}`}
                                >
                                    {isCurrent ? 'Current Plan' : action === 'upgrade' ? 'Upgrade' : action === 'switch' ? 'Switch Plan' : 'Choose'}
                                </button>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
