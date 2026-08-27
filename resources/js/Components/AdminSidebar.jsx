import { useState, useMemo, useEffect, useRef, useCallback } from 'react';
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
    Users, ShieldCheck,
    Bell, Globe, BellRing, Send, Settings,
    Store, User, LogOut, Menu, X,
    ChevronLeft, ChevronRight,
    FileText, Ruler, Layers, Zap,
    Rocket, HelpCircle,
    LayoutTemplate, Images, LayoutList,
    ChevronDown, Navigation, Archive, Clock,
    Activity, Shield, UserPlus, ArrowUp,
} from 'lucide-react';

const SECTION_VIS_KEY = {
    'Overview': 'overview',
    'Catalog': 'catalog',
    'Sales': 'sales',
    'Store': 'store',
    'Website': 'website',
    'Business': 'business',
    'Billing': 'billing',
    'Settings': 'settings',
    'Analytics': 'analytics',
    'Orders & Payments': 'sales',
    'Marketing': 'marketing',
    'Locations': 'locations',
    'Team': 'staff',
    'Content': 'content',
};

const iconMap = {
    LayoutDashboard, Package, Tags, Megaphone,
    BarChart3, ShoppingBag, Receipt,
    ShoppingCart, CreditCard,
    Building2, MapPin,
    Users, ShieldCheck,
    Bell, Globe, BellRing, Send, Settings,
    FileText, Ruler, Layers, Zap,
    Rocket, HelpCircle,
    LayoutTemplate, Images, LayoutList,
    ChevronDown, Navigation, Archive, Clock,
    Activity, Shield, UserPlus, ArrowUp,
};

function Icon({ name, className = '', ...props }) {
    const LucideIcon = iconMap[name];
    if (!LucideIcon) return null;
    return <LucideIcon className={`w-[18px] h-[18px] ${className}`} {...props} />;
}

function SubmenuHeight({ open, children }) {
    const ref = useRef(null);
    const [height, setHeight] = useState(0);

    useEffect(() => {
        if (!ref.current) return;
        if (open) {
            const el = ref.current;
            setHeight(el.scrollHeight);
            const timer = setTimeout(() => setHeight('auto'), 150);
            return () => clearTimeout(timer);
        } else {
            const el = ref.current;
            setHeight(el.scrollHeight);
            requestAnimationFrame(() => {
                requestAnimationFrame(() => setHeight(0));
            });
        }
    }, [open]);

    return (
        <div
            ref={ref}
            style={{
                height: height === 'auto' ? 'auto' : `${height}px`,
                overflow: 'hidden',
                transition: 'height 150ms ease',
            }}
            aria-hidden={!open}
        >
            {children}
        </div>
    );
}

export default function AdminSidebar() {
    const { props, url } = usePage();
    const { auth, website_info, platform_setting, tenant, featureStatus, menuVisibility } = props;
    const { t } = useTranslation();
    const userPermissions = auth?.user?.permissions;
    const isSuperAdmin = auth?.user?.is_superadmin;
    const isOwner = auth?.user?.is_owner;
    const can = (perm) => isSuperAdmin || isOwner || userPermissions?.includes(perm);
    const hasFeature = (key) => featureStatus?.[key]?.enabled !== false;
    const vis = menuVisibility || {};
    const isVis = (key) => vis[key] !== false;

    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(false);
    const [openGroup, setOpenGroup] = useState(null);
    const userToggledRef = useRef(false);

    const brandLogo = isSuperAdmin ? platform_setting?.site_logo : website_info?.logo;
    const brandName = isSuperAdmin ? (platform_setting?.site_name || 'SuperAdmin') : (tenant?.name || website_info?.site_name || 'My Store');
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
                    ...(can('dashboard.view') && isVis('overview') ? [{ label: t('navigation.dashboard'), href: '/admin/dashboard', icon: 'LayoutDashboard' }] : []),
                ]
            },
            {
                title: 'Catalog',
                items: [
                    ...(can('products.view') && isVis('catalog.products') ? [{ label: t('navigation.products'), href: '/admin/products', icon: 'Package' }] : []),
                    ...(can('categories.view') && isVis('catalog.categories') ? [{ label: t('navigation.categories'), href: '/admin/categories', icon: 'Tags' }] : []),
                    ...(can('brands.view') && isVis('catalog.brands') ? [{ label: t('navigation.brands'), href: '/admin/brands', icon: 'Layers' }] : []),
                    ...(can('units.view') && isVis('catalog.units') ? [{ label: t('navigation.units'), href: '/admin/units', icon: 'Ruler' }] : []),
                    ...(can('inventory.view') && hasFeature('inventory_management') && isVis('inventory.dashboard') ? [{ label: t('navigation.inventory'), href: '/admin/inventory/dashboard', icon: 'Archive' }] : []),
                ]
            },
            {
                title: 'Sales',
                items: [
                    ...(can('orders.view') && isVis('sales.orders') ? [{ label: t('navigation.orders'), href: '/admin/orders', icon: 'ShoppingCart' }] : []),
                    ...(can('payments.view') && isVis('sales.payment_methods') ? [{ label: t('navigation.payment_methods'), href: '/admin/payment-methods', icon: 'CreditCard' }] : []),
                    ...(can('coupons.view') && hasFeature('coupons') && isVis('marketing.coupons') ? [{ label: t('navigation.coupons'), href: '/admin/coupons', icon: 'Tags' }] : []),
                    ...(can('promotions.view') && hasFeature('promotions') && isVis('marketing.promotions') ? [{ label: t('navigation.promotions'), href: '/admin/promotions', icon: 'Megaphone' }] : []),
                    ...(hasFeature('flash_sales') && isVis('marketing.flash_sales') ? [{ label: t('navigation.flash_sales'), href: '/admin/flash-sales', icon: 'Zap' }] : []),
                ]
            },
            {
                title: 'Store',
                items: [
                    ...(can('settings.website') && isVis('settings.website') ? [
                        { label: 'Storefront', href: '/admin/storefront', icon: 'LayoutTemplate' },
                        { label: 'Homepage', href: '/admin/storefront/homepage', icon: 'LayoutList' },
                        { label: 'Header & Navigation', href: '/admin/storefront/navigation', icon: 'Navigation' },
                        { label: 'Promotions', href: '/admin/storefront/promotions', icon: 'Megaphone' },
                        { label: 'Media', href: '/admin/storefront/media', icon: 'Images' },
                    ] : []),
                    ...(can('products.view') && isVis('content.faq') ? [{ label: 'FAQ', href: '/admin/faqs', icon: 'HelpCircle' }] : []),
                ]
            },
            {
                title: 'Website',
                items: [
                    ...(can('settings.website') && isVis('settings.website') ? [{ label: 'Website Settings', href: '/admin/website-info/edit', icon: 'Globe' }] : []),
                    ...(isOwner && isVis('settings.menu_visibility') ? [{ label: 'Menu Visibility', href: '/admin/settings/menu-visibility', icon: 'Layers' }] : []),
                ]
            },
            {
                title: 'Locations',
                items: [
                    ...(can('cities.view') && isVis('locations.cities') ? [{ label: t('navigation.cities'), href: '/admin/cities', icon: 'Building2' }] : []),
                    ...(can('townships.view') && isVis('locations.townships') ? [{ label: t('navigation.townships'), href: '/admin/townships', icon: 'MapPin' }] : []),
                ]
            },
            {
                title: 'Business',
                items: [
                    ...((can('users.view') || auth?.user?.is_owner) && isVis('staff.staff') ? [{ label: 'Team', href: '/admin/team', icon: 'Users' }] : []),
                    ...(can('users.view') && isVis('staff.members') ? [{ label: t('navigation.members'), href: '/admin/users', icon: 'UserPlus' }] : []),
                ]
            },
            ...(can('billing.view') ? [{
                title: 'Billing',
                items: [
                    ...(isVis('billing.overview') ? [{ label: 'Overview', href: '/admin/billing', icon: 'CreditCard' }] : []),
                    ...(isVis('billing.subscription') ? [{ label: 'Subscription', href: '/admin/billing/subscription', icon: 'FileText' }] : []),
                    ...(isVis('billing.upgrade') ? [{ label: 'Upgrade', href: '/admin/billing/upgrade', icon: 'ArrowUp' }] : []),
                    ...(isVis('billing.invoices') ? [{ label: 'Invoices', href: '/admin/billing/invoices', icon: 'Receipt' }] : []),
                    ...(isVis('billing.history') ? [{ label: 'Payment History', href: '/admin/billing/payment-history', icon: 'Clock' }] : []),
                    ...(isVis('billing.settings') ? [{ label: t('navigation.settings'), href: '/admin/billing/settings', icon: 'Settings' }] : []),
                ]
            }] : []),
            ...(can('reports.sales') && hasFeature('reports') ? [{
                title: 'Analytics',
                items: [
                    ...(isVis('analytics.sales') ? [{ label: 'Sales', href: '/admin/reports/sales', icon: 'BarChart3' }] : []),
                    ...(isVis('analytics.products') ? [{ label: t('navigation.products'), href: '/admin/reports/product-sales', icon: 'ShoppingBag' }] : []),
                    ...(isVis('analytics.payments') ? [{ label: 'Payments', href: '/admin/reports/payments', icon: 'Receipt' }] : []),
                ]
            }] : []),
            {
                title: t('navigation.settings'),
                items: [
                    ...(can('settings.view') && isVis('settings.general') ? [{ label: t('navigation.general'), href: '/admin/settings', icon: 'Settings' }] : []),
                    ...(can('settings.notifications') && isVis('settings.notifications') ? [{ label: t('navigation.notifications'), href: '/admin/settings/notifications', icon: 'BellRing' }] : []),
                    ...(can('settings.telegram') && isVis('settings.telegram') ? [{ label: t('navigation.telegram'), href: '/admin/settings/telegram-integration', icon: 'Send' }] : []),
                    ...(isOwner && isVis('settings.setup_guide') ? [{ label: 'Setup Guide', href: '/admin/settings/onboarding', icon: 'Rocket' }] : []),
                    ...(can('activity.view') && isVis('staff.activity') ? [{ label: 'Activity Log', href: '/admin/activity-logs', icon: 'Activity' }] : []),
                    ...(can('audit.view') && isVis('staff.audit') ? [{ label: 'Audit Log', href: '/admin/audit-logs', icon: 'ShieldCheck' }] : []),
                ]
            }
        ];
    }, [userPermissions, isSuperAdmin, t, menuVisibility]);

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

    function sectionHasActiveItem(section) {
        return section.items.some(item => isActive(item.href));
    }

    const storeSlug = tenant?.slug;
    const logout = () => router.post('/logout', {
        context: isSuperAdmin ? 'superadmin' : 'admin',
        store_slug: storeSlug,
    });

    const visibleSections = menuSections.filter((section) => {
        const visibilityKey = section.visibilityKey || SECTION_VIS_KEY[section.title];
        return section.items.length > 0 && (!visibilityKey || isVis(visibilityKey));
    });

    const merchant = !isSuperAdmin;

    useEffect(() => {
        if (collapsed) return;
        if (userToggledRef.current) return;
        const activeSection = visibleSections.find(s => sectionHasActiveItem(s));
        if (activeSection) {
            setOpenGroup(activeSection.title);
        }
    }, [url, collapsed, visibleSections]);

    useEffect(() => {
        if (collapsed) {
            setOpenGroup(null);
        }
    }, [collapsed]);

    const handleToggleGroup = useCallback((title) => {
        userToggledRef.current = true;
        setOpenGroup(prev => prev === title ? null : title);
    }, []);

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
                    background: ${merchant ? 'rgba(100, 116, 139, 0.18)' : 'rgba(255, 255, 255, 0.1)'};
                    border-radius: 9999px;
                }
                .sidebar-scrollbar::-webkit-scrollbar-thumb:hover {
                    background: ${merchant ? 'rgba(71, 85, 105, 0.3)' : 'rgba(255, 255, 255, 0.18)'};
                }
            `}</style>

            {/* Mobile toggle */}
            <button
                onClick={() => setSidebarOpen(!sidebarOpen)}
                className={`lg:hidden fixed top-3 left-3 z-50 p-2 rounded-lg shadow-lg transition-colors ${merchant ? 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' : 'bg-slate-900 text-white hover:bg-slate-800'}`}
                aria-label={sidebarOpen ? 'Close menu' : 'Open menu'}
            >
                {sidebarOpen ? <X className="w-5 h-5 text-white" /> : <Menu className="w-5 h-5 text-white" />}
            </button>

            {/* Mobile overlay */}
            {sidebarOpen && (
                <div className="lg:hidden fixed inset-0 bg-black/60 z-40 backdrop-blur-sm" onClick={() => setSidebarOpen(false)} />
            )}

            <aside
                className={`fixed lg:sticky top-0 left-0 z-40 h-screen flex flex-col transition-all duration-300 ease-in-out ${merchant ? 'bg-[#F8FAFC] text-slate-800 border-r border-slate-200' : 'bg-slate-900 text-white'} ${collapsed ? 'w-[72px]' : 'w-[260px]'} ${sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}`}
            >
                {/* Header */}
                <div className={`h-14 flex items-center ${collapsed ? 'justify-center px-2' : 'px-4'} flex-shrink-0 ${merchant ? 'border-b border-slate-200' : 'border-b border-white/[0.06]'}`}>
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
                            <span className={`font-semibold truncate ${merchant ? 'text-[15px] text-slate-900' : 'text-sm'}`}>{brandName}</span>
                        </div>
                    )}
                </div>

                {/* Collapse toggle */}
                <div className="hidden lg:block absolute -right-3 top-20 z-50">
                    <button
                        onClick={() => setCollapsed(!collapsed)}
                        className={`w-6 h-6 rounded-full flex items-center justify-center shadow-sm border transition-colors ${merchant ? 'bg-white hover:bg-slate-50 border-slate-200 text-slate-500' : 'bg-slate-700 hover:bg-slate-600 border-slate-600/50'}`}
                        aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    >
                        {collapsed ? <ChevronRight className="w-3 h-3" /> : <ChevronLeft className="w-3 h-3" />}
                    </button>
                </div>

                {/* Navigation */}
                <nav className="flex-1 px-2.5 py-2 overflow-y-auto overflow-x-hidden sidebar-scrollbar">
                    {visibleSections.map((section, sectionIdx) => {
                        const sectionHasActive = sectionHasActiveItem(section);
                        const activeItem = findActiveItem(section.items);
                        const isGroupOpen = collapsed || openGroup === section.title;

                        return (
                            <div key={section.title} className={sectionIdx > 0 ? 'mt-3' : ''}>
                                {/* Group header button (accordion trigger) */}
                                {!collapsed && (
                                    <button
                                        type="button"
                                        onClick={() => handleToggleGroup(section.title)}
                                        aria-expanded={isGroupOpen}
                                        className={`w-full flex items-center gap-1.5 px-3 py-1.5 uppercase select-none text-[12px] font-semibold tracking-wider rounded-md transition-colors ${
                                            merchant
                                                ? sectionHasActive ? 'text-slate-800' : 'text-slate-500 hover:text-slate-700'
                                                : sectionHasActive ? 'text-white/90' : 'text-gray-500 hover:text-gray-400'
                                        }`}
                                    >
                                        <ChevronDown
                                            className={`w-3.5 h-3.5 flex-shrink-0 transition-transform duration-150 ${
                                                isGroupOpen ? 'rotate-0' : '-rotate-90'
                                            }`}
                                        />
                                        <span className="truncate">{section.title}</span>
                                    </button>
                                )}

                                {/* Items */}
                                <SubmenuHeight open={isGroupOpen}>
                                    <div className="space-y-0.5">
                                        {section.items.map((item) => {
                                            const active = activeItem?.href === item.href;
                                            const accentColor = 'var(--theme-color, #3B82F6)';
                                            return (
                                                <Link
                                                    key={item.href}
                                                    href={adminUrl(item.href)}
                                                    onClick={() => setSidebarOpen(false)}
                                                    style={active && merchant ? { backgroundColor: `color-mix(in srgb, ${accentColor} 10%, transparent)` } : undefined}
                                                    className={`flex items-center gap-2.5 ${collapsed ? 'justify-center px-2' : 'px-3 pl-4'} h-[38px] rounded-lg text-[14px] transition-colors duration-150 group relative ${
                                                        active
                                                            ? merchant ? 'font-semibold' : 'text-white bg-white/[0.08] font-semibold'
                                                            : merchant ? 'font-medium text-slate-700 hover:text-slate-900 hover:bg-slate-100/60' : 'font-medium text-gray-300 hover:text-gray-100 hover:bg-white/[0.05]'
                                                    }`}
                                                    title={collapsed ? item.label : undefined}
                                                >
                                                    {/* Active accent indicator */}
                                                    {active && (
                                                        <span
                                                            className="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-4 rounded-r-full"
                                                            style={{ backgroundColor: accentColor }}
                                                        />
                                                    )}
                                                    <Icon
                                                        name={item.icon}
                                                        strokeWidth={active ? 2.2 : 1.8}
                                                        className={`flex-shrink-0 ${
                                                            active
                                                                ? ''
                                                                : merchant ? 'text-slate-500 group-hover:text-slate-700' : 'text-gray-400 group-hover:text-gray-300'
                                                        }`}
                                                        {...(active ? { style: { color: accentColor } } : {})}
                                                    />
                                                    {!collapsed && (
                                                        <span className="truncate" style={active && merchant ? { color: accentColor } : undefined}>{item.label}</span>
                                                    )}
                                                </Link>
                                            );
                                        })}
                                    </div>
                                </SubmenuHeight>
                            </div>
                        );
                    })}
                </nav>

                {/* Workspace switcher */}
                 <WorkspaceSwitcher collapsed={collapsed} light={merchant} />

                {/* User section */}
                <div className={`p-2.5 border-t flex-shrink-0 ${merchant ? 'border-slate-200 bg-white' : 'border-white/[0.06]'}`}>
                    <div className={`flex items-center ${collapsed ? 'justify-center' : ''}`}>
                        <div
                            className="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0"
                            style={{ background: 'linear-gradient(135deg, var(--theme-color, #3B82F6), color-mix(in srgb, var(--theme-color, #3B82F6) 80%, black))' }}
                        >
                            {auth?.user?.name?.charAt(0).toUpperCase()}
                        </div>
                        {!collapsed && (
                            <div className="ml-2.5 flex-1 min-w-0">
                                 <p className={`font-semibold truncate ${merchant ? 'text-[14px] text-slate-900' : 'text-sm'}`}>{auth?.user?.name}</p>
                                 <p className={`truncate ${merchant ? 'text-[12px] text-slate-500' : 'text-xs text-gray-400 dark:text-gray-500'}`}>{auth?.user?.email}</p>
                            </div>
                        )}
                    </div>

                    {!collapsed && (
                        <div className="mt-2 flex gap-1.5">
                            <Link
                                href={adminUrl('/profile')}
                                 className={`flex-1 flex items-center justify-center gap-1.5 px-2.5 py-1.5 text-[12px] font-medium rounded-md transition-colors ${merchant ? 'bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900' : 'bg-white/[0.08] hover:bg-white/[0.12] text-slate-300 hover:text-white'}`}
                            >
                                <User className="w-3.5 h-3.5" />{t('general.profile')}
                            </Link>
                             <button
                                onClick={logout}
                                 className={`flex-1 flex items-center justify-center gap-1.5 px-2.5 py-1.5 text-[12px] font-medium rounded-md transition-colors ${merchant ? 'bg-red-50 hover:bg-red-100 text-red-600' : 'bg-red-500/10 hover:bg-red-500/20 text-red-400'}`}
                            >
                                <LogOut className="w-3.5 h-3.5" />{t('general.logout')}
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
