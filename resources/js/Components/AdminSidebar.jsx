import { useState, useEffect, useMemo } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';
import { adminUrl } from '@/Utils/adminUrl';
import {
    LayoutDashboard, Package, Tags, Megaphone,
    BarChart3, ShoppingBag, Receipt,
    ShoppingCart, CreditCard,
    Building2, MapPin,
    Users, ShieldCheck, History,
    Bell, Globe, BellRing, Send, Settings,
    Store, User, LogOut, Menu, X,
    ChevronLeft, ChevronRight, ChevronDown,
    FileText, Ruler, Layers,
} from 'lucide-react';

const STORAGE_PREFIX = 'admin_sidebar_section_';

export default function AdminSidebar() {
    const { props, url } = usePage();
    const { auth, website_info, platform_setting, tenant } = props;
    const userPermissions = auth?.user?.permissions;
    const can = (perm) => userPermissions?.includes(perm);
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(false);

    const isSuperAdmin = auth?.user?.is_superadmin;
    const brandLogo = isSuperAdmin ? platform_setting?.site_logo : website_info?.logo;
    const brandName = isSuperAdmin ? (platform_setting?.site_name || 'SuperAdmin') : (website_info?.site_name || 'My Store');
    const logoUrl = assetUrl(brandLogo);
    const siteName = brandName;

    const iconMap = {
        'LayoutDashboard': LayoutDashboard,
        'Package': Package,
        'Tags': Tags,
        'Megaphone': Megaphone,
        'BarChart3': BarChart3,
        'ShoppingBag': ShoppingBag,
        'Receipt': Receipt,
        'ShoppingCart': ShoppingCart,
        'CreditCard': CreditCard,
        'Building2': Building2,
        'MapPin': MapPin,
        'Users': Users,
        'ShieldCheck': ShieldCheck,
        'History': History,
        'Bell': Bell,
        'Globe': Globe,
        'BellRing': BellRing,
        'Send': Send,
        'Settings': Settings,
        'FileText': FileText,
        'Ruler': Ruler,
        'Layers': Layers,
    };

    const Icon = ({ name, className = '' }) => {
        const LucideIcon = iconMap[name];
        if (!LucideIcon) return null;
        return <LucideIcon className={`w-5 h-5 ${className}`} />;
    };

    const menuSections = useMemo(() => {
        if (isSuperAdmin) {
            return [
                {
                    title: 'Main',
                    items: [
                        { label: 'Dashboard', href: '/superadmin', icon: 'LayoutDashboard' },
                    ]
                },
                {
                    title: 'Merchant Management',
                    items: [
                        { label: 'Merchants', href: '/superadmin/tenants', icon: 'Building2' },
                    ]
                },
                {
                    title: 'Subscription Management',
                    items: [
                        { label: 'Plans', href: '/superadmin/plans', icon: 'FileText' },
                        { label: 'Subscriptions', href: '/superadmin/subscriptions', icon: 'CreditCard' },
                    ]
                },
                {
                    title: 'System Management',
                    items: [
                        { label: 'Platform Settings', href: '/superadmin/platform-settings', icon: 'Settings' },
                        { label: 'Website Info', href: '/admin/website-info/edit', icon: 'Globe' },
                    ]
                },
                {
                    title: 'Logs',
                    items: [
                        { label: 'Activity Logs', href: '/admin/activity-logs', icon: 'History' },
                    ]
                },
            ];
        }

        return [
            {
                title: 'Main',
                items: [
                    ...(can('dashboard.view') ? [{ label: 'Dashboard', href: '/admin/dashboard', icon: 'LayoutDashboard' }] : []),
                    ...(can('billing.view') ? [{ label: 'Billing', href: '/admin/billing', icon: 'CreditCard' }] : []),
                ]
            },
            {
                title: 'Catalog',
                items: [
                    ...(can('products.view') ? [{ label: 'Products', href: '/admin/products', icon: 'Package' }] : []),
                    ...(can('categories.view') ? [{ label: 'Categories', href: '/admin/categories', icon: 'Tags' }] : []),
                    ...(can('brands.view') ? [{ label: 'Brands', href: '/admin/brands', icon: 'Layers' }] : []),
                    ...(can('units.view') ? [{ label: 'Units', href: '/admin/units', icon: 'Ruler' }] : []),
                    ...(can('promotions.view') ? [{ label: 'Promotions', href: '/admin/promotions', icon: 'Megaphone' }] : []),
                ]
            },
            {
                title: 'Orders',
                items: [
                    ...(can('orders.view') ? [{ label: 'Orders', href: '/admin/orders', icon: 'ShoppingCart' }] : []),
                    ...(can('payments.view') ? [{ label: 'Payment Methods', href: '/admin/payment-methods', icon: 'CreditCard' }] : []),
                ]
            },
            {
                title: 'Reports',
                items: [
                    ...(can('reports.sales') ? [{ label: 'Sales Report', href: '/admin/reports/sales', icon: 'BarChart3' }] : []),
                    ...(can('reports.products') ? [{ label: 'Product Sales', href: '/admin/reports/product-sales', icon: 'ShoppingBag' }] : []),
                    ...(can('reports.payments') ? [{ label: 'Payments', href: '/admin/reports/payments', icon: 'Receipt' }] : []),
                ]
            },
            {
                title: 'Locations',
                items: [
                    ...(can('cities.view') ? [{ label: 'Cities', href: '/admin/cities', icon: 'Building2' }] : []),
                    ...(can('townships.view') ? [{ label: 'Townships', href: '/admin/townships', icon: 'MapPin' }] : []),
                ]
            },
            {
                title: 'System',
                items: [
                    ...(can('users.view') ? [{ label: 'Users', href: '/admin/users', icon: 'Users' }] : []),
                    ...(can('roles.view') ? [{ label: 'Roles & Permissions', href: '/admin/roles', icon: 'ShieldCheck' }] : []),
                    ...(can('activity-logs.view') ? [{ label: 'Activity Logs', href: '/admin/activity-logs', icon: 'History' }] : []),
                    { label: 'Notifications', href: '/admin/notifications', icon: 'Bell' },
                ]
            },
            {
                title: 'Configuration',
                items: [
                    ...(can('settings.website') ? [{ label: 'Website Info', href: '/admin/website-info/edit', icon: 'Globe' }] : []),
                    ...(can('settings.notifications') ? [{ label: 'Notification Settings', href: '/admin/settings/notifications', icon: 'BellRing' }] : []),
                    ...(can('settings.telegram') ? [{ label: 'Telegram Integration', href: '/admin/settings/telegram-integration', icon: 'Send' }] : []),
                    ...(can('settings.view') ? [{ label: 'Settings', href: '/admin/settings', icon: 'Settings' }] : []),
                ]
            }
        ];
    }, [userPermissions, isSuperAdmin]);

    function isActive(href) {
        if (href === '/') return url === '/';
        const candidates = [href, adminUrl(href)];
        return candidates.some(candidate => {
            const hrefPath = candidate.replace(/\/+$/, '');
            const urlPath = url.replace(/\/+$/, '');
            if (urlPath === hrefPath) return true;
            if (urlPath.startsWith(hrefPath + '/')) return true;
            return false;
        });
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

    const storeSlug = tenant?.slug;
    const logout = () => router.post('/logout', {
        context: isSuperAdmin ? 'superadmin' : 'admin',
        store_slug: storeSlug,
    });

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
                {sidebarOpen ? <X className="w-5 h-5 text-white" /> : <Menu className="w-5 h-5 text-white" />}
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
                        <div className="w-8 h-8 rounded-lg flex items-center justify-center" style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}>
                            <Store className="w-5 h-5 text-white" />
                        </div>
                    ) : (
                        <>
                            {logoUrl ? (
                                <img src={logoUrl} alt={siteName} className="h-8 w-auto" />
                            ) : (
                                <div className="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}>
                                    <Store className="w-5 h-5 text-white" />
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
                        {collapsed
                            ? <ChevronRight className="w-3.5 h-3.5" />
                            : <ChevronLeft className="w-3.5 h-3.5" />
                        }
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
                                        {isOpen
                                            ? <ChevronDown className="w-3.5 h-3.5 transition-transform duration-200" />
                                            : <ChevronRight className="w-3.5 h-3.5 transition-transform duration-200" />
                                        }
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
                                                    href={adminUrl(item.href)}
                                                    onClick={() => setSidebarOpen(false)}
                                                        className={`flex items-center ${collapsed ? 'justify-center px-2' : 'px-3'} py-2.5 rounded-lg text-sm font-medium transition-all duration-200 group relative ${active
                                                                ? 'text-white shadow-md'
                                                                : 'text-slate-400 hover:text-white hover:bg-slate-800'
                                                            }`}
                                                        style={active ? { backgroundColor: 'var(--theme-color, #3B82F6)', boxShadow: '0 4px 12px rgba(var(--theme-color-rgb, 59, 130, 246), 0.2)' } : {}}
                                                        title={collapsed ? item.label : undefined}
                                                    >
                                                        {active && (
                                                            <div className="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-white rounded-r-full"></div>
                                                        )}
                                                        <Icon name={item.icon} className={`w-6 flex-shrink-0 ${active ? 'text-white' : 'text-slate-500 group-hover:text-white'}`} />
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
                        <div className="w-9 h-9 rounded-lg flex items-center justify-center text-sm font-bold flex-shrink-0 shadow-md" style={{ background: 'linear-gradient(135deg, var(--theme-color, #3B82F6), color-mix(in srgb, var(--theme-color, #3B82F6) 80%, black))' }}>
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
                                href={adminUrl('/profile')}
                                className="flex-1 text-center px-3 py-2 text-xs font-medium bg-slate-800 hover:bg-slate-700 rounded-lg text-slate-300 transition-colors"
                            >
                                <User className="w-3.5 h-3.5 inline mr-1" />Profile
                            </Link>
                            <button
                                onClick={logout}
                                className="flex-1 text-center px-3 py-2 text-xs font-medium bg-red-500/10 hover:bg-red-500/20 rounded-lg text-red-400 transition-colors"
                            >
                                <LogOut className="w-3.5 h-3.5 inline mr-1" />Logout
                            </button>
                        </div>
                    )}

                    {collapsed && (
                        <button
                            onClick={logout}
                            className="mt-2 w-full text-center px-2 py-2 text-xs font-medium bg-red-500/10 hover:bg-red-500/20 rounded-lg text-red-400 transition-colors"
                            title="Logout"
                        >
                            <LogOut className="w-4 h-4 mx-auto" />
                        </button>
                    )}
                </div>
            </aside>
        </>
    );
}
