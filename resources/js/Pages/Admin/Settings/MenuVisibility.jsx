import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { useTranslation } from '@/Utils/useTranslation';

const MENU_GROUPS = [
    {
        key: 'catalog',
        children: [
            { key: 'catalog.products' },
            { key: 'catalog.categories' },
            { key: 'catalog.brands' },
            { key: 'catalog.units' },
        ],
    },
    {
        key: 'inventory',
        children: [
            { key: 'inventory.dashboard' },
            { key: 'inventory.products' },
            { key: 'inventory.stock_history' },
            { key: 'inventory.movements' },
            { key: 'inventory.adjustments' },
        ],
    },
    {
        key: 'sales',
        children: [
            { key: 'sales.orders' },
            { key: 'sales.payment_methods' },
        ],
    },
    {
        key: 'marketing',
        children: [
            { key: 'marketing.coupons' },
            { key: 'marketing.promotions' },
            { key: 'marketing.flash_sales' },
        ],
    },
    {
        key: 'billing',
        children: [
            { key: 'billing.overview' },
            { key: 'billing.subscription' },
            { key: 'billing.upgrade' },
            { key: 'billing.invoices' },
            { key: 'billing.history' },
            { key: 'billing.settings' },
        ],
    },
    {
        key: 'analytics',
        children: [
            { key: 'analytics.sales' },
            { key: 'analytics.products' },
            { key: 'analytics.payments' },
        ],
    },
    {
        key: 'locations',
        children: [
            { key: 'locations.cities' },
            { key: 'locations.townships' },
        ],
    },
    {
        key: 'staff',
        children: [
            { key: 'staff.members' },
            { key: 'staff.staff' },
            { key: 'staff.roles' },
            { key: 'staff.activity' },
            { key: 'staff.audit' },
            { key: 'staff.notifications' },
        ],
    },
    {
        key: 'content',
        children: [
            { key: 'content.faq' },
        ],
    },
    {
        key: 'storefront',
        children: [
            { key: 'storefront.overview' },
            { key: 'storefront.homepage' },
            { key: 'storefront.navigation' },
            { key: 'storefront.media' },
            { key: 'storefront.promotions' },
        ],
    },
    {
        key: 'settings',
        children: [
            { key: 'settings.website' },
            { key: 'settings.notifications' },
            { key: 'settings.telegram' },
            { key: 'settings.setup_guide' },
            { key: 'settings.general' },
            { key: 'settings.menu_visibility' },
        ],
    },
];

const ITEM_KEYS = {
    'catalog.products': 'products',
    'catalog.categories': 'categories',
    'catalog.brands': 'brands',
    'catalog.units': 'units',
    'inventory.dashboard': 'dashboard',
    'inventory.products': 'products_inventory',
    'inventory.stock_history': 'stock_history',
    'inventory.movements': 'stock_movements',
    'inventory.adjustments': 'stock_adjustments',
    'sales.orders': 'orders',
    'sales.payment_methods': 'payment_methods',
    'marketing.coupons': 'coupons',
    'marketing.promotions': 'promotions',
    'marketing.flash_sales': 'flash_sales',
    'billing.overview': 'overview',
    'billing.subscription': 'subscription',
    'billing.upgrade': 'upgrade',
    'billing.invoices': 'invoices',
    'billing.history': 'history',
    'billing.settings': 'settings',
    'analytics.sales': 'sales_reports',
    'analytics.products': 'product_reports',
    'analytics.payments': 'payment_reports',
    'locations.cities': 'cities',
    'locations.townships': 'townships',
    'staff.members': 'members',
    'staff.staff': 'staff',
    'staff.roles': 'roles',
    'staff.activity': 'activity_log',
    'staff.audit': 'audit_log',
    'staff.notifications': 'notifications',
    'content.faq': 'faq',
    'storefront.overview': 'storefront_overview',
    'storefront.homepage': 'storefront_homepage',
    'storefront.navigation': 'storefront_navigation',
    'storefront.media': 'storefront_media',
    'storefront.promotions': 'storefront_promotions',
    'settings.website': 'website_settings',
    'settings.notifications': 'notification_settings',
    'settings.telegram': 'telegram_integration',
    'settings.setup_guide': 'setup_guide',
    'settings.general': 'general_settings',
    'settings.menu_visibility': 'menu_visibility',
};

function Toggle({ on, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            role="switch"
            aria-checked={on}
            className={`relative inline-flex w-11 h-6 shrink-0 items-center rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-blue-500 dark:focus-visible:ring-offset-gray-900 ${
                on ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'
            }`}
        >
            <span
                className={`inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition-transform ${
                    on ? 'translate-x-5' : 'translate-x-0.5'
                }`}
            />
        </button>
    );
}

export default function MenuVisibility({ menuVisibility }) {
    const { t } = useTranslation();
    const [visibility, setVisibility] = useState(menuVisibility || {});
    const [saving, setSaving] = useState(false);

    const toggleSection = (sectionKey) => {
        const section = MENU_GROUPS.find(g => g.key === sectionKey);
        if (!section) return;

        const newValue = !visibility[sectionKey];
        const updated = { ...visibility, [sectionKey]: newValue };

        section.children.forEach(child => {
            updated[child.key] = newValue;
        });

        setVisibility(updated);
    };

    const toggleItem = (itemKey) => {
        setVisibility(prev => ({ ...prev, [itemKey]: !prev[itemKey] }));
    };

    const isSectionOn = (sectionKey) => visibility[sectionKey] === true;
    const isItemOn = (itemKey) => visibility[itemKey] === true;

    const handleSave = () => {
        setSaving(true);
        router.post(adminUrl('/admin/settings/menu-visibility'), { visibility }, {
            onFinish: () => setSaving(false),
        });
    };

    const handleShowAll = () => {
        const allOn = {};
        MENU_GROUPS.forEach(group => {
            allOn[group.key] = true;
            group.children.forEach(child => {
                allOn[child.key] = true;
            });
        });
        setVisibility(allOn);
    };

    const handleResetDefaults = () => {
        router.post(adminUrl('/admin/settings/menu-visibility/reset-defaults'), {}, {
            onSuccess: (page) => {
                const defaults = page.props.menuVisibility || {};
                setVisibility(defaults);
            },
        });
    };

    const sectionCount = MENU_GROUPS.filter(g => isSectionOn(g.key)).length;

    return (
        <AdminLayout>
            <Head title={t('admin_menu_visibility.title')} />

            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-8">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {t('admin_menu_visibility.title')}
                    </h1>
                    <p className="mt-1.5 text-sm text-gray-600 dark:text-gray-400">
                        {t('admin_menu_visibility.description')}
                    </p>
                    <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        {t('admin_menu_visibility.help_text')}
                    </p>
                </div>

                <div className="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
                    <button
                        onClick={handleShowAll}
                        className="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:text-blue-400 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors"
                    >
                        {t('admin_menu_visibility.show_all')}
                    </button>
                    <button
                        onClick={handleResetDefaults}
                        className="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 dark:bg-gray-800 dark:text-gray-400 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                    >
                        {t('admin_menu_visibility.reset_defaults')}
                    </button>
                    <div className="flex-1" />
                    <button
                        onClick={handleSave}
                        disabled={saving}
                        className="inline-flex items-center justify-center px-5 py-2 text-sm font-medium text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}
                    >
                        {saving ? t('admin_menu_visibility.saving') : t('admin_menu_visibility.save_changes')}
                    </button>
                </div>

                <div className="space-y-3 divide-y divide-gray-100 dark:divide-gray-800 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden bg-white dark:bg-gray-900">
                    {MENU_GROUPS.map(group => {
                        const sectionOn = isSectionOn(group.key);
                        const visibleChildren = group.children.filter(c => isItemOn(c.key));

                        return (
                            <div key={group.key} className={sectionOn ? '' : 'opacity-70'}>
                                <div className="flex items-center justify-between px-5 py-4">
                                    <div className="flex items-center gap-3">
                                        <Toggle on={sectionOn} onClick={() => toggleSection(group.key)} />
                                        <div>
                                            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                {t(`admin_menu_visibility.sections.${group.key}`)}
                                            </h3>
                                            <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                                {t(`admin_menu_visibility.descriptions.${group.key}`)}
                                            </p>
                                        </div>
                                    </div>
                                    <span className="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">
                                        {t('admin_menu_visibility.enabled_count', {
                                            enabled: visibleChildren.length,
                                            total: group.children.length,
                                        })}
                                    </span>
                                </div>

                                {sectionOn && (
                                    <div className="px-5 pb-3 space-y-0.5">
                                        {group.children.map(child => {
                                            const itemOn = isItemOn(child.key);
                                            return (
                                                <div
                                                    key={child.key}
                                                    className="flex items-center justify-between py-2.5 px-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                                                >
                                                    <span className="text-sm text-gray-700 dark:text-gray-300">
                                                        {t(`admin_menu_visibility.items.${ITEM_KEYS[child.key] || child.key}`)}
                                                    </span>
                                                    <Toggle on={itemOn} onClick={() => toggleItem(child.key)} />
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>

                <p className="mt-6 text-xs text-gray-400 dark:text-gray-500 text-center">
                    {t('admin_menu_visibility.enabled_count', {
                        enabled: sectionCount,
                        total: MENU_GROUPS.length,
                    })}
                </p>
            </div>
        </AdminLayout>
    );
}
