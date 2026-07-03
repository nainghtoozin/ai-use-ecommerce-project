import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function AdminBillingSubscription({ subscription }) {
    return (
        <AdminLayout>
            <Head title="Subscription" />

            <div className="p-6 lg:p-8 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Subscription</h1>
                        <p className="text-sm text-gray-500 mt-1">View and manage your subscription details</p>
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
                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="px-6 py-4 border-b border-gray-100">
                            <h3 className="text-base font-semibold text-gray-900">Subscription Details</h3>
                        </div>
                        <div className="p-6">
                            <dl className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Status</dt>
                                    <dd className={`mt-1 text-sm font-semibold ${
                                        subscription.status === 'active' ? 'text-green-600' :
                                        subscription.status === 'trialing' ? 'text-blue-600' :
                                        subscription.status === 'past_due' ? 'text-yellow-600' :
                                        subscription.status === 'expired' ? 'text-red-600' :
                                        'text-gray-600'
                                    }`}>{subscription.status}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Plan</dt>
                                    <dd className="mt-1 text-sm font-semibold text-gray-900">{subscription.plan?.name || 'N/A'}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Billing Interval</dt>
                                    <dd className="mt-1 text-sm font-semibold text-gray-900 capitalize">{subscription.billing_interval || 'N/A'}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Price</dt>
                                    <dd className="mt-1 text-sm font-semibold text-gray-900">{subscription.price || 'N/A'}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Started At</dt>
                                    <dd className="mt-1 text-sm font-semibold text-gray-900">{subscription.starts_at || 'N/A'}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Expires At</dt>
                                    <dd className="mt-1 text-sm font-semibold text-gray-900">{subscription.expires_at || 'N/A'}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                )}

                <div className="bg-white rounded-xl border border-gray-200">
                    <div className="px-6 py-4 border-b border-gray-100">
                        <h3 className="text-base font-semibold text-gray-900">Coming Next</h3>
                    </div>
                    <div className="p-6">
                        <p className="text-sm text-gray-500">
                            Detailed subscription management — including auto-renewal toggles, payment method assignment, and invoice history — will be available in a future update.
                        </p>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
