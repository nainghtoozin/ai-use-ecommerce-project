import { Link, usePage } from '@inertiajs/react';
import { Check, Sparkles } from 'lucide-react';
import { CURRENCY_SYMBOL } from '@/Utils/currency';
import { useState } from 'react';

const highlightFeatures = {
    free: ['Standard Products', 'Order Management', 'Cash on Delivery'],
    starter: ['Variable Products', 'Analytics & Reports', 'Coupons', 'Custom Domain', 'Telegram Integration'],
    business: ['Combo Products', 'Digital Products', 'AI Features', 'All Payment Gateways', 'Advanced SEO'],
};

function getFeaturesForPlan(planSlug) {
    return highlightFeatures[planSlug] || [];
}

export default function PricingSection({ plans }) {
    const { auth } = usePage().props;
    const [isYearly, setIsYearly] = useState(false);

    if (!plans || plans.length === 0) return null;

    const getPrice = (plan) => {
        if (isYearly && plan.yearly_price !== null && plan.yearly_price !== undefined) {
            return { price: plan.yearly_price, period: '/year', showMonthly: true, monthly: plan.monthly_price };
        }
        if (plan.monthly_price !== null && plan.monthly_price !== undefined) {
            return { price: plan.monthly_price, period: '/month', showMonthly: false };
        }
        return { price: null, period: '' };
    };

    const getEffectiveMonthly = (plan) => {
        if (isYearly && plan.yearly_price && plan.monthly_price) {
            return Math.round((plan.yearly_price / 12) * 10) / 10;
        }
        return null;
    };

    return (
        <section id="pricing" className="py-16 sm:py-20 lg:py-24 bg-white scroll-mt-16">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center max-w-2xl mx-auto mb-10">
                    <h2 className="text-3xl sm:text-4xl font-bold text-gray-900">
                        Simple, Transparent Pricing
                    </h2>
                    <p className="mt-4 text-gray-500 text-lg">
                        Start free. Upgrade when you grow. No hidden fees.
                    </p>
                </div>

                <div className="flex items-center justify-center gap-3 mb-10">
                    <span className={`text-sm font-medium ${!isYearly ? 'text-gray-900' : 'text-gray-400'}`}>Monthly</span>
                    <button
                        type="button"
                        role="switch"
                        aria-checked={isYearly}
                        aria-label="Toggle yearly billing"
                        onClick={() => setIsYearly(!isYearly)}
                        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${isYearly ? 'bg-blue-600' : 'bg-gray-300'}`}
                    >
                        <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${isYearly ? 'translate-x-6' : 'translate-x-1'}`} />
                    </button>
                    <span className={`text-sm font-medium ${isYearly ? 'text-gray-900' : 'text-gray-400'}`}>
                        Yearly
                        {isYearly && <span className="text-emerald-600 ml-1 text-xs font-normal">Save ~17%</span>}
                    </span>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto">
                    {plans.map((plan) => {
                        if (!plan || typeof plan !== 'object') return null;
                        const planSlug = plan.slug || '';
                        const planName = plan.name || 'Plan';
                        const { price, period } = getPrice(plan);
                        const effectiveMonthly = getEffectiveMonthly(plan);
                        const isFree = planSlug === 'free';
                        const isCurrentPlan = auth?.user && (plan.is_current);

                        return (
                            <div
                                key={planSlug}
                                className={`relative rounded-2xl border-2 p-6 sm:p-8 flex flex-col transition-shadow duration-200 ${planSlug === 'starter'
                                    ? 'border-blue-500 bg-white shadow-xl shadow-blue-500/10 scale-[1.02]'
                                    : 'border-gray-200 bg-white hover:shadow-lg'
                                }`}
                                role="region"
                                aria-label={`${planName} plan`}
                            >
                                {planSlug === 'starter' && (
                                    <div className="absolute -top-3.5 left-1/2 -translate-x-1/2 z-10">
                                        <span className="px-4 py-1 text-xs font-semibold text-white bg-blue-600 rounded-full shadow-sm">
                                            Most Popular
                                        </span>
                                    </div>
                                )}
                                {isCurrentPlan && (
                                    <div className="absolute -top-3.5 left-1/2 -translate-x-1/2 z-10">
                                        <span className="px-4 py-1 text-xs font-semibold text-emerald-700 bg-emerald-100 rounded-full">
                                            Current Plan
                                        </span>
                                    </div>
                                )}

                                <div className="mb-6">
                                    <div className="flex items-center gap-2 mb-1">
                                        <h3 className="text-xl font-bold text-gray-900">{planName}</h3>
                                        {planSlug === 'business' && (
                                            <Sparkles className="w-4 h-4 text-amber-400" aria-label="Best value" />
                                        )}
                                    </div>
                                    {plan.description && (
                                        <p className="text-sm text-gray-500 mt-1">{plan.description}</p>
                                    )}

                                    <div className="mt-4 flex items-baseline gap-1">
                                        {price !== null ? (
                                            <>
                                                <span className="text-4xl font-bold text-gray-900">
                                                    {price === 0 ? 'Free' : `${CURRENCY_SYMBOL}${price}`}
                                                </span>
                                                <span className="text-sm text-gray-400">{period}</span>
                                            </>
                                        ) : (
                                            <span className="text-4xl font-bold text-gray-900">Contact Us</span>
                                        )}
                                    </div>
                                    {effectiveMonthly !== null && (
                                        <p className="text-xs text-gray-400 mt-1">
                                            {CURRENCY_SYMBOL}{effectiveMonthly}/month billed yearly
                                        </p>
                                    )}
                                </div>

                                <ul className="space-y-3 flex-1 mb-6" aria-label={`${planName} plan features`}>
                                    {getFeaturesForPlan(planSlug).map((feat, i) => (
                                        <li key={i} className="flex items-start gap-2.5 text-sm">
                                            <Check className="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                                            <span className="text-gray-600">{feat || ''}</span>
                                        </li>
                                    ))}
                                </ul>

                                {isCurrentPlan ? (
                                    <div className="w-full py-2.5 rounded-lg text-sm font-medium text-center bg-gray-100 text-gray-400 cursor-not-allowed">
                                        Current Plan
                                    </div>
                                ) : (
                                    <Link
                                        href={isFree ? '/register' : '/create-store'}
                                        className={`w-full py-2.5 rounded-lg text-sm font-medium text-center transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 ${planSlug === 'starter'
                                            ? 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 shadow-sm'
                                            : planSlug === 'business'
                                                ? 'bg-gray-900 text-white hover:bg-gray-800 focus:ring-gray-500'
                                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 focus:ring-gray-300'
                                            }`}
                                    >
                                        {isFree ? 'Get Started Free' : 'Start Free Trial'}
                                    </Link>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
