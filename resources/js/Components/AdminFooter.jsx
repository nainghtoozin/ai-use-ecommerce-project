import { usePage } from '@inertiajs/react';

export default function AdminFooter() {
    const { website_info, platform_setting, auth } = usePage().props;
    const isSuperAdmin = auth?.user?.is_superadmin;
    const brandName = isSuperAdmin ? (platform_setting?.site_name || 'SuperAdmin') : (website_info?.site_name || 'My Store');
    const supportEmail = isSuperAdmin ? (platform_setting?.support_email || null) : null;
    const year = new Date().getFullYear();

    return (
        <footer className="bg-white border-t border-gray-200 py-4 px-4 lg:px-6">
            <div className="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-2 text-xs text-gray-500">
                <div className="flex items-center gap-3">
                    <span>&copy; {year} {brandName}. All rights reserved.</span>
                    {supportEmail && (
                        <>
                            <span className="text-gray-300">|</span>
                            <a href={`mailto:${supportEmail}`} className="hover:text-gray-700 transition-colors">{supportEmail}</a>
                        </>
                    )}
                </div>
                <span className="text-gray-400">Admin Dashboard</span>
            </div>
        </footer>
    );
}