import { Head, usePage, router } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

export default function Suspended() {
    const { website_info, auth } = usePage().props;
    const logoUrl = assetUrl(website_info?.logo);
    const siteName = website_info?.site_name || website_info?.name || 'My Store';
    const supportEmail = website_info?.support_email || 'support@example.com';

    const handleLogout = () => {
        router.post(route('logout'));
    };

    return (
        <>
            <Head title="Store Suspended" />
            <div className="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-50">
                <div className="mb-6">
                    <div className="flex items-center gap-3">
                        {logoUrl && <img src={logoUrl} alt={siteName} className="h-10 w-auto" />}
                        <span className="text-2xl font-bold text-gray-900">{siteName}</span>
                    </div>
                </div>

                <div className="w-full sm:max-w-lg px-6 py-8 bg-white shadow-lg rounded-xl border border-gray-100">
                    <div className="text-center">
                        <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                        </div>

                        <h1 className="text-2xl font-bold text-gray-900 mb-3">
                            Store Suspended
                        </h1>

                        <p className="text-gray-600 mb-4 leading-relaxed">
                            Your store has been suspended due to prolonged subscription inactivity.
                            All admin features are temporarily restricted.
                        </p>

                        <div className="bg-red-50 rounded-lg p-4 mb-6 text-left text-sm text-gray-600 space-y-2">
                            <p className="font-medium text-gray-800">What this means:</p>
                            <ul className="list-disc list-inside space-y-1">
                                <li>Your storefront is not accessible to customers</li>
                                <li>Order processing and product management are disabled</li>
                                <li>All admin features are temporarily restricted</li>
                                <li>Your data remains securely stored and preserved</li>
                            </ul>
                        </div>

                        <div className="bg-gray-50 rounded-lg p-4 mb-6 text-left text-sm text-gray-600">
                            <p className="font-medium text-gray-800 mb-1">Contact Support</p>
                            <p className="text-gray-500">
                                If you believe this is an error, please contact us at{' '}
                                <a href={`mailto:${supportEmail}`} className="text-blue-600 hover:underline">
                                    {supportEmail}
                                </a>
                            </p>
                        </div>

                        <div className="mt-6 pt-6 border-t border-gray-100">
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
