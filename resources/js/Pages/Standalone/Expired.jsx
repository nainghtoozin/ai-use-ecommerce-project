import { Head, usePage, router } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

export default function Expired() {
    const { website_info, store_slug, auth } = usePage().props;
    const logoUrl = assetUrl(website_info?.logo);
    const siteName = website_info?.site_name || website_info?.name || 'My Store';
    const sub = auth?.user?.subscription;
    const daysSince = sub?.days_since_expiry || 0;
    const planName = sub?.plan_name || 'Current';

    const isStorefront = !!store_slug;
    const routePrefix = isStorefront ? 'storefront.admin' : 'admin';
    const routeParams = isStorefront ? { store_slug } : {};

    const handleRenew = () => {
        router.post(route(`${routePrefix}.billing.renew`, routeParams));
    };

    const handleLogout = () => {
        router.post(route('logout'));
    };

    const billingUrl = route(`${routePrefix}.billing`, routeParams);

    return (
        <>
            <Head title="Subscription Expired" />
            <div className="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-50">
                <div className="mb-6">
                    <div className="flex items-center gap-3">
                        {logoUrl && <img src={logoUrl} alt={siteName} className="h-10 w-auto" />}
                        <span className="text-2xl font-bold text-gray-900">{siteName}</span>
                    </div>
                </div>

                <div className="w-full sm:max-w-lg px-6 py-8 bg-white shadow-lg rounded-xl border border-gray-100">
                    <div className="text-center">
                        <div className="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg className="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m0 0v2m0-2h2m-2 0H9m3-4V5a3 3 0 00-3-3H8a3 3 0 00-3 3v5m2 0h10a2 2 0 012 2v4a2 2 0 01-2 2H7a2 2 0 01-2-2v-4a2 2 0 012-2z" />
                            </svg>
                        </div>

                        <h1 className="text-2xl font-bold text-gray-900 mb-3">
                            Store Safely Locked
                        </h1>

                        <p className="text-gray-600 mb-4 leading-relaxed">
                            Your <strong>{planName}</strong> plan subscription has expired
                            {daysSince > 0 ? ` (${daysSince} day${daysSince !== 1 ? 's' : ''} ago)` : ''}.
                            Your store data is safely preserved and no information has been lost.
                            Renew your subscription to restore full access.
                        </p>

                        <div className="bg-amber-50 rounded-lg p-4 mb-6 text-left text-sm text-gray-600 space-y-2">
                            <p className="font-medium text-gray-800">What this means:</p>
                            <ul className="list-disc list-inside space-y-1">
                                <li>All your products, orders, and data are securely stored</li>
                                <li>Your storefront is temporarily unavailable to customers</li>
                                <li>You can still manage your billing and subscription</li>
                                <li>Renew within the grace period to avoid suspension</li>
                            </ul>
                        </div>

                        <div className="flex flex-col sm:flex-row gap-3 justify-center">
                            <button
                                type="button"
                                onClick={handleRenew}
                                className="inline-flex items-center justify-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                Renew Subscription
                            </button>

                            <a
                                href={billingUrl}
                                className="inline-flex items-center justify-center px-6 py-3 bg-white text-gray-700 font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors"
                            >
                                Upgrade Plan
                            </a>
                        </div>

                        <div className="mt-8 pt-6 border-t border-gray-100">
                            <button
                                type="button"
                                onClick={handleLogout}
                                className="text-sm text-gray-500 hover:text-gray-700 underline"
                            >
                                Sign out
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
