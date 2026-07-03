import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function AdminBillingUpgradePlan({ currentPlan, plans, usage }) {
    return (
        <AdminLayout>
            <Head title="Upgrade Plan" />

            <div className="p-6 lg:p-8 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Upgrade Plan</h1>
                        <p className="text-sm text-gray-500 mt-1">Compare plans and choose the right one for your business</p>
                    </div>
                </div>

                {currentPlan ? (
                    <div className="bg-white rounded-xl border border-gray-200 p-6">
                        <p className="text-sm text-gray-600 mb-4">
                            You are currently on the <span className="font-semibold text-gray-900">{currentPlan.name}</span> plan.
                            {plans?.length > 1 && ' Compare the available plans below to find the best fit for your needs.'}
                        </p>
                    </div>
                ) : (
                    <div className="bg-white rounded-xl border border-gray-200 p-8 text-center">
                        <p className="text-gray-500">No active plan found. Browse available plans below.</p>
                    </div>
                )}

                {plans && plans.length > 0 && (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {plans.map((plan) => (
                            <div
                                key={plan.id}
                                className={`bg-white rounded-xl border-2 p-6 transition-all duration-200 ${
                                    plan.is_current
                                        ? 'border-blue-500 shadow-lg shadow-blue-100'
                                        : 'border-gray-200 hover:border-blue-300 hover:shadow-md'
                                }`}
                            >
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-lg font-bold text-gray-900">{plan.name}</h3>
                                    {plan.is_current && (
                                        <span className="px-2.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 rounded-full">Current</span>
                                    )}
                                </div>
                                {plan.description && (
                                    <p className="text-sm text-gray-500 mb-4">{plan.description}</p>
                                )}
                                <div className="mb-4">
                                    <div className="flex items-baseline gap-1">
                                        <span className="text-3xl font-bold text-gray-900">{plan.monthly_price ?? '—'}</span>
                                        {plan.monthly_price && <span className="text-sm text-gray-500">/mo</span>}
                                    </div>
                                    {plan.yearly_price && (
                                        <p className="text-sm text-gray-500 mt-1">
                                            {plan.yearly_price}/yr
                                            {plan.yearly_savings_percent > 0 && (
                                                <span className="text-green-600 font-medium ml-1">Save {plan.yearly_savings_percent}%</span>
                                            )}
                                        </p>
                                    )}
                                </div>
                                <ul className="space-y-2 mb-6">
                                    <li className="flex items-center gap-2 text-sm text-gray-600">
                                        <svg className="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                                        Up to {plan.product_limit} products
                                    </li>
                                    <li className="flex items-center gap-2 text-sm text-gray-600">
                                        <svg className="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                                        Up to {plan.staff_limit} staff
                                    </li>
                                    <li className="flex items-center gap-2 text-sm text-gray-600">
                                        <svg className="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                                        {plan.storage_limit} MB storage
                                    </li>
                                </ul>
                                {!plan.is_current && (
                                    <button
                                        disabled
                                        className="w-full px-4 py-2.5 bg-gray-100 text-gray-400 rounded-lg text-sm font-medium cursor-not-allowed"
                                        title="Upgrade functionality coming next"
                                    >
                                        Upgrade Coming Next
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                <div className="bg-white rounded-xl border border-gray-200">
                    <div className="px-6 py-4 border-b border-gray-100">
                        <h3 className="text-base font-semibold text-gray-900">Coming Next</h3>
                    </div>
                    <div className="p-6">
                        <p className="text-sm text-gray-500">
                            One-click plan upgrades, proration calculations, and automated billing adjustments will be available in a future update.
                        </p>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
