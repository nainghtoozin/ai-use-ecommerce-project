import { Link, usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

export default function GuestLayout({ children }) {
    const { website_info } = usePage().props;
    const logoUrl = assetUrl(website_info?.logo);
    const siteName = website_info?.site_name || website_info?.name || 'My Store';

    return (
        <div className="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-50 dark:bg-gray-950">
            <div className="mb-6">
                <Link href="/" className="flex items-center gap-3">
                    {logoUrl && <img src={logoUrl} alt={siteName} className="h-10 w-auto" />}
                    <span className="text-2xl font-bold text-gray-900 dark:text-gray-100">{siteName}</span>
                </Link>
            </div>

            <div className="w-full sm:max-w-md px-6 py-6 bg-white dark:bg-gray-900 shadow-lg rounded-xl border border-gray-100 dark:border-gray-800">
                {children}
            </div>
        </div>
    );
}
