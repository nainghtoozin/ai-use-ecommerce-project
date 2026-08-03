import { Link, usePage } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';
import { useTranslation } from '@/Utils/useTranslation';
import { useState } from 'react';
import PlanFeatureList from '@/Components/Billing/PlanFeatureList';

export default function PricingSection({ plans }) {
    const { auth, allFeatureDefs, featureCategories, platform_setting } = usePage().props;
    const { t } = useTranslation();
    const pc = getPlatformCurrencyConfig(platform_setting);
    const [isYearly, setIsYearly] = useState(false);

    if (!plans || plans.length === 0) return null;

    const getPrice = (plan) => {
        if (isYearly && plan.yearly_price !== null && plan.yearly_price !== undefined) {
            return { price: plan.yearly_price, period: t('landing.pricing.per_year'), showMonthly: true, monthly: plan.monthly_price };
        }
        if (plan.monthly_price !== null && plan.monthly_price !== undefined) {
            return { price: plan.monthly_price, period: t('landing.pricing.per_month'), showMonthly: false };
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
        <section id="pricing" className="py-16 sm:py-20 lg:py-24 bg-white dark:bg-gray-900 scroll-mt-16">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center max-w-2xl mx-auto mb-10">
                    <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-gray-100">
                        {t('landing.pricing.title')}
                    </h2>
                    <p className="mt-4 text-gray-500 dark:text-gray-400 text-lg">
                        {t('landing.pricing.subtitle')}
                    </p>
                </div>

                <div className="flex items-center justify-center gap-3 mb-10">
                    <span className={`text-sm font-medium ${!isYearly ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400'}`}>{t('landing.pricing.monthly')}</span>
                    <button
                        type="button"
                        role="switch"
                        aria-checked={isYearly}
                        aria-label="Toggle yearly billing"
                        onClick={() => setIsYearly(!isYearly)}
                        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${isYearly ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'}`}
                    >
                        <span className={`inline-block h-4 w-4 transform rounded-full bg-white dark:bg-gray-900 transition-transform ${isYearly ? 'translate-x-6' : 'translate-x-1'}`} />
                    </button>
                    <span className={`text-sm font-medium ${isYearly ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400'}`}>
                        {t('landing.pricing.yearly')}
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

                        const yearlySavings = plan.monthly_price && plan.yearly_price
                            ? (parseFloat(plan.monthly_price) * 12) - parseFloat(plan.yearly_price)
                            : 0;
                        const savingsPercent = yearlySavings > 0 && plan.monthly_price > 0
                            ? ((yearlySavings / (parseFloat(plan.monthly_price) * 12)) * 100).toFixed(1)
                            : 0;
                        const showSavings = isYearly && yearlySavings > 0;

                        return (
                            <div
                                key={planSlug}
                                className={`relative rounded-2xl border-2 p-6 sm:p-8 flex flex-col transition-shadow duration-200 dark:bg-gray-900 ${planSlug === 'starter'
                                    ? 'border-blue-500 bg-white dark:border-blue-400 shadow-xl shadow-blue-500/10 scale-[1.02]'
                                    : 'border-gray-200 bg-white dark:border-gray-800 hover:shadow-lg'
                                }`}
                                role="region"
                                aria-label={`${planName} plan`}
                            >
                                {planSlug === 'starter' && (
                                    <div className="absolute -top-3.5 left-1/2 -translate-x-1/2 z-10">
                                        <span className="px-4 py-1 text-xs font-semibold text-white bg-blue-600 rounded-full shadow-sm">
                                            {t('landing.pricing.most_popular')}
                                        </span>
                                    </div>
                                )}
                                {isCurrentPlan && (
                                    <div className="absolute -top-3.5 left-1/2 -translate-x-1/2 z-10">
                                        <span className="px-4 py-1 text-xs font-semibold text-emerald-700 bg-emerald-100 rounded-full">
                                            {t('landing.pricing.current_plan')}
                                        </span>
                                    </div>
                                )}

                                <div className="mb-6">
                                    <div className="flex items-center gap-2 mb-1">
                                        <h3 className="text-xl font-bold text-gray-900 dark:text-gray-100">{planName}</h3>
                                        {planSlug === 'business' && (
                                            <Sparkles className="w-4 h-4 text-amber-400" aria-label="Best value" />
                                        )}
                                    </div>
                                    {plan.description && (
                                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{plan.description}</p>
                                    )}

                                    <div className="mt-4 flex items-baseline gap-1">
                                        {price !== null ? (
                                            <>
                                                <span className="text-4xl font-bold text-gray-900 dark:text-gray-100">
                                                    {price === 0 ? 'Free' : formatCurrency(price, pc)}
                                                </span>
                                                <span className="text-sm text-gray-400 dark:text-gray-500">{period}</span>
                                            </>
                                        ) : (
                                            <span className="text-4xl font-bold text-gray-900 dark:text-gray-100">{t('landing.pricing.contact_us')}</span>
                                        )}
                                    </div>
                                    {effectiveMonthly !== null && (
                                        <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                            {pc.symbol}{effectiveMonthly}{t('landing.pricing.billed_yearly')}
                                        </p>
                                    )}
                                    {showSavings && (
                                        <p className="text-xs text-emerald-600 font-medium mt-1">
                                            {t('landing.pricing.save', { amount: formatCurrency(yearlySavings, pc), percent: savingsPercent })}
                                        </p>
                                    )}
                                </div>

                                <div className="flex-1 mb-6">
                                    <PlanFeatureList plan={plan} allFeatureDefs={allFeatureDefs || []} featureCategories={featureCategories || []} />
                                </div>

                                {isCurrentPlan ? (
                                    <div className="w-full py-2.5 rounded-lg text-sm font-medium text-center bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 cursor-not-allowed">
                                        {t('landing.pricing.current_plan')}
                                    </div>
                                ) : (
                                    <Link
                                        href="/register"
                                        className={`w-full py-2.5 rounded-lg text-sm font-medium text-center transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 ${planSlug === 'starter'
                                            ? 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 shadow-sm'
                                            : planSlug === 'business'
                                                ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-gray-100 focus:ring-gray-500'
                                                : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 focus:ring-gray-300'
                                            }`}
                                    >
                                        {isFree ? t('landing.pricing.get_started_free') : t('landing.pricing.start_free_trial')}
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
