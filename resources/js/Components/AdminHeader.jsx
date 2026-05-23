import { usePage } from '@inertiajs/react';
import NotificationBell from '@/Components/NotificationBell';

export default function AdminHeader() {
    const { auth, url } = usePage().props;

    const getPageTitle = () => {
        const path = url || '/';
        if (path.includes('dashboard')) return 'Dashboard';
        if (path.includes('products')) return 'Products';
        if (path.includes('categories')) return 'Categories';
        if (path.includes('orders')) return 'Orders';
        if (path.includes('promotions')) return 'Promotions';
        if (path.includes('payment-methods')) return 'Payment Methods';
        if (path.includes('cities')) return 'Cities';
        if (path.includes('townships')) return 'Townships';
        if (path.includes('website-info')) return 'Website Info';
        if (path.includes('settings')) return 'Settings';
        return 'Admin Panel';
    };

    return (
        <header className="bg-white border-b border-gray-200 h-14 lg:h-16 flex items-center px-4 lg:px-6 sticky top-0 z-20 shadow-sm">
            <div className="flex items-center justify-between w-full">
                <div className="flex items-center gap-3">
                    <div className="lg:hidden w-10"></div>
                    <div>
                        <h1 className="text-base lg:text-lg font-semibold text-gray-900">{getPageTitle()}</h1>
                        <p className="text-xs text-gray-500 hidden lg:block">Manage your store</p>
                    </div>
                </div>
                <div className="flex items-center gap-2 lg:gap-3">
                    <div className="hidden md:flex items-center gap-2 px-3 py-1.5 bg-gray-50 rounded-lg">
                        <span className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        <span className="text-xs text-gray-600">Online</span>
                    </div>
                    <NotificationBell isAdmin={true} />
                    <div className="flex items-center gap-2 border-l border-gray-200 pl-2 lg:pl-3">
                        <div className="w-7 lg:w-8 h-7 lg:h-8 text-white rounded-lg flex items-center justify-center text-xs font-bold shadow-sm" style={{ background: 'linear-gradient(135deg, var(--theme-color, #3B82F6), color-mix(in srgb, var(--theme-color, #3B82F6) 80%, black))' }}>
                            {auth?.user?.name?.charAt(0).toUpperCase()}
                        </div>
                        <div className="hidden lg:block">
                            <span className="text-sm font-medium text-gray-700 block">{auth?.user?.name}</span>
                            <span className="text-xs text-gray-500">Administrator</span>
                        </div>
                    </div>
                </div>
            </div>
        </header>
    );
}