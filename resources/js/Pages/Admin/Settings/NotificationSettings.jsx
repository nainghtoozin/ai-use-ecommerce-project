import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { Bell, BellRing, Send, ShieldCheck, Package, Users, Archive, AlertTriangle, CheckCircle2 } from 'lucide-react';

const NOTIFICATION_CATEGORIES = [
    {
        key: 'orders',
        label: 'Order Notifications',
        icon: Package,
        color: 'blue',
        items: [
            { key: 'new_order', label: 'New Order', description: 'When a customer places a new order' },
            { key: 'order_cancelled', label: 'Order Cancelled', description: 'When an order is cancelled by customer or admin' },
            { key: 'order_status_changed', label: 'Order Status Changed', description: 'When order status changes (confirmed, shipped, delivered)' },
            { key: 'payment_proof_uploaded', label: 'Payment Proof Uploaded', description: 'When customer uploads payment evidence' },
        ],
    },
    {
        key: 'customers',
        label: 'Customer Notifications',
        icon: Users,
        color: 'purple',
        items: [
            { key: 'new_customer', label: 'New Customer Registration', description: 'When a new customer signs up' },
            { key: 'customer_event', label: 'Customer Events', description: 'Important customer-related events' },
        ],
    },
    {
        key: 'inventory',
        label: 'Inventory Notifications',
        icon: Archive,
        color: 'amber',
        items: [
            { key: 'low_stock', label: 'Low Stock Alert', description: 'When product stock falls below threshold' },
            { key: 'out_of_stock', label: 'Out of Stock', description: 'When a product runs out of stock' },
        ],
    },
    {
        key: 'system',
        label: 'System Notifications',
        icon: AlertTriangle,
        color: 'red',
        items: [
            { key: 'system_alert', label: 'System Alerts', description: 'Critical system notifications and warnings' },
        ],
    },
];

const CHANNEL_ICONS = {
    in_app: Bell,
    telegram: Send,
};

const CHANNEL_LABELS = {
    in_app: 'In-App',
    telegram: 'Telegram',
};

const COLOR_CLASSES = {
    blue: {
        bg: 'bg-blue-50 dark:bg-blue-950/30',
        border: 'border-blue-200 dark:border-blue-800',
        icon: 'text-blue-600 dark:text-blue-400',
        badge: 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300',
    },
    purple: {
        bg: 'bg-purple-50 dark:bg-purple-950/30',
        border: 'border-purple-200 dark:border-purple-800',
        icon: 'text-purple-600 dark:text-purple-400',
        badge: 'bg-purple-100 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300',
    },
    amber: {
        bg: 'bg-amber-50 dark:bg-amber-950/30',
        border: 'border-amber-200 dark:border-amber-800',
        icon: 'text-amber-600 dark:text-amber-400',
        badge: 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300',
    },
    red: {
        bg: 'bg-red-50 dark:bg-red-950/30',
        border: 'border-red-200 dark:border-red-800',
        icon: 'text-red-600 dark:text-red-400',
        badge: 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300',
    },
};

function ToggleSwitch({ enabled, onChange, disabled = false, size = 'md' }) {
    const sizeClasses = {
        sm: 'h-5 w-9',
        md: 'h-6 w-11',
    };
    const knobClasses = {
        sm: 'h-4 w-4 translate-x-4',
        md: 'h-5 w-5 translate-x-5',
    };
    return (
        <button
            type="button"
            role="switch"
            aria-checked={enabled}
            disabled={disabled}
            onClick={() => !disabled && onChange(!enabled)}
            className={`relative inline-flex ${sizeClasses[size]} shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 ${
                enabled ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-700'
            } ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
        >
            <span
                className={`pointer-events-none inline-block ${knobClasses[size]} transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out`}
            />
        </button>
    );
}

function NotificationItem({ item, enabled, onToggle }) {
    return (
        <div className="flex items-center justify-between py-3 px-4 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors group">
            <div className="flex-1 min-w-0 mr-4">
                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{item.label}</p>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{item.description}</p>
            </div>
            <ToggleSwitch
                enabled={enabled}
                onChange={onToggle}
                size="sm"
            />
        </div>
    );
}

function CategorySection({ category, categoryEnabled, onCategoryToggle, itemValues, onItemToggle }) {
    const colors = COLOR_CLASSES[category.color];
    const Icon = category.icon;
    const enabledItems = category.items.filter(item => itemValues[item.key] !== false).length;

    return (
        <div className={`rounded-xl border ${colors.border} ${colors.bg} overflow-hidden`}>
            <div className="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-800/50">
                <div className="flex items-center gap-3">
                    <div className={`p-2 rounded-lg bg-white dark:bg-gray-900 shadow-sm`}>
                        <Icon className={`w-5 h-5 ${colors.icon}`} />
                    </div>
                    <div>
                        <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">{category.label}</h3>
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {enabledItems} of {category.items.length} enabled
                        </p>
                    </div>
                </div>
                <ToggleSwitch
                    enabled={categoryEnabled}
                    onChange={onCategoryToggle}
                />
            </div>

            {categoryEnabled && (
                <div className="divide-y divide-gray-100 dark:divide-gray-800/50">
                    {category.items.map((item) => (
                        <NotificationItem
                            key={item.key}
                            item={item}
                            enabled={itemValues[item.key] !== false}
                            onToggle={(val) => onItemToggle(item.key, val)}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

function ChannelStatus({ channels }) {
    const availableChannels = Object.entries(channels).filter(([_, available]) => available);

    return (
        <div className="flex flex-wrap items-center gap-2 text-xs">
            <span className="text-gray-500 dark:text-gray-400 font-medium">Channels:</span>
            {availableChannels.map(([key, available]) => {
                const Icon = CHANNEL_ICONS[key] || Bell;
                return (
                    <span
                        key={key}
                        className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 font-medium"
                    >
                        <CheckCircle2 className="w-3 h-3" />
                        {CHANNEL_LABELS[key] || key}
                    </span>
                );
            })}
            {availableChannels.length === 0 && (
                <span className="text-gray-400 dark:text-gray-500">No channels configured</span>
            )}
        </div>
    );
}

export default function NotificationSettings({ settings = {}, channels = {} }) {
    const { data, setData, post, processing, errors } = useForm({
        notifications_enabled: settings.notifications_enabled === 'true' || settings.notifications_enabled === true,
        notification_orders_enabled: settings.notification_orders_enabled === 'true' || settings.notification_orders_enabled === true,
        notification_customers_enabled: settings.notification_customers_enabled === 'true' || settings.notification_customers_enabled === true,
        notification_inventory_enabled: settings.notification_inventory_enabled === 'true' || settings.notification_inventory_enabled === true,
        notification_system_enabled: settings.notification_system_enabled === 'true' || settings.notification_system_enabled === true,
        item_new_order: settings.item_new_order !== 'false',
        item_order_cancelled: settings.item_order_cancelled !== 'false',
        item_order_status_changed: settings.item_order_status_changed !== 'false',
        item_payment_proof_uploaded: settings.item_payment_proof_uploaded !== 'false',
        item_new_customer: settings.item_new_customer !== 'false',
        item_customer_event: settings.item_customer_event !== 'false',
        item_low_stock: settings.item_low_stock !== 'false',
        item_out_of_stock: settings.item_out_of_stock !== 'false',
        item_system_alert: settings.item_system_alert !== 'false',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(adminUrl('/admin/settings/notifications'));
    }

    function handleCategoryToggle(key) {
        const enabledKey = `${key}_enabled`;
        setData(enabledKey, !data[enabledKey]);
    }

    function handleItemToggle(key, value) {
        setData(`item_${key}`, value);
    }

    const enabledCount = [
        data.notification_orders_enabled,
        data.notification_customers_enabled,
        data.notification_inventory_enabled,
        data.notification_system_enabled,
    ].filter(Boolean).length;

    return (
        <AdminLayout>
            <Head title="Notification Settings" />

            <div className="px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Notification Settings</h1>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Control which notifications you receive across all channels.
                            </p>
                        </div>
                        <button
                            type="submit"
                            form="notification-settings-form"
                            disabled={processing}
                            className="inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm"
                            style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}
                        >
                            {processing ? (
                                <>
                                    <svg className="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Saving...
                                </>
                            ) : (
                                <>
                                    <CheckCircle2 className="w-4 h-4 mr-2" />
                                    Save Changes
                                </>
                            )}
                        </button>
                    </div>
                </div>

                <form id="notification-settings-form" onSubmit={handleSubmit}>
                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden mb-6">
                        <div className="p-5 sm:p-6 border-b border-gray-100 dark:border-gray-800 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950/30 dark:to-indigo-950/30">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <div className="flex items-center gap-4">
                                    <div className="p-3 rounded-xl bg-white dark:bg-gray-900 shadow-sm">
                                        <BellRing className="w-6 h-6 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Master Switch</h2>
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                                                data.notifications_enabled
                                                    ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300'
                                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'
                                            }`}>
                                                {data.notifications_enabled ? 'Enabled' : 'Disabled'}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            When disabled, no notifications will be sent regardless of individual settings.
                                        </p>
                                    </div>
                                </div>
                                <ToggleSwitch
                                    enabled={data.notifications_enabled}
                                    onChange={(val) => setData('notifications_enabled', val)}
                                    size="md"
                                />
                            </div>
                        </div>

                        <div className="px-5 py-4 bg-gray-50 dark:bg-gray-950/50 border-b border-gray-200 dark:border-gray-800">
                            <ChannelStatus channels={channels} />
                        </div>

                        <div className="px-5 py-3 bg-amber-50 dark:bg-amber-950/30 border-b border-amber-100 dark:border-amber-900/50" style={{ display: data.notifications_enabled ? 'flex' : 'none' }}>
                            <div className="flex items-center gap-2 text-amber-800 dark:text-amber-200">
                                <ShieldCheck className="w-4 h-4" />
                                <span className="text-xs font-medium">
                                    {enabledCount} of 4 notification categories enabled
                                </span>
                            </div>
                        </div>
                    </div>

                    {data.notifications_enabled && (
                        <>
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
                                {NOTIFICATION_CATEGORIES.slice(0, 2).map((category) => (
                                    <CategorySection
                                        key={category.key}
                                        category={category}
                                        categoryEnabled={data[`notification_${category.key}_enabled`]}
                                        onCategoryToggle={() => handleCategoryToggle(`notification_${category.key}`)}
                                        itemValues={{
                                            new_order: data.item_new_order,
                                            order_cancelled: data.item_order_cancelled,
                                            order_status_changed: data.item_order_status_changed,
                                            payment_proof_uploaded: data.item_payment_proof_uploaded,
                                            new_customer: data.item_new_customer,
                                            customer_event: data.item_customer_event,
                                            low_stock: data.item_low_stock,
                                            out_of_stock: data.item_out_of_stock,
                                            system_alert: data.item_system_alert,
                                        }}
                                        onItemToggle={handleItemToggle}
                                    />
                                ))}
                            </div>

                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                                {NOTIFICATION_CATEGORIES.slice(2).map((category) => (
                                    <CategorySection
                                        key={category.key}
                                        category={category}
                                        categoryEnabled={data[`notification_${category.key}_enabled`]}
                                        onCategoryToggle={() => handleCategoryToggle(`notification_${category.key}`)}
                                        itemValues={{
                                            new_order: data.item_new_order,
                                            order_cancelled: data.item_order_cancelled,
                                            order_status_changed: data.item_order_status_changed,
                                            payment_proof_uploaded: data.item_payment_proof_uploaded,
                                            new_customer: data.item_new_customer,
                                            customer_event: data.item_customer_event,
                                            low_stock: data.item_low_stock,
                                            out_of_stock: data.item_out_of_stock,
                                            system_alert: data.item_system_alert,
                                        }}
                                        onItemToggle={handleItemToggle}
                                    />
                                ))}
                            </div>
                        </>
                    )}

                    {!data.notifications_enabled && (
                        <div className="bg-gray-100 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-12 text-center">
                            <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-200 dark:bg-gray-800 flex items-center justify-center">
                                <Bell className="w-8 h-8 text-gray-400" />
                            </div>
                            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">Notifications Disabled</h3>
                            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                                All notifications are currently disabled. Enable the master switch to configure individual notification preferences.
                            </p>
                        </div>
                    )}
                </form>
            </div>
        </AdminLayout>
    );
}
