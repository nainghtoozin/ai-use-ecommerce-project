import { useState, useEffect, useMemo } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

const STORAGE_PREFIX = 'admin_sidebar_section_';

export default function AdminSidebar() {
    const { props, url } = usePage();
    const { auth, website_info } = props;
    const userPermissions = auth?.user?.permissions;
    const can = (perm) => userPermissions?.includes(perm);
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(false);

    const logoUrl = assetUrl(website_info?.logo);
    const siteName = website_info?.site_name || 'My Store';

    const menuSections = useMemo(() => [
        {
            title: 'Main',
            items: [
                ...(can('dashboard.view') ? [{ label: 'Dashboard', href: '/admin/dashboard', icon: 'bi-grid-1x2' }] : []),
                ...(can('products.view') ? [{ label: 'Products', href: '/admin/products', icon: 'bi-box-seam' }] : []),
                ...(can('categories.view') ? [{ label: 'Categories', href: '/admin/categories', icon: 'bi-tags' }] : []),
                { label: 'Promotions', href: '/admin/promotions', icon: 'bi-megaphone' },
                { label: 'Reports', href: '/admin/promotions/reports', icon: 'bi-bar-chart-line' },
            ]
        },
        {
            title: 'Orders',
            items: [
                ...(can('orders.view') ? [{ label: 'Orders', href: '/admin/orders', icon: 'bi-cart3' }] : []),
                ...(can('payments.view') ? [{ label: 'Payment Methods', href: '/admin/payment-methods', icon: 'bi-credit-card' }] : []),
            ]
        },
        {
            title: 'Locations',
            items: [
                { label: 'Cities', href: '/admin/cities', icon: 'bi-building' },
                { label: 'Townships', href: '/admin/townships', icon: 'bi-pin-map' },
            ]
        },
        {
            title: 'Users',
            items: [
                ...(can('users.view') ? [{ label: 'Users', href: '/admin/users', icon: 'bi-people' }] : []),
                ...(can('roles.view') ? [{ label: 'Roles', href: '/admin/roles', icon: 'bi-shield-check' }] : []),
                ...(can('activity-logs.view') ? [{ label: 'Activity Logs', href: '/admin/activity-logs', icon: 'bi-clock-history' }] : []),
            ]
        },
        {
            title: 'System',
            items: [
                { label: 'Notifications', href: '/admin/notifications', icon: 'bi-bell' },
                { label: 'Website Info', href: '/admin/website-info/edit', icon: 'bi-globe' },
                { label: 'Notification Settings', href: '/admin/settings/notifications', icon: 'bi-bell-fill' },
                { label: 'Telegram Bot', href: '/admin/settings/telegram', icon: 'bi-telegram' },
                { label: 'Settings', href: '/admin/settings', icon: 'bi-gear' },
            ]
        }
    ], [userPermissions]);

    function isActive(href) {
        if (href === '/') return url === '/';
        const hrefPath = href.replace(/\/+$/, '');
        const urlPath = url.replace(/\/+$/, '');
        if (urlPath === hrefPath) return true;
        if (urlPath.startsWith(hrefPath + '/')) return true;
        return false;
    }

    const [openSections, setOpenSections] = useState({});

    useEffect(() => {
        setOpenSections(prev => {
            const next = { ...prev };
            menuSections.forEach(section => {
                if (next[section.title] === undefined) {
                    const saved = localStorage.getItem(STORAGE_PREFIX + section.title);
                    next[section.title] = saved !== null ? saved === 'true' : section.title === 'Main';
                }
                const sectionHasActive = section.items.some(item => isActive(item.href));
                if (sectionHasActive) {
                    next[section.title] = true;
                }
            });
            return next;
        });
    }, [url, menuSections]);

    const toggleSection = (title) => {
        setOpenSections(prev => {
            const next = { ...prev, [title]: !prev[title] };
            localStorage.setItem(STORAGE_PREFIX + title, next[title]);
            return next;
        });
    };

    const logout = () => router.post('/logout');

    return (
        <>
            <style>{`
                .sidebar-scrollbar::-webkit-scrollbar {
                    width: 4px;
                }
                .sidebar-scrollbar::-webkit-scrollbar-track {
                    background: transparent;
                }
                .sidebar-scrollbar::-webkit-scrollbar-thumb {
                    background: #475569;
                    border-radius: 9999px;
                }
                .sidebar-scrollbar::-webkit-scrollbar-thumb:hover {
                    background: #64748b;
                }
            `}</style>

            <button
                onClick={() => setSidebarOpen(!sidebarOpen)}
                className="lg:hidden fixed top-14 left-3 z-50 p-2.5 bg-slate-800 rounded-lg shadow-lg hover:bg-slate-700 transition-colors"
                style={{ marginTop: '0px' }}
            >
                <i className={`bi ${sidebarOpen ? 'bi-x-lg' : 'bi-list'} text-lg text-white`}></i>
            </button>

            {sidebarOpen && (
                <div className="lg:hidden fixed inset-0 bg-black/60 z-40 backdrop-blur-sm" onClick={() => setSidebarOpen(false)}></div>
            )}

            <aside
                className={`fixed lg:sticky top-0 left-0 z-40 h-screen flex flex-col bg-slate-900 text-white transition-all duration-300 ${collapsed ? 'w-20' : 'w-64'
                    } ${sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
                    }`}
                style={{ paddingTop: '0px' }}
            >
                <div className={`h-16 flex items-center ${collapsed ? 'justify-center px-2' : 'px-5'} border-b border-slate-800 flex-shrink-0`}>
                    {collapsed ? (
                        <div className="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                            <i className="bi bi-shop text-white text-lg"></i>
                        </div>
                    ) : (
                        <>
                            {logoUrl ? (
                                <img src={logoUrl} alt={siteName} className="h-8 w-auto" />
                            ) : (
                                <div className="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                                    <i className="bi bi-shop text-white text-lg"></i>
                                </div>
                            )}
                            <span className="ml-3 text-lg font-bold truncate">{siteName}</span>
                        </>
                    )}
                </div>

                <div className="hidden lg:block absolute -right-3 top-20 z-50">
                    <button
                        onClick={() => setCollapsed(!collapsed)}
                        className="w-6 h-6 bg-slate-700 hover:bg-slate-600 rounded-full flex items-center justify-center shadow-lg border border-slate-600 transition-colors"
                    >
                        <i className={`bi bi-chevron-${collapsed ? 'right' : 'left'} text-xs`}></i>
                    </button>
                </div>

                <nav className="flex-1 px-3 py-4 overflow-y-auto overflow-x-hidden sidebar-scrollbar">
                    {menuSections.filter(s => s.items.length > 0).map((section, sectionIdx) => {
                        const isOpen = openSections[section.title] ?? (section.title === 'Main');
                        const sectionHasActive = section.items.some(item => isActive(item.href));

                        return (
                            <div key={section.title} className={sectionIdx > 0 ? 'mt-6 pt-6 border-t border-slate-800' : ''}>
                                {!collapsed && (
                                    <button
                                        onClick={() => toggleSection(section.title)}
                                        className={`w-full flex items-center justify-between px-3 mb-2 text-xs font-semibold uppercase tracking-wider transition-colors ${sectionHasActive
                                            ? 'text-blue-400'
                                            : 'text-slate-500 hover:text-slate-300'
                                            }`}
                                    >
                                        <span>{section.title}</span>
                                        <i className={`bi bi-chevron-${isOpen ? 'down' : 'right'} text-xs transition-transform duration-200`}></i>
                                    </button>
                                )}
                                <div className={`grid transition-[grid-template-rows] duration-300 ease-in-out ${isOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}>
                                    <div className="overflow-hidden">
                                        <div className="space-y-1">
                                            {section.items.map((item) => {
                                                const active = isActive(item.href);
                                                return (
                                                    <Link
                                                        key={item.href}
                                                        href={item.href}
                                                        onClick={() => setSidebarOpen(false)}
                                                        className={`flex items-center ${collapsed ? 'justify-center px-2' : 'px-3'} py-2.5 rounded-lg text-sm font-medium transition-all duration-200 group relative ${active
                                                                ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20'
                                                                : 'text-slate-400 hover:text-white hover:bg-slate-800'
                                                            }`}
                                                        title={collapsed ? item.label : undefined}
                                                    >
                                                        {active && (
                                                            <div className="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-white rounded-r-full"></div>
                                                        )}
                                                        <i className={`bi ${item.icon} text-lg w-6 flex-shrink-0 ${active ? 'text-white' : 'text-slate-500 group-hover:text-white'}`}></i>
                                                        {!collapsed && <span className="truncate">{item.label}</span>}
                                                    </Link>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </nav>

                <div className={`p-3 border-t border-slate-800 flex-shrink-0 ${collapsed ? 'items-center' : ''}`}>
                    <div className={`flex items-center ${collapsed ? 'justify-center' : ''}`}>
                        <div className="w-9 h-9 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center text-sm font-bold flex-shrink-0 shadow-md">
                            {auth?.user?.name?.charAt(0).toUpperCase()}
                        </div>
                        {!collapsed && (
                            <div className="ml-3 flex-1 min-w-0">
                                <p className="text-sm font-semibold truncate">{auth?.user?.name}</p>
                                <p className="text-xs text-slate-500 truncate">Admin</p>
                            </div>
                        )}
                    </div>

                    {!collapsed && (
                        <div className="mt-3 flex gap-2">
                            <Link
                                href="/profile"
                                className="flex-1 text-center px-3 py-2 text-xs font-medium bg-slate-800 hover:bg-slate-700 rounded-lg text-slate-300 transition-colors"
                            >
                                <i className="bi bi-person mr-1"></i>Profile
                            </Link>
                            <button
                                onClick={logout}
                                className="flex-1 text-center px-3 py-2 text-xs font-medium bg-red-500/10 hover:bg-red-500/20 rounded-lg text-red-400 transition-colors"
                            >
                                <i className="bi bi-box-arrow-right mr-1"></i>Logout
                            </button>
                        </div>
                    )}

                    {collapsed && (
                        <button
                            onClick={logout}
                            className="mt-2 w-full text-center px-2 py-2 text-xs font-medium bg-red-500/10 hover:bg-red-500/20 rounded-lg text-red-400 transition-colors"
                            title="Logout"
                        >
                            <i className="bi bi-box-arrow-right"></i>
                        </button>
                    )}
                </div>
            </aside>
        </>
    );
}
