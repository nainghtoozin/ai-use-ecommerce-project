import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import CurrentPlanCard from '@/Components/Billing/CurrentPlanCard';
import PlanFeatureMatrix from '@/Components/Billing/PlanFeatureMatrix';
import PlanCards from '@/Components/Billing/PlanCards';
import UpgradeDialog from '@/Components/Billing/UpgradeDialog';
import { adminUrl } from '@/Utils/adminUrl';

export default function AdminBillingIndex({ subscription, usage, plans, featureCategories, allFeatureDefs, auditLogs }) {
    const { auth } = usePage().props;
    const permissions = auth?.user?.permissions || [];
    const can = (perm) => permissions.includes(perm);

    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogTarget, setDialogTarget] = useState(null);
    const [dialogFeatureKey, setDialogFeatureKey] = useState(null);

    const currentPlan = plans?.find(p => p.is_current) || null;

    const openUpgradeDialog = (plan) => {
        setDialogTarget(plan);
        setDialogFeatureKey(null);
        setDialogOpen(true);
    };

    const handleLockedFeature = (featureKey, planSlug) => {
        const plan = plans?.find(p => p.slug === planSlug);
        if (plan && !plan.is_current) {
            const upgradeHint = allFeatureDefs?.find(f => f.key === featureKey)?.upgradeHint;
            const betterPlan = upgradeHint
                ? plans?.find(p => p.name === upgradeHint)
                : plans?.find(p => !p.is_current && p.slug !== 'free');
            setDialogTarget(betterPlan || plan);
            setDialogFeatureKey(featureKey);
            setDialogOpen(true);
        }
    };

    const handleRenew = () => {
        router.post(adminUrl('/admin/billing/renew'), {}, { preserveScroll: true });
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
                        <div className="text-4xl text-gray-300 mb-3">
                            <svg className="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                        </div>
                        <p className="text-gray-500">No subscription found for your store.</p>
                        <p className="text-sm text-gray-400 mt-1">Please contact your account manager.</p>
                    </div>
                )}

                {subscription && (
                    <>
                        <CurrentPlanCard subscription={subscription} usage={usage} />

                        {subscription && !['active', 'trialing'].includes(subscription.status) && can('billing.renew') && (
                            <div className="bg-white rounded-xl border border-gray-200 p-6">
                                <h3 className="text-base font-semibold text-gray-900 mb-2">Renew Subscription</h3>
                                <p className="text-sm text-gray-600 mb-4">
                                    {subscription.status === 'expired' && 'Your subscription has expired. Renew now to restore full access.'}
                                    {subscription.status === 'past_due' && 'Your payment is past due. Renew to continue using your subscription.'}
                                    {subscription.status === 'canceled' && 'Your subscription has been canceled. Renew to reactivate your store.'}
                                </p>
                                <button
                                    onClick={handleRenew}
                                    className="px-6 py-3 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    Renew Now
                                </button>
                            </div>
                        )}

                        <PlanCards plans={plans} onUpgrade={openUpgradeDialog} />

                        <PlanFeatureMatrix
                            plans={plans}
                            featureCategories={featureCategories}
                            allFeatureDefs={allFeatureDefs}
                            onLockedFeatureClick={handleLockedFeature}
                        />

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
                                                             log.event === 'trial_renewed' ? 'Trial Renewed' :
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

            <UpgradeDialog
                isOpen={dialogOpen}
                onClose={() => setDialogOpen(false)}
                currentPlan={currentPlan}
                targetPlan={dialogTarget}
                featureKey={dialogFeatureKey}
                allFeatureDefs={allFeatureDefs}
            />
        </AdminLayout>
    );
}
