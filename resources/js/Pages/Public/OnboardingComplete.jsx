import { Head, Link, usePage } from '@inertiajs/react';

export default function OnboardingComplete() {
    const { storeName, storeSlug, storeUrl, adminLoginUrl, subscriptionPlan, status } = usePage().props;

    return (
        <>
            <Head title="Store Activated" />

            <div className="min-h-screen bg-gradient-to-br from-green-50 via-white to-emerald-50 flex flex-col">
                <div className="max-w-lg mx-auto px-4 py-16 sm:py-24 text-center flex-1 flex flex-col justify-center">
                    <div className="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-6">
                        <svg className="w-10 h-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>

                    <h1 className="text-3xl font-extrabold text-gray-900 sm:text-4xl">
                        Your Store is Live!
                    </h1>

                    <p className="mt-3 text-lg text-gray-600">
                        <span className="font-semibold text-gray-900">{storeName}</span> has been activated successfully.
                    </p>

                    <div className="mt-8 bg-white rounded-2xl shadow-sm border border-gray-200 p-6 text-left space-y-4">
                        <div>
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Store Name</p>
                            <p className="mt-1 text-sm text-gray-900 font-medium">{storeName}</p>
                        </div>

                        <div className="border-t border-gray-100 pt-4">
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Store URL</p>
                            <a href={storeUrl} target="_blank" rel="noopener noreferrer"
                                className="mt-1 text-sm text-indigo-600 hover:text-indigo-800 underline break-all font-mono">
                                {storeUrl}
                            </a>
                        </div>

                        <div className="border-t border-gray-100 pt-4">
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Admin Login</p>
                            <a href={adminLoginUrl} className="mt-1 text-sm text-indigo-600 hover:text-indigo-800 underline break-all font-mono">
                                {adminLoginUrl}
                            </a>
                        </div>

                        <div className="border-t border-gray-100 pt-4 flex justify-between items-center">
                            <div>
                                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Plan</p>
                                <p className="mt-1 text-sm text-gray-900 font-medium">{subscriptionPlan}</p>
                            </div>
                            <div className="text-right">
                                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</p>
                                <span className="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    {status}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
                        <a
                            href={storeUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center justify-center px-8 py-3 rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all"
                        >
                            Visit Store
                        </a>
                        <a
                            href={adminLoginUrl}
                            className="inline-flex items-center justify-center px-8 py-3 rounded-xl text-sm font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-all"
                        >
                            Login to Admin
                        </a>
                    </div>
                </div>
            </div>
        </>
    );
}
