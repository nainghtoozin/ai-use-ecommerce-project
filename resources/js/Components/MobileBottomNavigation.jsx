import { Link, usePage } from '@inertiajs/react';

function storeBase() {
    const match = window.location.pathname.match(/^\/store\/([^/]+)/);
    return match ? `/store/${match[1]}` : '';
}

export default function MobileBottomNavigation() {
    const base = storeBase();
    const path = window.location.pathname;

    const items = [
        { label: 'Home', href: `${base}/`, icon: 'bi-house', active: path === `${base}/` || path === base },
        { label: 'Products', href: `${base}/products`, icon: 'bi-grid', active: path.startsWith(`${base}/products`) },
        { label: 'My Orders', href: `${base}/customer/orders`, icon: 'bi-clipboard', active: path.startsWith(`${base}/customer/orders`) },
        { label: 'Account', href: `${base}/customer/account`, icon: 'bi-person', active: path.startsWith(`${base}/customer/account`) || path.startsWith(`${base}/customer/profile`) },
    ];

    return (
        <nav className="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 pb-[env(safe-area-inset-bottom)]">
            <div className="flex items-center justify-around py-2">
                {items.map((item) => (
                    <Link key={item.label} href={item.href} className={`flex flex-col items-center gap-0.5 px-2 py-1 text-[10px] ${item.active ? 'text-blue-600' : 'text-gray-500'}`}>
                        <i className={`bi ${item.icon} text-lg`} />
                        <span>{item.label}</span>
                    </Link>
                ))}
            </div>
        </nav>
    );
}