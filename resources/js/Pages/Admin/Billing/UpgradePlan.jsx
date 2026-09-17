import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import CurrentPlanSummary from '@/Components/Billing/CurrentPlanSummary';
import PlanPicker, { getRecommendedSlug } from '@/Components/Billing/PlanPicker';
import { Zap, Calendar } from 'lucide-react';
import { adminUrl } from '@/Utils/adminUrl';

export default function AdminBillingUpgradePlan({ currentPlan, subscription, plans, usage, allFeatureDefs, trialDays }) {
    const [billingInterval, setBillingInterval] = useState(subscription?.billing_interval || 'monthly');
    const recommendedSlug = getRecommendedSlug(usage, plans);

    const handleChoose = (plan, { action, interval }) => {
        if (action === 'upgrade') {
            router.get(adminUrl(`/admin/billing/checkout/${plan.slug}`), { billing_cycle: interval });
        } else {
            router.post(adminUrl('/admin/billing/change-plan/preview'), { plan_id: plan.id, billing_interval: interval });
        }
    };

    return (
        <AdminLayout>
            <Head title="Plan Selection & Upgrade" />

            <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-6xl mx-auto">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Plan Selection & Upgrade</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Compare plans and choose the right one for your business</p>
                </div>

                <CurrentPlanSummary subscription={subscription} />

                {subscription?.pending_plan && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div className="flex items-start gap-3">
                            <div className="p-1.5 rounded-lg bg-amber-100 flex-shrink-0">
                                <Calendar className="w-5 h-5 text-amber-600" />
                            </div>
                            <div className="flex-1">
                                <p className="text-sm font-semibold text-amber-800">Scheduled Plan Change</p>
                                <p className="text-xs text-amber-600 mt-0.5">
                                    Your plan will change to <strong>{subscription.pending_plan.name}</strong> on {subscription.pending_plan_effective_at}.
                                </p>
                                <button
                                    onClick={() => router.post(adminUrl('/admin/billing/change-plan/cancel'))}
                                    className="mt-2 px-3 py-1 text-xs font-medium text-red-600 bg-white dark:bg-gray-900 border border-red-200 rounded-lg hover:bg-red-50 transition-colors"
                                >
                                    Cancel Scheduled Change
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {subscription && subscription.on_trial && subscription.trial_days_remaining > 0 && subscription.trial_days_remaining <= 7 && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div className="flex items-start gap-3">
                            <div className="p-1.5 rounded-lg bg-amber-100 flex-shrink-0">
                                <Zap className="w-4 h-4 text-amber-600" />
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-amber-800">Trial expires {subscription.trial_days_remaining === 1 ? 'today' : `in ${subscription.trial_days_remaining} days`}</p>
                                <p className="text-xs text-amber-600 mt-0.5">Choose a plan below to continue using all features after your trial ends.</p>
                            </div>
                        </div>
                    </div>
                )}

                <PlanPicker
                    plans={plans}
                    subscription={subscription}
                    currentPlan={currentPlan}
                    interval={billingInterval}
                    onIntervalChange={setBillingInterval}
                    onChoose={handleChoose}
                    recommendedSlug={recommendedSlug}
                    allFeatureDefs={allFeatureDefs}
                    trialDays={trialDays}
                />
            </div>
        </AdminLayout>
    );
}
