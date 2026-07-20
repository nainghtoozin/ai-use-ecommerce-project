import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import { useState } from 'react';
import { usePermission } from '@/Hooks/usePermission';

const paymentMethodStyles = {
    kpay:      { icon: 'bi-phone',       bg: 'bg-blue-100', text: 'text-blue-600' },
    wavepay:   { icon: 'bi-phone',       bg: 'bg-cyan-100', text: 'text-cyan-600' },
    'cb pay':  { icon: 'bi-bank',        bg: 'bg-indigo-100', text: 'text-indigo-600' },
    'cb':      { icon: 'bi-bank',        bg: 'bg-indigo-100', text: 'text-indigo-600' },
    visa:      { icon: 'bi-credit-card', bg: 'bg-blue-100', text: 'text-blue-700' },
    mastercard:{ icon: 'bi-credit-card', bg: 'bg-orange-100', text: 'text-orange-600' },
    'cash on delivery': { icon: 'bi-cash-stack', bg: 'bg-green-100', text: 'text-green-600' },
    cod:       { icon: 'bi-cash-stack',  bg: 'bg-green-100', text: 'text-green-600' },
};

function getMethodStyle(name) {
    if (!name) return { icon: 'bi-wallet', bg: 'bg-gray-100', text: 'text-gray-500' };
    const key = name.toLowerCase().replace(/\s+/g, '');
    for (const [k, v] of Object.entries(paymentMethodStyles)) {
        if (key.includes(k.replace(/\s+/g, '')) || name.toLowerCase().includes(k)) {
            return v;
        }
    }
    return { icon: 'bi-wallet', bg: 'bg-gray-100', text: 'text-gray-500' };
}

export default function AdminDashboard({
    totalProducts,
    filteredOrdersCount,
    totalReceivedPayments,
    filteredPendingOrders,
    filteredCustomers,
    lowStockCount,
    orders,
    lowStock,
    outOfStock,
    paymentMethodSummary,
    selectedPeriod,
    startDate,
    endDate,
}) {
    const cc = getCurrencyConfig(usePage().props.platform_setting, usePage().props.website_info);
    const [showCustomDate, setShowCustomDate] = useState(selectedPeriod === 'custom');
    const [customStartDate, setCustomStartDate] = useState(startDate || '');
    const [customEndDate, setCustomEndDate] = useState(endDate || '');

    const formatMoney = (amount) => formatCurrency(amount);

    const timeAgo = (date) => {
        const now = new Date();
        const past = new Date(date);
        const diffMs = now - past;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        return past.toLocaleDateString();
    };

    const periods = [
        { value: 'today', label: 'Today' },
        { value: 'last_7_days', label: 'Last 7 Days' },
        { value: 'last_30_days', label: 'Last 30 Days' },
        { value: 'this_month', label: 'This Month' },
        { value: 'last_month', label: 'Last Month' },
        { value: 'this_year', label: 'This Year' },
        { value: 'custom', label: 'Custom' },
    ];

    const handlePeriodChange = (period) => {
        if (period === 'custom') {
            setShowCustomDate(true);
            return;
        }
        setShowCustomDate(false);
        router.get(adminUrl('/admin/dashboard'), { period }, { preserveState: true, replace: true });
    };

    const handleCustomDateSubmit = () => {
        if (!customStartDate || !customEndDate) return;
        router.get(adminUrl('/admin/dashboard'), {
            period: 'custom',
            start_date: customStartDate,
            end_date: customEndDate,
        }, { preserveState: true, replace: true });
    };

    const statusColors = {
        pending: 'bg-amber-100 text-amber-700',
        confirmed: 'bg-blue-100 text-blue-700',
        shipped: 'bg-violet-100 text-violet-700',
        delivered: 'bg-emerald-100 text-emerald-700',
        completed: 'bg-emerald-100 text-emerald-700',
        cancelled: 'bg-red-100 text-red-700',
        rejected: 'bg-gray-100 text-gray-700',
    };

    const statCards = [
        {
            label: 'Orders',
            value: filteredOrdersCount || 0,
            icon: 'bi-bag-check',
            color: 'blue',
            subtitle: 'Total orders in period',
        },
        {
            label: 'Pending',
            value: filteredPendingOrders || 0,
            icon: 'bi-hourglass-split',
            color: 'amber',
            subtitle: 'Awaiting confirmation',
        },
        {
            label: 'Total Received Payments',
            value: formatMoney(totalReceivedPayments),
            icon: 'bi-cash-stack',
            color: 'emerald',
            subtitle: 'Verified & confirmed payments',
        },
        {
            label: 'Products',
            value: totalProducts || 0,
            icon: 'bi-box-seam',
            color: 'slate',
            subtitle: 'In catalog',
        },
        {
            label: 'Low Stock',
            value: lowStockCount || 0,
            icon: 'bi-exclamation-triangle',
            color: 'red',
            subtitle: 'Less than 10 items',
        },
        {
            label: 'Customers',
            value: filteredCustomers || 0,
            icon: 'bi-people',
            color: 'violet',
            subtitle: 'Who placed orders',
        },
    ];

    const colorMap = {
        blue: { bg: 'bg-blue-50', icon: 'text-blue-600', ring: 'ring-blue-100' },
        amber: { bg: 'bg-amber-50', icon: 'text-amber-600', ring: 'ring-amber-100' },
        emerald: { bg: 'bg-emerald-50', icon: 'text-emerald-600', ring: 'ring-emerald-100' },
        slate: { bg: 'bg-slate-50', icon: 'text-slate-600', ring: 'ring-slate-100' },
        red: { bg: 'bg-red-50', icon: 'text-red-600', ring: 'ring-red-100' },
        violet: { bg: 'bg-violet-50', icon: 'text-violet-600', ring: 'ring-violet-100' },
    };

    const { auth } = usePage().props;
    const { can } = usePermission();
    const subscriptionExpired = auth?.user?.subscription_expired;
    const subscriptionStatus = auth?.user?.subscription?.status;
    const showBanner = subscriptionExpired || subscriptionStatus === 'past_due' || subscriptionStatus === 'suspended';

    const widgetPermissions = {
        Orders: 'orders.view',
        Pending: 'orders.view',
        'Total Received Payments': 'payments.view',
        Products: 'products.view',
        'Low Stock': 'products.view',
        Customers: 'customers.view',
    };

    const visibleStatCards = statCards.filter(s => can(widgetPermissions[s.label]));
    const canViewOrders = can('orders.view');
    const canViewPayments = can('payments.view');
    const canViewProducts = can('products.view');
    const ordersColSpan = canViewOrders && !canViewProducts ? 'lg:col-span-3' : 'lg:col-span-2';
    const hasAnyWidgets = visibleStatCards.length > 0 || canViewOrders || canViewPayments || canViewProducts;

    return (
        <AdminLayout>
            <Head title="Dashboard" />

            <div className="p-6 lg:p-8 space-y-6">
                {showBanner && (
                    <div className={`rounded-xl border p-4 sm:p-5 ${
                        subscriptionStatus === 'suspended'
                            ? 'bg-yellow-50 border-yellow-200'
                            : 'bg-red-50 border-red-200'
                    }`}>
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <div className={`p-2 rounded-full ${
                                    subscriptionStatus === 'suspended'
                                        ? 'bg-yellow-100 text-yellow-600'
                                        : 'bg-red-100 text-red-600'
                                }`}>
                                    <i className={`bi ${
                                        subscriptionStatus === 'suspended'
                                            ? 'bi-pause-circle-fill'
                                            : 'bi-x-circle-fill'
                                    } text-xl`}></i>
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-gray-900">
                                        {subscriptionStatus === 'suspended'
                                            ? 'Your subscription has been suspended'
                                            : 'Your subscription has expired'
                                        }
                                    </p>
                                    <p className="text-sm text-gray-600 mt-0.5">
                                        {subscriptionStatus === 'suspended'
                                            ? 'Please contact support for assistance.'
                                            : 'Renew your subscription to restore full access.'
                                        }
                                    </p>
                                </div>
                            </div>
                            <Link
                                href={adminUrl('/admin/billing')}
                                className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors whitespace-nowrap"
                            >
                                {subscriptionStatus === 'suspended' ? 'Contact Support' : 'Renew Now'}
                            </Link>
                        </div>
                    </div>
                )}

                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
                        <p className="text-sm text-gray-500 mt-1">
                            {periods.find(p => p.value === selectedPeriod)?.label || 'Custom'} overview
                        </p>
                    </div>
                    <div className="flex items-center gap-2 text-sm text-gray-500">
                        <i className="bi bi-calendar3"></i>
                        <span>{new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</span>
                    </div>
                </div>

                {/* Period Filter */}
                <div className="bg-white rounded-xl border border-gray-200 p-4">
                    <div className="flex flex-wrap items-center gap-2">
                        {periods.map((period) => (
                            <button
                                key={period.value}
                                onClick={() => handlePeriodChange(period.value)}
                                className={`px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 ${
                                    selectedPeriod === period.value
                                        ? 'bg-blue-600 text-white shadow-md'
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                }`}
                            >
                                {period.label}
                            </button>
                        ))}
                    </div>

                    {showCustomDate && (
                        <div className="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-gray-100">
                            <input
                                type="date"
                                value={customStartDate}
                                onChange={(e) => setCustomStartDate(e.target.value)}
                                className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                            <span className="text-gray-500 text-sm">to</span>
                            <input
                                type="date"
                                value={customEndDate}
                                onChange={(e) => setCustomEndDate(e.target.value)}
                                className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                            <button
                                onClick={handleCustomDateSubmit}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
                            >
                                Apply
                            </button>
                        </div>
                    )}

                    {selectedPeriod !== 'today' && selectedPeriod !== 'custom' && (
                        <div className="mt-3 pt-3 border-t border-gray-100">
                            <span className="text-sm text-gray-500">
                                Showing data for: <span className="font-medium text-gray-900">
                                    {periods.find(p => p.value === selectedPeriod)?.label}
                                </span>
                            </span>
                        </div>
                    )}

                    {selectedPeriod === 'custom' && startDate && endDate && (
                        <div className="mt-3 pt-3 border-t border-gray-100">
                            <span className="text-sm text-gray-500">
                                Showing data for: <span className="font-medium text-gray-900">
                                    {new Date(startDate).toLocaleDateString()} - {new Date(endDate).toLocaleDateString()}
                                </span>
                            </span>
                        </div>
                    )}
                </div>

                {/* Empty State */}
                {!hasAnyWidgets && (
                    <div className="bg-white rounded-xl border border-gray-200 p-12 text-center">
                        <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gray-100 mb-4">
                            <i className="bi bi-grid-3x3-gap-fill text-2xl text-gray-300"></i>
                        </div>
                        <h3 className="text-base font-semibold text-gray-700 mb-1">No dashboard widgets available</h3>
                        <p className="text-sm text-gray-500">No dashboard widgets are available for your current permissions.</p>
                    </div>
                )}

                {/* Stats Cards */}
                {visibleStatCards.length > 0 && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6 gap-4">
                        {visibleStatCards.map((stat, idx) => {
                            const colors = colorMap[stat.color];
                            return (
                                <div
                                    key={idx}
                                    className="bg-white rounded-xl border border-gray-200 p-4 lg:p-5 hover:shadow-md transition-shadow duration-200"
                                >
                                    <div className="flex items-center gap-3 lg:gap-4">
                                        <div className={`p-2 lg:p-2.5 rounded-lg shrink-0 ${colors.bg}`}>
                                            <i className={`bi ${stat.icon} text-base lg:text-lg ${colors.icon}`}></i>
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-lg sm:text-xl font-bold text-gray-900 break-words">{stat.value}</p>
                                            <p className="text-xs text-gray-500 mt-0.5">{stat.label}</p>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Payment Methods Breakdown */}
                {canViewPayments && paymentMethodSummary?.length > 0 && (
                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="px-5 py-4 border-b border-gray-100">
                            <h2 className="text-sm font-semibold text-gray-700">Payment Methods Breakdown</h2>
                        </div>
                        <div className="p-5">
                            {(() => {
                                const maxTotal = Math.max(...paymentMethodSummary.map(p => Number(p.total)));
                                return (
                                    <div className="space-y-4">
                                        {paymentMethodSummary.map((pm, i) => {
                                            const style = getMethodStyle(pm.name);
                                            const total = Number(pm.total);
                                            const pct = maxTotal > 0 ? (total / maxTotal) * 100 : 0;
                                            return (
                                                <div key={i} className="flex items-center gap-3 lg:gap-4">
                                                    <div className={`p-2 rounded-lg shrink-0 ${style.bg}`}>
                                                        <i className={`bi ${style.icon} ${style.text}`}></i>
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="text-sm font-medium text-gray-900 truncate">
                                                                {pm.name}
                                                            </span>
                                                            <span className="text-sm font-semibold text-gray-900 tabular-nums shrink-0">
                                                                {formatCurrency(total, cc)}
                                                            </span>
                                                        </div>
                                                        <div className="mt-1.5 w-full bg-gray-100 rounded-full h-2">
                                                            <div
                                                                className="h-2 rounded-full transition-all duration-500"
                                                                style={{
                                                                    width: `${Math.max(pct, 4)}%`,
                                                                    backgroundColor: i === 0 ? '#2563eb' : i === 1 ? '#0891b2' : i === 2 ? '#059669' : '#6b7280',
                                                                }}
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                );
                            })()}
                        </div>
                    </div>
                )}

                {/* Recent Orders + Stock Alerts */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Recent Orders */}
                    {canViewOrders && (
                    <div className={`${ordersColSpan} bg-white rounded-xl border border-gray-200`}>
                        <div className="flex items-center justify-between p-5 border-b border-gray-100">
                            <h2 className="text-lg font-semibold text-gray-900">Recent Orders</h2>
                            <Link href={adminUrl('/admin/orders')} className="text-sm text-blue-600 hover:text-blue-700 font-medium">
                                View All <i className="bi bi-arrow-right ml-1"></i>
                            </Link>
                        </div>

                        {!orders?.length ? (
                            <div className="text-center py-12">
                                <i className="bi bi-bag text-4xl text-gray-300"></i>
                                <p className="text-sm text-gray-500 mt-2">No orders yet</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="text-left text-xs text-gray-500 uppercase tracking-wider">
                                            <th className="px-5 py-3 font-medium">Order</th>
                                            <th className="px-5 py-3 font-medium">Customer</th>
                                            <th className="px-5 py-3 font-medium">Amount</th>
                                            <th className="px-5 py-3 font-medium">Status</th>
                                            <th className="px-5 py-3 font-medium">Time</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {orders.slice(0, 8).map((order) => (
                                            <tr
                                                key={order.id}
                                                className="hover:bg-gray-50 transition-colors cursor-pointer"
                                                onClick={() => router.visit(adminUrl(`/admin/orders/${order.id}`))}
                                            >
                                                <td className="px-5 py-4">
                                                    <span className="text-sm font-medium text-gray-900">#{order.id}</span>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <span className="text-sm text-gray-600">
                                                        {order.user?.name || order.customer_name || (order.first_name ? `${order.first_name} ${order.last_name}` : 'N/A')}
                                                    </span>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <span className="text-sm font-semibold text-gray-900">{formatMoney(order.total_amount)}</span>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColors[order.order_status] || 'bg-gray-100 text-gray-700'}`}>
                                                        {order.order_status}
                                                    </span>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <span className="text-sm text-gray-500">{timeAgo(order.created_at)}</span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                    )}

                    {/* Stock Alerts */}
                    {canViewProducts && (
                    <div className="space-y-6">
                        {outOfStock?.data?.length > 0 && (
                            <div className="bg-white rounded-xl border border-red-200">
                                <div className="flex items-center gap-3 p-4 border-b border-red-100">
                                    <div className="p-2 bg-red-100 rounded-lg">
                                        <i className="bi bi-exclamation-triangle text-red-600"></i>
                                    </div>
                                    <div>
                                        <h3 className="text-sm font-semibold text-red-900">Out of Stock</h3>
                                        <p className="text-xs text-red-600">{outOfStock.data.length} products</p>
                                    </div>
                                </div>
                                <div className="p-4 space-y-3">
                                    {outOfStock.data.slice(0, 4).map((product) => (
                                        <div key={product.id} className="flex items-center gap-3">
                                            {product.photo1_url ? (
                                                <img src={product.photo1_url} alt={product.name} className="w-8 h-8 object-cover rounded-lg" />
                                            ) : (
                                                <div className="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-xs">
                                                    <i className="bi bi-box"></i>
                                                </div>
                                            )}
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-gray-900 truncate">{product.name}</p>
                                                <p className="text-xs text-red-600">Out of stock</p>
                                            </div>
                                            <Link
                                                href={adminUrl(`/admin/products/${product.id}/edit`)}
                                                className="text-xs text-red-600 hover:text-red-700 font-medium"
                                            >
                                                Restock
                                            </Link>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {lowStock?.data?.length > 0 && (
                            <div className="bg-white rounded-xl border border-amber-200">
                                <div className="flex items-center gap-3 p-4 border-b border-amber-100">
                                    <div className="p-2 bg-amber-100 rounded-lg">
                                        <i className="bi bi-exclamation-circle text-amber-600"></i>
                                    </div>
                                    <div>
                                        <h3 className="text-sm font-semibold text-amber-900">Low Stock</h3>
                                        <p className="text-xs text-amber-600">{lowStock.data.length} products</p>
                                    </div>
                                </div>
                                <div className="p-4 space-y-3">
                                    {lowStock.data.slice(0, 4).map((product) => (
                                        <div key={product.id} className="flex items-center gap-3">
                                            {product.photo1_url ? (
                                                <img src={product.photo1_url} alt={product.name} className="w-8 h-8 object-cover rounded-lg" />
                                            ) : (
                                                <div className="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-xs">
                                                    <i className="bi bi-box"></i>
                                                </div>
                                            )}
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-gray-900 truncate">{product.name}</p>
                                                <p className="text-xs text-amber-600">Only {product.stock} left</p>
                                            </div>
                                            <Link
                                                href={adminUrl(`/admin/products/${product.id}/edit`)}
                                                className="text-xs text-amber-600 hover:text-amber-700 font-medium"
                                            >
                                                Update
                                            </Link>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {(!outOfStock?.data?.length && !lowStock?.data?.length) && (
                            <div className="bg-white rounded-xl border border-gray-200 p-6 text-center">
                                <i className="bi bi-check-circle text-3xl text-emerald-400"></i>
                                <p className="text-sm text-gray-500 mt-2">All products are in stock</p>
                            </div>
                        )}
                    </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
