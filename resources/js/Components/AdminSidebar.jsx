import { useState, useEffect, useMemo, useCallback } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';
import { adminUrl } from '@/Utils/adminUrl';
import { useTranslation } from '@/Utils/useTranslation';
import WorkspaceSwitcher from '@/Components/WorkspaceSwitcher';
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
    UserCircle, UserPlus, Activity, Shield, Archive,
    Rocket, HelpCircle,
} from 'lucide-react';

const STORAGE_KEY = 'admin_sidebar_open_section';

const iconMap = {
    LayoutDashboard, Package, Tags, Megaphone,
    BarChart3, ShoppingBag, Receipt,
    ShoppingCart, CreditCard,
    Building2, MapPin,
    Users, UserCog, ShieldCheck, History,
    Bell, Globe, BellRing, Send, Settings,
    FileText, Ruler, Layers, Zap, ArrowUp, Clock,
    UserCircle, UserPlus, Activity, Shield, Archive, Rocket, HelpCircle,
};

function Icon({ name, className = '', ...props }) {
    const LucideIcon = iconMap[name];
    if (!LucideIcon) return null;
    return <LucideIcon className={`w-[18px] h-[18px] ${className}`} {...props} />;
}

export default function AdminSidebar() {
    const { props, url } = usePage();
    const { auth, website_info, platform_setting, tenant, featureStatus } = props;
    const { t } = useTranslation();
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

    const menuSections = useMemo(() => {
        if (isSuperAdmin) {
            return [
                {
                    title: t('navigation.overview'),
                    items: [
                        { label: t('navigation.dashboard'), href: '/superadmin', icon: 'LayoutDashboard' },
                    ]
                },
                {
                    title: t('navigation.marketing'),
                    items: [
                        { label: t('navigation.dashboard'), href: '/superadmin/tenants', icon: 'Building2' },
                    ]
                },
                {
                    title: t('navigation.billing'),
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
                    title: t('navigation.settings'),
                    items: [
                        { label: 'Platform', href: '/superadmin/platform-settings', icon: 'Settings' },
                        { label: 'FAQ Management', href: '/superadmin/faqs', icon: 'HelpCircle' },
                        { label: t('navigation.website'), href: '/admin/website-info/edit', icon: 'Globe' },
                    ]
                },
                {
                    title: 'Logs',
                    items: [
                        { label: 'Activity', href: '/admin/activity-logs', icon: 'Activity' },
                        { label: 'Audit Log', href: '/admin/audit-logs', icon: 'Shield' },
                    ]
                },
            ];
        }

        return [
            {
                title: t('navigation.overview'),
                items: [
                    ...(can('dashboard.view') ? [{ label: t('navigation.dashboard'), href: '/admin/dashboard', icon: 'LayoutDashboard' }] : []),
                ]
            },
            {
                title: t('navigation.catalog'),
                items: [
                    ...(can('products.view') ? [{ label: t('navigation.products'), href: '/admin/products', icon: 'Package' }] : []),
                    ...(can('categories.view') ? [{ label: t('navigation.categories'), href: '/admin/categories', icon: 'Tags' }] : []),
                    ...(can('brands.view') ? [{ label: t('navigation.brands'), href: '/admin/brands', icon: 'Layers' }] : []),
                    ...(can('units.view') ? [{ label: t('navigation.units'), href: '/admin/units', icon: 'Ruler' }] : []),
                ]
            },
            ...(can('inventory.view') && hasFeature('inventory_management') ? [{
                title: t('navigation.inventory'),
                items: [
                    { label: t('navigation.dashboard'), href: '/admin/inventory/dashboard', icon: 'LayoutDashboard' },
                    { label: 'Products Inventory', href: '/admin/inventory', icon: 'Archive' },
                    { label: 'Stock History', href: '/admin/inventory/stock-history', icon: 'Clock' },
                    { label: 'Stock Movements', href: '/admin/inventory/movements', icon: 'Activity' },
                    { label: 'Stock Adjustments', href: '/admin/inventory/adjustments', icon: 'Settings' },
                ]
            }] : []),
            {
                title: t('navigation.sales'),
                items: [
                    ...(can('orders.view') ? [{ label: t('navigation.orders'), href: '/admin/orders', icon: 'ShoppingCart' }] : []),
                    ...(can('payments.view') ? [{ label: t('navigation.payment_methods'), href: '/admin/payment-methods', icon: 'CreditCard' }] : []),
                ]
            },
            {
                title: t('navigation.marketing'),
                items: [
                    ...(can('coupons.view') && hasFeature('coupons') ? [{ label: t('navigation.coupons'), href: '/admin/coupons', icon: 'Tags' }] : []),
                    ...(can('promotions.view') && hasFeature('promotions') ? [{ label: t('navigation.promotions'), href: '/admin/promotions', icon: 'Megaphone' }] : []),
                    ...(hasFeature('flash_sales') ? [{ label: t('navigation.flash_sales'), href: '/admin/flash-sales', icon: 'Zap' }] : []),
                ]
            },
            ...(can('billing.view') ? [{
                title: t('navigation.billing'),
                items: [
                    { label: 'Overview', href: '/admin/billing', icon: 'CreditCard' },
                    { label: 'Subscription', href: '/admin/billing/subscription', icon: 'FileText' },
                    { label: 'Upgrade', href: '/admin/billing/upgrade', icon: 'ArrowUp' },
                    { label: 'Invoices', href: '/admin/billing/invoices', icon: 'Receipt' },
                    { label: 'History', href: '/admin/billing/payment-history', icon: 'Clock' },
                    { label: t('navigation.settings'), href: '/admin/billing/settings', icon: 'Settings' },
                ]
            }] : []),
            {
                title: t('navigation.analytics'),
                items: [
                    ...(can('reports.sales') && hasFeature('reports') ? [{ label: 'Sales', href: '/admin/reports/sales', icon: 'BarChart3' }] : []),
                    ...(can('reports.products') && hasFeature('reports') ? [{ label: t('navigation.products'), href: '/admin/reports/product-sales', icon: 'ShoppingBag' }] : []),
                    ...(can('reports.payments') && hasFeature('reports') ? [{ label: 'Payments', href: '/admin/reports/payments', icon: 'Receipt' }] : []),
                ]
            },
            {
                title: t('navigation.locations'),
                items: [
                    ...(can('cities.view') ? [{ label: t('navigation.cities'), href: '/admin/cities', icon: 'Building2' }] : []),
                    ...(can('townships.view') ? [{ label: t('navigation.townships'), href: '/admin/townships', icon: 'MapPin' }] : []),
                ]
            },
            {
                title: t('navigation.staff'),
                items: [
                    ...(can('users.view') ? [{ label: t('navigation.members'), href: '/admin/users', icon: 'Users' }] : []),
                    ...(can('users.view') || auth?.user?.is_owner ? [{ label: t('navigation.staff'), href: '/admin/team', icon: 'UserPlus' }] : []),
                    ...(can('roles.view') ? [{ label: t('navigation.roles'), href: '/admin/roles', icon: 'Shield' }] : []),
                    ...(can('activity.view') ? [{ label: 'Activity', href: '/admin/activity-logs', icon: 'Activity' }] : []),
                    ...(can('audit.view') ? [{ label: 'Audit Log', href: '/admin/audit-logs', icon: 'ShieldCheck' }] : []),
                    { label: t('navigation.notifications'), href: '/admin/notifications', icon: 'Bell' },
                ]
            },
            {
                title: t('navigation.settings'),
                items: [
                    ...(can('settings.website') ? [{ label: t('navigation.website'), href: '/admin/website-info/edit', icon: 'Globe' }] : []),
                    ...(can('settings.notifications') ? [{ label: t('navigation.notifications'), href: '/admin/settings/notifications', icon: 'BellRing' }] : []),
                    ...(can('settings.telegram') ? [{ label: t('navigation.telegram'), href: '/admin/settings/telegram-integration', icon: 'Send' }] : []),
                    ...(isOwner ? [{ label: 'Setup Guide', href: '/admin/settings/onboarding', icon: 'Rocket' }] : []),
                    ...(can('settings.view') ? [{ label: t('navigation.general'), href: '/admin/settings', icon: 'Settings' }] : []),
                ]
            }
        ];
    }, [userPermissions, isSuperAdmin, t]);

    function matchPath(href) {
        if (href === '/') return url === '/' ? 1 : 0;
        const candidates = [href, adminUrl(href)];
        for (const candidate of candidates) {
            const hrefPath = candidate.replace(/\/+$/, '');
            const urlPath = url.replace(/\/+$/, '');
            if (urlPath === hrefPath) return hrefPath.length;
            if (urlPath.startsWith(hrefPath + '/')) return hrefPath.length;
        }
        return 0;
    }

    function findActiveItem(items) {
        let best = null;
        let bestLen = 0;
        for (const item of items) {
            const len = matchPath(item.href);
            if (len > bestLen) {
                bestLen = len;
                best = item;
            }
        }
        return best;
    }

    function isActive(href) {
        return matchPath(href) > 0;
    }

    // Single-open accordion: only one section open at a time
    const [openSection, setOpenSection] = useState(() => {
        try {
            return localStorage.getItem(STORAGE_KEY) || 'Overview';
        } catch {
            return 'Overview';
        }
    });

    // Auto-expand the section containing the active link
    useEffect(() => {
        for (const section of menuSections) {
            if (section.items.some(item => isActive(item.href))) {
                setOpenSection(prev => {
                    if (prev !== section.title) {
                        try { localStorage.setItem(STORAGE_KEY, section.title); } catch {}
                    }
                    return section.title;
                });
                return;
            }
        }
    }, [url, menuSections]);

    const toggleSection = useCallback((title) => {
        setOpenSection(prev => {
            const next = prev === title ? '' : title;
            try { localStorage.setItem(STORAGE_KEY, next); } catch {}
            return next;
        });
    }, []);

    const storeSlug = tenant?.slug;
    const logout = () => router.post('/logout', {
        context: isSuperAdmin ? 'superadmin' : 'admin',
        store_slug: storeSlug,
    });

    const visibleSections = menuSections.filter(s => s.items.length > 0);

    return (
        <>
            <style>{`
                .sidebar-scrollbar::-webkit-scrollbar {
                    width: 3px;
                }
                .sidebar-scrollbar::-webkit-scrollbar-track {
                    background: transparent;
                }
                .sidebar-scrollbar::-webkit-scrollbar-thumb {
                    background: rgba(255, 255, 255, 0.1);
                    border-radius: 9999px;
                }
                .sidebar-scrollbar::-webkit-scrollbar-thumb:hover {
                    background: rgba(255, 255, 255, 0.18);
                }
                .sidebar-collapse-content {
                    display: grid;
                    grid-template-rows: 0fr;
                    transition: grid-template-rows 250ms cubic-bezier(0.16, 1, 0.3, 1);
                }
                .sidebar-collapse-content.open {
                    grid-template-rows: 1fr;
                }
                .sidebar-collapse-content > div {
                    overflow: hidden;
                }
            `}</style>

            {/* Mobile toggle */}
            <button
                onClick={() => setSidebarOpen(!sidebarOpen)}
                className="lg:hidden fixed top-3 left-3 z-50 p-2 bg-slate-900 rounded-lg shadow-lg hover:bg-slate-800 transition-colors"
                aria-label={sidebarOpen ? 'Close menu' : 'Open menu'}
            >
                {sidebarOpen ? <X className="w-5 h-5 text-white" /> : <Menu className="w-5 h-5 text-white" />}
            </button>

            {/* Mobile overlay */}
            {sidebarOpen && (
                <div className="lg:hidden fixed inset-0 bg-black/60 z-40 backdrop-blur-sm" onClick={() => setSidebarOpen(false)} />
            )}

            <aside
                className={`fixed lg:sticky top-0 left-0 z-40 h-screen flex flex-col bg-slate-900 text-white transition-all duration-300 ease-in-out ${
                    collapsed ? 'w-[72px]' : 'w-64'
                } ${sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}`}
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
                                <img src={logoUrl} alt={brandName} className="h-7 w-auto flex-shrink-0" />
                            ) : (
                                <div className="w-7 h-7 rounded-md flex items-center justify-center flex-shrink-0" style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}>
                                    <Store className="w-4 h-4 text-white" />
                                </div>
                            )}
                            <span className="text-sm font-semibold truncate">{brandName}</span>
                        </div>
                    )}
                </div>

                {/* Collapse toggle */}
                <div className="hidden lg:block absolute -right-3 top-20 z-50">
                    <button
                        onClick={() => setCollapsed(!collapsed)}
                        className="w-6 h-6 bg-slate-700 hover:bg-slate-600 rounded-full flex items-center justify-center shadow-lg border border-slate-600/50 transition-colors"
                        aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    >
                        {collapsed ? <ChevronRight className="w-3 h-3" /> : <ChevronLeft className="w-3 h-3" />}
                    </button>
                </div>

                {/* Navigation */}
                <nav className="flex-1 px-3 py-3 overflow-y-auto overflow-x-hidden sidebar-scrollbar">
                    {visibleSections.map((section, sectionIdx) => {
                        const isOpen = openSection === section.title;
                        const sectionHasActive = section.items.some(item => isActive(item.href));
                        const activeItem = findActiveItem(section.items);

                        return (
                            <div key={section.title} className={sectionIdx > 0 ? 'mt-2' : ''}>
                                {/* Section header */}
                                {!collapsed && (
                                    <button
                                        onClick={() => toggleSection(section.title)}
                                        className={`w-full flex items-center justify-between px-3 py-[7px] text-[13px] font-semibold uppercase tracking-[0.04em] transition-colors rounded-md ${
                                            sectionHasActive
                                                ? 'text-blue-400/90'
                                                : 'text-gray-400 hover:text-gray-300'
                                        }`}
                                    >
                                        <span>{section.title}</span>
                                        <ChevronDown
                                            className={`w-3.5 h-3.5 transition-transform duration-250 ease-out ${
                                                isOpen ? 'rotate-0' : '-rotate-90'
                                            }`}
                                        />
                                    </button>
                                )}

                                {/* Collapsible items */}
                                <div className={`sidebar-collapse-content ${isOpen ? 'open' : ''}`}>
                                    <div>
                                        <div className={`${collapsed ? 'space-y-1' : 'space-y-0.5'} ${!collapsed ? 'mt-1 pb-1' : ''}`}>
                                            {section.items.map((item) => {
                                                const active = activeItem?.href === item.href;
                                                return (
                                                    <Link
                                                        key={item.href}
                                                        href={adminUrl(item.href)}
                                                        onClick={() => setSidebarOpen(false)}
                                                        className={`flex items-center gap-3 ${
                                                            collapsed ? 'justify-center px-2' : 'px-3'
                                                        } py-[9px] rounded-md text-[15px] font-medium transition-all duration-150 group relative ${
                                                            active
                                                                ? 'text-white bg-white/[0.08]'
                                                                : 'text-gray-300 hover:text-gray-100 hover:bg-white/[0.05]'
                                                        }`}
                                                        title={collapsed ? item.label : undefined}
                                                    >
                                                        {/* Active accent border */}
                                                        {active && (
                                                            <div className="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-4 rounded-r-full" style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }} />
                                                        )}
                                                        <Icon
                                                            name={item.icon}
                                                            className={`flex-shrink-0 ${
                                                                active
                                                                    ? ''
                                                                    : 'text-gray-400 group-hover:text-gray-300'
                                                            }`}
                                                            style={active ? { color: 'var(--theme-color, #3B82F6)' } : undefined}
                                                        />
                                                        {!collapsed && (
                                                            <span className="truncate">{item.label}</span>
                                                        )}
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

                {/* Workspace switcher */}
                <WorkspaceSwitcher collapsed={collapsed} />

                {/* User section */}
                <div className="p-2.5 border-t border-white/[0.06] flex-shrink-0">
                    <div className={`flex items-center ${collapsed ? 'justify-center' : ''}`}>
                        <div
                            className="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0"
                            style={{ background: 'linear-gradient(135deg, var(--theme-color, #3B82F6), color-mix(in srgb, var(--theme-color, #3B82F6) 80%, black))' }}
                        >
                            {auth?.user?.name?.charAt(0).toUpperCase()}
                        </div>
                        {!collapsed && (
                            <div className="ml-2.5 flex-1 min-w-0">
                                <p className="text-sm font-medium truncate">{auth?.user?.name}</p>
                                <p className="text-xs text-gray-400 dark:text-gray-500 truncate">{auth?.user?.email}</p>
                            </div>
                        )}
                    </div>

                    {!collapsed && (
                        <div className="mt-2.5 flex gap-1.5">
                            <Link
                                href={adminUrl('/profile')}
                                className="flex-1 flex items-center justify-center gap-1.5 px-2.5 py-1.5 text-[11px] font-medium bg-white/[0.08] hover:bg-white/[0.12] rounded-md text-slate-300 hover:text-white transition-colors"
                            >
                                <User className="w-3 h-3" />{t('general.profile')}
                            </Link>
                            <button
                                onClick={logout}
                                className="flex-1 flex items-center justify-center gap-1.5 px-2.5 py-1.5 text-[11px] font-medium bg-red-500/10 hover:bg-red-500/20 rounded-md text-red-400 transition-colors"
                            >
                                <LogOut className="w-3 h-3" />{t('general.logout')}
                            </button>
                        </div>
                    )}

                    {collapsed && (
                        <button
                            onClick={logout}
                            className="mt-2 w-full flex items-center justify-center px-2 py-2 text-xs font-medium bg-red-500/10 hover:bg-red-500/20 rounded-lg text-red-400 transition-colors"
                            title={t('general.logout')}
                        >
                            <LogOut className="w-4 h-4" />
                        </button>
                    )}
                </div>
            </aside>
        </>
    );
}
