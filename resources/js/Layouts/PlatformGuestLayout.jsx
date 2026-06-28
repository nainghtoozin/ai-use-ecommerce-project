import { Link, usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

export default function PlatformGuestLayout({ children }) {
    const { platform_setting } = usePage().props;
    const logoUrl = assetUrl(platform_setting?.site_logo);
    const siteName = platform_setting?.site_name || 'My Store';

    return (
        <div className="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-50">
            <div className="mb-6">
                <Link href="/" className="flex items-center gap-3">
                    {logoUrl && <img src={logoUrl} alt={siteName} className="h-10 w-auto" />}
                    <span className="text-2xl font-bold text-gray-900">{siteName}</span>
                </Link>
            </div>

            <div className="w-full sm:max-w-md px-6 py-6 bg-white shadow-lg rounded-xl border border-gray-100">
                {children}
            </div>
        </div>
    );
}
