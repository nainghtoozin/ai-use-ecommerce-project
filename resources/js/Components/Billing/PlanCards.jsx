import { Check, Sparkles } from 'lucide-react';
import { CURRENCY_SYMBOL } from '@/Utils/currency';

const highlightFeatures = {
    free: ['Standard Products', 'Order Management', 'Cash on Delivery'],
    starter: ['Variable Products', 'Analytics & Reports', 'Coupons', 'Custom Domain', 'Telegram Integration'],
    business: ['Combo Products', 'Digital Products', 'AI Features', 'All Payment Gateways', 'Advanced SEO'],
};

function getFeaturesForPlan(planSlug) {
    return highlightFeatures[planSlug] || [];
}

export default function PlanCards({ plans, onUpgrade }) {
    const handleUpgrade = (plan) => {
        if (onUpgrade && !plan.is_current) {
            onUpgrade(plan);
        }
    };

    return (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
            {plans.map(plan => {
                const price = plan.monthly_price;
                const isCurrent = plan.is_current;
                const isFree = plan.slug === 'free';
                const savings = plan.yearly_savings_percent;

                return (
                    <div
                        key={plan.slug}
                        className={`relative rounded-xl border-2 p-6 flex flex-col transition-shadow duration-200 ${
                            isCurrent
                                ? 'border-blue-500 bg-blue-50/20 shadow-lg shadow-blue-500/10'
                                : 'border-gray-200 bg-white hover:shadow-md hover:border-gray-300'
                        }`}
                        role="region"
                        aria-label={`${plan.name} plan${isCurrent ? ' — your current plan' : ''}`}
                    >
                        {isCurrent && (
                            <div className="absolute -top-3 left-1/2 -translate-x-1/2 z-10">
                                <span className="px-3 py-1 text-xs font-semibold text-white bg-blue-600 rounded-full shadow-sm">
                                    Current Plan
                                </span>
                            </div>
                        )}
                        {!isCurrent && savings > 0 && (
                            <div className="absolute -top-3 left-1/2 -translate-x-1/2 z-10">
                                <span className="px-3 py-1 text-xs font-semibold text-emerald-700 bg-emerald-100 rounded-full">
                                    Save {savings}% yearly
                                </span>
                            </div>
                        )}

                        <div className="mb-5">
                            <div className="flex items-center gap-2 mb-1">
                                <h3 className="text-lg font-bold text-gray-900">{plan.name}</h3>
                                {plan.slug === 'business' && (
                                    <Sparkles className="w-4 h-4 text-amber-400" aria-label="Best value" />
                                )}
                            </div>
                            {plan.description && (
                                <p className="text-sm text-gray-500">{plan.description}</p>
                            )}
                            <div className="mt-3 flex items-baseline gap-1">
                                <span className="text-3xl font-bold text-gray-900">
                                    {price === 0 ? 'Free' : price !== null ? `${CURRENCY_SYMBOL}${price}` : '—'}
                                </span>
                                {!isFree && price !== null && (
                                    <span className="text-sm text-gray-400">/month</span>
                                )}
                            </div>
                        </div>

                        <ul className="space-y-2.5 flex-1 mb-6" aria-label={`${plan.name} plan highlights`}>
                            {getFeaturesForPlan(plan.slug).map((feat, i) => (
                                <li key={i} className="flex items-start gap-2 text-sm">
                                    <Check className="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                                    <span className="text-gray-600">{feat}</span>
                                </li>
                            ))}
                        </ul>

                        <button
                            type="button"
                            onClick={() => handleUpgrade(plan)}
                            disabled={isCurrent}
                            aria-label={isCurrent ? `You are on the ${plan.name} plan` : `Upgrade to ${plan.name}`}
                            className={`w-full py-2.5 rounded-lg text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 ${
                                isCurrent
                                    ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                    : isFree
                                        ? 'bg-gray-900 text-white hover:bg-gray-800 focus:ring-gray-500'
                                        : 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500'
                            }`}
                        >
                            {isCurrent ? 'Current Plan' : 'Upgrade'}
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
