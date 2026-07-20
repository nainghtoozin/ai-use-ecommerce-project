import { useState, useEffect, useMemo } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';
import { adminUrl } from '@/Utils/adminUrl';
import {
    LayoutDashboard, Package, Tags, Megaphone,
    BarChart3, ShoppingBag, Receipt,
    ShoppingCart, CreditCard,
    Building2, MapPin,
    Users, UserCog, ShieldCheck, History,
    Bell, Globe, BellRing, Send, Settings,
    Store, User, LogOut, Menu, X,
    ChevronLeft, ChevronRight, ChevronDown,
    FileText, Ruler, Layers, Zap, ArrowUp, Clock,
    UserCircle, UserPlus, Activity, Shield,
} from 'lucide-react';

const STORAGE_PREFIX = 'admin_sidebar_section_';

export default function AdminSidebar() {
    const { props, url } = usePage();
    const { auth, website_info, platform_setting, tenant, featureStatus } = props;
    const userPermissions = auth?.user?.permissions;
    const isSuperAdmin = auth?.user?.is_superadmin;
    const isOwner = auth?.user?.is_owner;
    const can = (perm) => isSuperAdmin || isOwner || userPermissions?.includes(perm);
    const hasFeature = (key) => featureStatus?.[key]?.enabled !== false;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(false);
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
        'UserCog': UserCog,
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
        'Zap': Zap,
        'ArrowUp': ArrowUp,
        'Clock': Clock,
        'UserCircle': UserCircle,
        'UserPlus': UserPlus,
        'Activity': Activity,
        'Shield': Shield,
    };

    const Icon = ({ name, className = '' }) => {
        const LucideIcon = iconMap[name];
        if (!LucideIcon) return null;
        return <LucideIcon className={`w-[18px] h-[18px] ${className}`} />;
    };

    const menuSections = useMemo(() => {
        if (isSuperAdmin) {
            return [
                {
                    title: 'Overview',
                    items: [
                        { label: 'Dashboard', href: '/superadmin', icon: 'LayoutDashboard' },
                    ]
                },
                {
                    title: 'Merchants',
                    items: [
                        { label: 'All Merchants', href: '/superadmin/tenants', icon: 'Building2' },
                    ]
                },
                {
                    title: 'Plans & Billing',
                    items: [
                        { label: 'Plans', href: '/superadmin/plans', icon: 'FileText' },
                        { label: 'Subscriptions', href: '/superadmin/subscriptions', icon: 'CreditCard' },
                        { label: 'Payment Reviews', href: '/superadmin/billing', icon: 'Receipt' },
                        { label: 'Financial Console', href: '/superadmin/financial', icon: 'BarChart3' },
                        { label: 'Billing Methods', href: '/superadmin/billing-payment-methods', icon: 'CreditCard' },
                    ]
                },
                {
                    title: 'Operations',
                    items: [
                        { label: 'Webhooks', href: '/superadmin/operations', icon: 'Zap' },
                    ]
                },
                {
                    title: 'Settings',
                    items: [
                        { label: 'Platform', href: '/superadmin/platform-settings', icon: 'Settings' },
                        { label: 'Website', href: '/admin/website-info/edit', icon: 'Globe' },
                    ]
                },
                {
                    title: 'Logs',
                    items: [
                        { label: 'Activity', href: '/admin/activity-logs', icon: 'Activity' },
                    ]
                },
            ];
        }

        return [
            {
                title: 'Overview',
                items: [
                    ...(can('dashboard.view') ? [{ label: 'Dashboard', href: '/admin/dashboard', icon: 'LayoutDashboard' }] : []),
                ]
            },
            {
                title: 'Catalog',
                items: [
                    ...(can('products.view') ? [{ label: 'Products', href: '/admin/products', icon: 'Package' }] : []),
                    ...(can('categories.view') ? [{ label: 'Categories', href: '/admin/categories', icon: 'Tags' }] : []),
                    ...(can('brands.view') ? [{ label: 'Brands', href: '/admin/brands', icon: 'Layers' }] : []),
                    ...(can('units.view') ? [{ label: 'Units', href: '/admin/units', icon: 'Ruler' }] : []),
                ]
            },
            {
                title: 'Sales',
                items: [
                    ...(can('orders.view') ? [{ label: 'Orders', href: '/admin/orders', icon: 'ShoppingCart' }] : []),
                    ...(can('payments.view') ? [{ label: 'Payment Methods', href: '/admin/payment-methods', icon: 'CreditCard' }] : []),
                ]
            },
            {
                title: 'Marketing',
                items: [
                    ...(can('coupons.view') && hasFeature('coupons') ? [{ label: 'Coupons', href: '/admin/coupons', icon: 'Tags' }] : []),
                    ...(can('promotions.view') && hasFeature('promotions') ? [{ label: 'Promotions', href: '/admin/promotions', icon: 'Megaphone' }] : []),
                    ...(hasFeature('flash_sales') ? [{ label: 'Flash Sales', href: '/admin/flash-sales', icon: 'Zap' }] : []),
                ]
            },
            ...(can('billing.view') ? [{
                title: 'Billing',
                items: [
                    { label: 'Overview', href: '/admin/billing', icon: 'CreditCard' },
                    { label: 'Subscription', href: '/admin/billing/subscription', icon: 'FileText' },
                    { label: 'Upgrade', href: '/admin/billing/upgrade', icon: 'ArrowUp' },
                    { label: 'History', href: '/admin/billing/payment-history', icon: 'Receipt' },
                    { label: 'Settings', href: '/admin/billing/settings', icon: 'Settings' },
                ]
            }] : []),
            {
                title: 'Analytics',
                items: [
                    ...(can('reports.sales') && hasFeature('reports') ? [{ label: 'Sales', href: '/admin/reports/sales', icon: 'BarChart3' }] : []),
                    ...(can('reports.products') && hasFeature('reports') ? [{ label: 'Products', href: '/admin/reports/product-sales', icon: 'ShoppingBag' }] : []),
                    ...(can('reports.payments') && hasFeature('reports') ? [{ label: 'Payments', href: '/admin/reports/payments', icon: 'Receipt' }] : []),
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
                title: 'Staff',
                items: [
                    ...(can('users.view') ? [{ label: 'Members', href: '/admin/users', icon: 'Users' }] : []),
                    ...(can('users.view') || auth?.user?.is_owner ? [{ label: 'Staff', href: '/admin/team', icon: 'UserPlus' }] : []),
                    ...(can('roles.view') ? [{ label: 'Roles', href: '/admin/roles', icon: 'Shield' }] : []),
                    ...(can('activity-logs.view') ? [{ label: 'Activity', href: '/admin/activity-logs', icon: 'Activity' }] : []),
                    { label: 'Notifications', href: '/admin/notifications', icon: 'Bell' },
                ]
            },
            {
                title: 'Settings',
                items: [
                    ...(can('settings.website') ? [{ label: 'Website', href: '/admin/website-info/edit', icon: 'Globe' }] : []),
                    ...(can('settings.notifications') ? [{ label: 'Notifications', href: '/admin/settings/notifications', icon: 'BellRing' }] : []),
                    ...(can('settings.telegram') ? [{ label: 'Telegram', href: '/admin/settings/telegram-integration', icon: 'Send' }] : []),
                    ...(can('settings.view') ? [{ label: 'General', href: '/admin/settings', icon: 'Settings' }] : []),
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
                    next[section.title] = saved !== null ? saved === 'true' : section.title === 'Overview';
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
                    background: rgba(255, 255, 255, 0.1);
                    border-radius: 9999px;
                }
                .sidebar-scrollbar::-webkit-scrollbar-thumb:hover {
                    background: rgba(255, 255, 255, 0.2);
                }
                .sidebar-section-content {
                    display: grid;
                    grid-template-rows: 0fr;
                    transition: grid-template-rows 200ms cubic-bezier(0.4, 0, 0.2, 1);
                }
                .sidebar-section-content.open {
                    grid-template-rows: 1fr;
                }
                .sidebar-section-content > div {
                    overflow: hidden;
                }
            `}</style>

            {/* Mobile toggle */}
            <button
                onClick={() => setSidebarOpen(!sidebarOpen)}
                className="lg:hidden fixed top-3 left-3 z-50 p-2 bg-slate-900 rounded-lg shadow-lg hover:bg-slate-800 transition-colors"
            >
                {sidebarOpen ? <X className="w-5 h-5 text-white" /> : <Menu className="w-5 h-5 text-white" />}
            </button>

            {/* Mobile overlay */}
            {sidebarOpen && (
                <div className="lg:hidden fixed inset-0 bg-black/60 z-40 backdrop-blur-sm" onClick={() => setSidebarOpen(false)} />
            )}

            <aside
                className={`fixed lg:sticky top-0 left-0 z-40 h-screen flex flex-col bg-slate-900 text-white transition-all duration-300 ease-in-out ${collapsed ? 'w-[72px]' : 'w-64'
                    } ${sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
                    }`}
            >
                {/* Header */}
                <div className={`h-16 flex items-center ${collapsed ? 'justify-center px-2' : 'px-4'} border-b border-white/[0.06] flex-shrink-0`}>
                    {collapsed ? (
                        <div className="w-8 h-8 rounded-lg flex items-center justify-center" style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}>
                            <Store className="w-4 h-4 text-white" />
                        </div>
                    ) : (
                        <div className="flex items-center gap-3 min-w-0">
                            {logoUrl ? (
                                <img src={logoUrl} alt={siteName} className="h-7 w-auto flex-shrink-0" />
                            ) : (
                                <div className="w-7 h-7 rounded-md flex items-center justify-center flex-shrink-0" style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}>
                                    <Store className="w-4 h-4 text-white" />
                                </div>
                            )}
                            <span className="text-sm font-semibold truncate">{siteName}</span>
                        </div>
                    )}
                </div>

                {/* Collapse toggle */}
                <div className="hidden lg:block absolute -right-3 top-20 z-50">
                    <button
                        onClick={() => setCollapsed(!collapsed)}
                        className="w-6 h-6 bg-slate-700 hover:bg-slate-600 rounded-full flex items-center justify-center shadow-lg border border-slate-600/50 transition-colors"
                    >
                        {collapsed
                            ? <ChevronRight className="w-3 h-3" />
                            : <ChevronLeft className="w-3 h-3" />
                        }
                    </button>
                </div>

                {/* Navigation */}
                <nav className="flex-1 px-2.5 py-3 overflow-y-auto overflow-x-hidden sidebar-scrollbar">
                    {menuSections.filter(s => s.items.length > 0).map((section, sectionIdx) => {
                        const isOpen = openSections[section.title] ?? (section.title === 'Overview');
                        const sectionHasActive = section.items.some(item => isActive(item.href));

                        return (
                            <div key={section.title} className={sectionIdx > 0 ? 'mt-1.5' : ''}>
                                {!collapsed && (
                                    <button
                                        onClick={() => toggleSection(section.title)}
                                        className={`w-full flex items-center justify-between px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wider transition-colors rounded-md ${sectionHasActive
                                            ? 'text-blue-400'
                                            : 'text-slate-500 hover:text-slate-400'
                                            }`}
                                    >
                                        <span>{section.title}</span>
                                        <ChevronDown className={`w-3 h-3 transition-transform duration-200 ${isOpen ? '' : '-rotate-90'}`} />
                                    </button>
                                )}
                                <div className={`sidebar-section-content ${isOpen ? 'open' : ''}`}>
                                    <div>
                                        <div className="space-y-0.5">
                                            {section.items.map((item) => {
                                                const active = isActive(item.href);
                                                return (
                                                    <Link
                                                        key={item.href}
                                                        href={adminUrl(item.href)}
                                                        onClick={() => setSidebarOpen(false)}
                                                        className={`flex items-center ${collapsed ? 'justify-center px-2' : 'px-2.5'} py-2 rounded-lg text-[13px] font-medium transition-all duration-150 group relative ${active
                                                                ? 'text-white'
                                                                : 'text-slate-400 hover:text-white hover:bg-white/[0.06]'
                                                            }`}
                                                        style={active ? { backgroundColor: 'var(--theme-color, #3B82F6)' } : {}}
                                                        title={collapsed ? item.label : undefined}
                                                    >
                                                        {active && (
                                                            <div className="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-5 bg-white rounded-r-full" />
                                                        )}
                                                        <Icon name={item.icon} className={`flex-shrink-0 ${active ? 'text-white' : 'text-slate-500 group-hover:text-slate-300'}`} />
                                                        {!collapsed && <span className="ml-2.5 truncate">{item.label}</span>}
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

                {/* User section */}
                <div className="p-2.5 border-t border-white/[0.06] flex-shrink-0">
                    <div className={`flex items-center ${collapsed ? 'justify-center' : ''}`}>
                        <div className="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0" style={{ background: 'linear-gradient(135deg, var(--theme-color, #3B82F6), color-mix(in srgb, var(--theme-color, #3B82F6) 80%, black))' }}>
                            {auth?.user?.name?.charAt(0).toUpperCase()}
                        </div>
                        {!collapsed && (
                            <div className="ml-2.5 flex-1 min-w-0">
                                <p className="text-[13px] font-medium truncate">{auth?.user?.name}</p>
                                <p className="text-[11px] text-slate-500 truncate">{auth?.user?.role_label}</p>
                            </div>
                        )}
                    </div>

                    {!collapsed && (
                        <div className="mt-2.5 flex gap-1.5">
                            <Link
                                href={adminUrl('/profile')}
                                className="flex-1 flex items-center justify-center gap-1.5 px-2.5 py-1.5 text-[11px] font-medium bg-white/[0.06] hover:bg-white/[0.1] rounded-md text-slate-300 transition-colors"
                            >
                                <User className="w-3 h-3" />Profile
                            </Link>
                            <button
                                onClick={logout}
                                className="flex-1 flex items-center justify-center gap-1.5 px-2.5 py-1.5 text-[11px] font-medium bg-red-500/10 hover:bg-red-500/20 rounded-md text-red-400 transition-colors"
                            >
                                <LogOut className="w-3 h-3" />Logout
                            </button>
                        </div>
                    )}

                    {collapsed && (
                        <button
                            onClick={logout}
                            className="mt-2 w-full flex items-center justify-center px-2 py-2 text-xs font-medium bg-red-500/10 hover:bg-red-500/20 rounded-lg text-red-400 transition-colors"
                            title="Logout"
                        >
                            <LogOut className="w-4 h-4" />
                        </button>
                    )}
                </div>
            </aside>
        </>
    );
}
