import { usePage } from '@inertiajs/react';

export default function AdminFooter() {
    const { website_info } = usePage().props;
    const siteName = website_info?.name || 'Electronics Store';
    const year = new Date().getFullYear();

    return (
        <footer className="bg-white border-t border-gray-200 py-4 px-4 lg:px-6">
            <div className="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-2 text-xs text-gray-500">
                <span>&copy; {year} {siteName}. All rights reserved.</span>
                <span className="text-gray-400">Admin Dashboard</span>
            </div>
        </footer>
    );
}