import { useState, useEffect, useCallback } from 'react';
import { Head, Link, usePage, router } from '@inertiajs/react';
import axios from 'axios';
import { assetUrl } from '@/Utils/helpers';

export default function AppLayout({ children, header = null }) {
    const { props, url } = usePage();
    const { auth, flash, website_info, cart } = props;
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [showNotifications, setShowNotifications] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const fetchNotifications = useCallback(async () => {
        try {
            const res = await axios.get('/notifications');
            setUnreadCount(res.data.unread_count);
            setNotifications(res.data.notifications.slice(0, 5));
        } catch (err) {
            console.error('Failed to fetch notifications:', err);
        }
    }, []);

    useEffect(() => {
        if (auth?.user) {
            fetchNotifications();
            window.Echo.private(`chat.${auth.user.id}`)
                .listen('.message.sent', () => setUnreadCount((p) => p + 1))
                .listen('.typing', () => {});
            return () => window.Echo.leave(`chat.${auth.user.id}`);
        }
    }, [auth?.user?.id, fetchNotifications]);

    const markAsRead = async (id) => {
        try {
            await axios.patch(`/notifications/${id}/read`);
            fetchNotifications();
        } catch (err) {
            console.error(err);
        }
    };

    const markAllAsRead = async () => {
        try {
            await axios.patch('/notifications/read-all');
            fetchNotifications();
        } catch (err) {
            console.error(err);
        }
    };

    const logout = () => router.post('/logout');

    const isAdmin = auth?.user?.is_admin;
    const logoUrl = assetUrl(website_info?.logo);
    const siteName = website_info?.site_name || 'My Store';

    const adminMenu = [
        { label: 'Dashboard', href: '/admin/dashboard', icon: 'bi-speedometer2' },
        { label: 'Products', href: '/admin/products', icon: 'bi-box-seam' },
        { label: 'Categories', href: '/admin/categories', icon: 'bi-tags' },
        { label: 'Promotions', href: '/admin/promotions', icon: 'bi-megaphone' },
        { label: 'Orders', href: '/admin/orders', icon: 'bi-cart3' },
        { label: 'Payment Methods', href: '/admin/payment-methods', icon: 'bi-credit-card' },
        { label: 'Cities', href: '/admin/cities', icon: 'bi-building' },
        { label: 'Townships', href: '/admin/townships', icon: 'bi-pin-map' },
        { label: 'Website Info', href: '/admin/website-info/edit', icon: 'bi-globe' },
        { label: 'Settings', href: '/admin/settings', icon: 'bi-headset' },
    ];

    const clientMenu = [
        { label: 'Home', href: '/', icon: 'bi-house-door' },
        { label: 'Products', href: '/client/dashboard', icon: 'bi-grid' },
        { label: 'Cart', href: '/cart', icon: 'bi-cart3', badge: cart?.count || 0 },
        { label: 'My Orders', href: '/orders', icon: 'bi-receipt' },
        { label: 'Checkout', href: '/checkout', icon: 'bi-bag-check' },
    ];

    const menu = isAdmin ? adminMenu : clientMenu;

    function isActive(href) {
        if (href === '/' && url === '/') return true;
        if (href !== '/') {
            const hrefPath = href.replace(/\/+$/, '');
            const urlPath = url.replace(/\/+$/, '');
            if (urlPath === hrefPath) return true;
            if (urlPath.startsWith(hrefPath + '/')) return true;
        }
        return false;
    }

    return (
        <>
            <Head title={header?.props?.title || siteName} />

            {flash?.success && (
                <div className="fixed top-4 right-4 z-50 bg-green-500 text-white px-4 py-2 rounded shadow-lg">
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="fixed top-4 right-4 z-50 bg-red-500 text-white px-4 py-2 rounded shadow-lg">
                    {flash.error}
                </div>
            )}

            <div className="min-h-screen flex">
                {/* Admin Sidebar */}
                {isAdmin && (
                    <>
                        {/* Mobile sidebar toggle */}
                        <div className="lg:hidden fixed top-4 left-4 z-50">
                            <button
                                onClick={() => setSidebarOpen(!sidebarOpen)}
                                className="p-2 bg-white rounded-lg shadow border border-gray-200"
                            >
                                <i className={`bi ${sidebarOpen ? 'bi-x-lg' : 'bi-list'} text-xl`}></i>
                            </button>
                        </div>

                        {/* Sidebar overlay */}
                        {sidebarOpen && (
                            <div
                                className="lg:hidden fixed inset-0 bg-black/50 z-40"
                                onClick={() => setSidebarOpen(false)}
                            ></div>
                        )}

                        {/* Sidebar */}
                        <aside
                            className={`
                                fixed lg:static inset-y-0 left-0 z-40
                                w-64 bg-gray-900 text-white flex flex-col
                                transform transition-transform duration-200 ease-in-out
                                ${sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}
                            `}
                        >
                            {/* Logo */}
                            <div className="h-16 flex items-center px-6 border-b border-gray-800">
                                {logoUrl && (
                                    <img src={logoUrl} alt={siteName} className="h-8 w-auto mr-3" />
                                )}
                                <span className="text-lg font-bold truncate">{siteName}</span>
                            </div>

                            {/* Menu */}
                            <nav className="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                                {menu.map((item) => (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        onClick={() => setSidebarOpen(false)}
                                        className={`
                                            flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                                            ${isActive(item.href)
                                                ? 'bg-blue-600 text-white'
                                                : 'text-gray-300 hover:bg-gray-800 hover:text-white'}
                                        `}
                                    >
                                        <i className={`bi ${item.icon} text-lg`}></i>
                                        <span>{item.label}</span>
                                        {item.badge > 0 && (
                                            <span className="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                                {item.badge}
                                            </span>
                                        )}
                                    </Link>
                                ))}
                            </nav>

                            {/* User */}
                            <div className="p-4 border-t border-gray-800">
                                <div className="flex items-center gap-3">
                                    <div className="w-8 h-8 bg-gray-700 rounded-full flex items-center justify-center text-sm font-bold">
                                        {auth?.user?.name?.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium truncate">{auth?.user?.name}</p>
                                        <p className="text-xs text-gray-400 truncate">{auth?.user?.email}</p>
                                    </div>
                                </div>
                                <div className="mt-3 flex gap-2">
                                    <Link href="/profile" className="flex-1 text-center px-3 py-1.5 text-xs bg-gray-800 rounded hover:bg-gray-700 transition-colors">
                                        Profile
                                    </Link>
                                    <button onClick={logout} className="flex-1 text-center px-3 py-1.5 text-xs bg-red-600/20 text-red-400 rounded hover:bg-red-600/30 transition-colors">
                                        Logout
                                    </button>
                                </div>
                            </div>
                        </aside>
                    </>
                )}

                {/* Main content area */}
                <div className="flex-1 flex flex-col min-w-0">
                    {/* Top Navbar (non-admin) */}
                    {!isAdmin && (
                        <nav className="bg-white border-b border-gray-200 sticky top-0 z-30">
                            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                                <div className="flex justify-between h-16">
                                    <div className="flex items-center">
                                        <Link href="/" className="flex items-center gap-2">
                                            {logoUrl && <img src={logoUrl} alt={siteName} className="h-8 w-auto" />}
                                            <span className="text-xl font-bold text-gray-800">{siteName}</span>
                                        </Link>
                                    </div>

                                    <div className="hidden sm:flex items-center gap-2">
                                        {clientMenu.map((item) => (
                                            <Link
                                                key={item.href}
                                                href={item.href}
                                                className={`
                                                    flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg transition-colors
                                                    ${isActive(item.href)
                                                        ? 'bg-blue-50 text-blue-700'
                                                        : 'text-gray-700 hover:bg-gray-100'}
                                                `}
                                            >
                                                <i className={`bi ${item.icon}`}></i>
                                                <span>{item.label}</span>
                                                {item.badge > 0 && (
                                                    <span className="bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                                        {item.badge}
                                                    </span>
                                                )}
                                            </Link>
                                        ))}
                                    </div>

                                    <div className="flex items-center gap-3">
                                        {auth?.user ? (
                                            <>
                                                {/* Notifications */}
                                                <div className="relative">
                                                    <button
                                                        onClick={() => setShowNotifications(!showNotifications)}
                                                        className="p-2 text-gray-600 hover:bg-gray-100 rounded-lg relative"
                                                    >
                                                        <i className="bi bi-bell text-lg"></i>
                                                        {unreadCount > 0 && (
                                                            <span className="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">
                                                                {unreadCount}
                                                            </span>
                                                        )}
                                                    </button>
                                                    {showNotifications && (
                                                        <div className="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                                            <div className="p-3 border-b flex justify-between items-center">
                                                                <span className="font-semibold">Notifications</span>
                                                                <button onClick={markAllAsRead} className="text-sm text-blue-600 hover:underline">
                                                                    Mark all read
                                                                </button>
                                                            </div>
                                                            <div className="max-h-64 overflow-y-auto">
                                                                {notifications.length === 0 ? (
                                                                    <p className="p-4 text-gray-500 text-center text-sm">No notifications</p>
                                                                ) : (
                                                                    notifications.map((n) => (
                                                                        <div
                                                                            key={n.id}
                                                                            onClick={() => markAsRead(n.id)}
                                                                            className="p-3 border-b hover:bg-gray-50 cursor-pointer"
                                                                        >
                                                                            <p className="text-sm">{n.data.message || n.data.title}</p>
                                                                            <p className="text-xs text-gray-400 mt-1">{n.created_at}</p>
                                                                        </div>
                                                                    ))
                                                                )}
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Chat */}
                                                <Link href="/chat" className="p-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                                                    <i className="bi bi-chat-dots text-lg"></i>
                                                </Link>

                                                {/* User Menu */}
                                                <div className="relative">
                                                    <button
                                                        onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                                                        className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg"
                                                    >
                                                        <span>{auth.user.name}</span>
                                                        <i className="bi bi-chevron-down text-xs"></i>
                                                    </button>
                                                    {mobileMenuOpen && (
                                                        <div className="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                                            <Link href="/profile" className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                                Profile
                                                            </Link>
                                                            <button onClick={logout} className="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                                                Logout
                                                            </button>
                                                        </div>
                                                    )}
                                                </div>
                                            </>
                                        ) : (
                                            <>
                                                <Link href="/login" className="px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">
                                                    Login
                                                </Link>
                                                {website_info?.allow_registration !== false && (
                                                    <Link href="/register" className="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                                        Register
                                                    </Link>
                                                )}
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </nav>
                    )}

                    {/* Page Content */}
                    <main className="flex-1">
                        {header && (
                            <header className="bg-white shadow">
                                <div className="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                                    {header}
                                </div>
                            </header>
                        )}
                        {children}
                    </main>

                    {/* Footer */}
                    <footer className="bg-gray-800 text-white py-8 mt-auto">
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
                                <div>
                                    <h3 className="text-lg font-bold mb-2">{siteName}</h3>
                                    <p className="text-gray-400 text-sm">{website_info?.about_description || 'Your trusted online store'}</p>
                                </div>
                                <div>
                                    <h3 className="text-lg font-bold mb-2">Quick Links</h3>
                                    <ul className="space-y-2 text-sm">
                                        <li><a href="/client/about" className="text-gray-400 hover:text-white">About Us</a></li>
                                        <li><a href="/client/contact" className="text-gray-400 hover:text-white">Contact</a></li>
                                        <li><a href="/client/faq" className="text-gray-400 hover:text-white">FAQ</a></li>
                                        <li><a href="/client/privacy" className="text-gray-400 hover:text-white">Privacy Policy</a></li>
                                        <li><a href="/client/terms" className="text-gray-400 hover:text-white">Terms of Service</a></li>
                                    </ul>
                                </div>
                                <div>
                                    <h3 className="text-lg font-bold mb-2">Contact</h3>
                                    {website_info?.phone && <p className="text-gray-400 text-sm"><i className="bi bi-telephone"></i> {website_info.phone}</p>}
                                    {website_info?.email && <p className="text-gray-400 text-sm"><i className="bi bi-envelope"></i> {website_info.email}</p>}
                                    {website_info?.address && <p className="text-gray-400 text-sm"><i className="bi bi-geo-alt"></i> {website_info.address}</p>}
                                </div>
                                <div>
                                    <h3 className="text-lg font-bold mb-2">Follow Us</h3>
                                    <div className="flex gap-3">
                                        {website_info?.facebook_link && <a href={website_info.facebook_link} target="_blank" rel="noopener noreferrer" className="text-gray-400 hover:text-white"><i className="bi bi-facebook text-xl"></i></a>}
                                        {website_info?.whatsapp_link && <a href={website_info.whatsapp_link} target="_blank" rel="noopener noreferrer" className="text-gray-400 hover:text-white"><i className="bi bi-whatsapp text-xl"></i></a>}
                                        {website_info?.telegram_link && <a href={website_info.telegram_link} target="_blank" rel="noopener noreferrer" className="text-gray-400 hover:text-white"><i className="bi bi-telegram text-xl"></i></a>}
                                        {website_info?.viber_link && <a href={website_info.viber_link} target="_blank" rel="noopener noreferrer" className="text-gray-400 hover:text-white"><i className="bi bi-chat-square-text text-xl"></i></a>}
                                    </div>
                                </div>
                            </div>
                            <div className="border-t border-gray-700 mt-6 pt-4 text-center text-gray-400 text-sm">
                                &copy; {new Date().getFullYear()} {siteName}. All rights reserved.
                            </div>
                        </div>
                    </footer>
                </div>
            </div>
        </>
    );
}
