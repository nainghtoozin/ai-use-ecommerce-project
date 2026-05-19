import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { assetUrl } from '@/Utils/helpers';
import { useState } from 'react';

export default function AdminDashboard({
    totalProducts,
    totalOrders,
    totalRevenue,
    totalSales,
    revenueToday,
    revenueYesterday,
    revenueLast7Days,
    revenueLast30Days,
    revenueThisMonth,
    revenueLastMonth,
    revenueThisYear,
    netrevenueToday,
    netrevenueYesterday,
    netrevenueLast7Days,
    netrevenueLast30Days,
    netrevenueThisMonth,
    netrevenueLastMonth,
    netrevenueThisYear,
    growthTodayVsYesterday,
    growthThisMonthVsLastMonth,
    topSelling,
    orders,
    outOfStock,
    lowStock,
    pendingOrders,
    verifiedRevenue,
    activePromotions,
    promotionDiscountsThisMonth,
    mostUsedCoupon,
    selectedPeriod,
    startDate,
    endDate,
    filteredOrdersCount,
    filteredRevenue,
    filteredSales,
    filteredPendingOrders,
    filteredVerifiedRevenue,
    growthPercentage,
}) {
    const { url } = usePage();
    const [showCustomDate, setShowCustomDate] = useState(selectedPeriod === 'custom');
    const [customStartDate, setCustomStartDate] = useState(startDate || '');
    const [customEndDate, setCustomEndDate] = useState(endDate || '');

    const formatMoney = (amount) => Number(amount || 0).toLocaleString() + ' MMK';

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
        const params = new URLSearchParams(window.location.search);
        params.set('period', period);
        
        if (period === 'custom') {
            setShowCustomDate(true);
            return;
        } else {
            setShowCustomDate(false);
            params.delete('start_date');
            params.delete('end_date');
        }

        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const handleCustomDateSubmit = () => {
        if (!customStartDate || !customEndDate) return;
        
        const params = new URLSearchParams();
        params.set('period', 'custom');
        params.set('start_date', customStartDate);
        params.set('end_date', customEndDate);

        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    function GrowthBadge({ value }) {
        if (value === undefined || value === null) return null;
        const isPositive = value >= 0;
        return (
            <span className={`inline-flex items-center gap-1 text-xs font-medium ${isPositive ? 'text-emerald-600' : 'text-red-600'}`}>
                <i className={`bi bi-arrow-${isPositive ? 'up' : 'down'}-right`}></i>
                {isPositive ? '+' : ''}{Math.abs(Number(value)).toFixed(1)}%
            </span>
        );
    }

    const statCards = [
        { label: 'Total Sales', value: filteredSales || 0, icon: 'bi-bag-check', color: 'blue', bg: 'bg-blue-50' },
        { label: 'Total Revenue', value: formatMoney(filteredRevenue), icon: 'bi-cash-stack', color: 'green', bg: 'bg-emerald-50' },
        { label: 'Total Orders', value: filteredOrdersCount || 0, icon: 'bi-receipt', color: 'violet', bg: 'bg-violet-50' },
        { label: 'Pending Orders', value: filteredPendingOrders || 0, icon: 'bi-hourglass-split', color: 'amber', bg: 'bg-amber-50' },
        { label: 'Verified Revenue', value: formatMoney(filteredVerifiedRevenue), icon: 'bi-check-circle', color: 'emerald', bg: 'bg-green-50' },
        { label: 'Products', value: totalProducts || 0, icon: 'bi-box-seam', color: 'slate', bg: 'bg-slate-50' },
    ];

    const colorMap = {
        blue: { icon: 'text-blue-600', ring: 'ring-blue-100' },
        green: { icon: 'text-emerald-600', ring: 'ring-emerald-100' },
        violet: { icon: 'text-violet-600', ring: 'ring-violet-100' },
        amber: { icon: 'text-amber-600', ring: 'ring-amber-100' },
        emerald: { icon: 'text-green-600', ring: 'ring-green-100' },
        slate: { icon: 'text-slate-600', ring: 'ring-slate-100' },
    };

    return (
        <AdminLayout>
            <Head title="Dashboard" />

            <div className="p-4 lg:p-6 space-y-6">
                {/* Welcome Section */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Dashboard Overview</h1>
                        <p className="text-sm text-gray-500 mt-1">Welcome back! Here's what's happening with your store.</p>
                    </div>
                    <div className="flex items-center gap-2 text-sm text-gray-500">
                        <i className="bi bi-calendar3"></i>
                        <span>{new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</span>
                    </div>
                </div>

                {/* Period Filter */}
                <div className="bg-white rounded-xl border border-gray-200 p-4">
                    <div className="flex flex-col lg:flex-row lg:items-center gap-4">
                        <div className="flex flex-wrap gap-2">
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
                            <div className="flex flex-wrap items-center gap-2">
                                <input
                                    type="date"
                                    value={customStartDate}
                                    onChange={(e) => setCustomStartDate(e.target.value)}
                                    className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                />
                                <span className="text-gray-500">to</span>
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
                    </div>
                    {selectedPeriod !== 'today' && (
                        <div className="mt-3 pt-3 border-t border-gray-100">
                            <span className="text-sm text-gray-500">
                                Showing data for: <span className="font-medium text-gray-900">{periods.find(p => p.value === selectedPeriod)?.label || 'Custom'}</span>
                                {selectedPeriod === 'custom' && startDate && endDate && (
                                    <span> ({new Date(startDate).toLocaleDateString()} - {new Date(endDate).toLocaleDateString()})</span>
                                )}
                                {growthPercentage !== null && (
                                    <span className="ml-4">
                                        Growth: <GrowthBadge value={growthPercentage} />
                                    </span>
                                )}
                            </span>
                        </div>
                    )}
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6 gap-3 lg:gap-4">
                    {statCards.map((stat, idx) => {
                        const colors = colorMap[stat.color];
                        return (
                            <div key={idx} className="bg-white rounded-xl border border-gray-200 p-4 lg:p-5 hover:shadow-lg transition-shadow duration-200">
                                <div className="flex items-start justify-between">
                                    <div className={`p-2 rounded-lg ${stat.bg}`}>
                                        <i className={`bi ${stat.icon} text-base lg:text-lg ${colors.icon}`}></i>
                                    </div>
                                    {stat.label === 'Total Revenue' && growthTodayVsYesterday !== null && (
                                        <GrowthBadge value={growthTodayVsYesterday} />
                                    )}
                                </div>
                                <div className="mt-3 lg:mt-4">
                                    <p className="text-xl lg:text-2xl font-bold text-gray-900 truncate">{stat.value}</p>
                                    <p className="text-xs lg:text-sm text-gray-500 mt-1">{stat.label}</p>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Revenue Analytics */}
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
                        <h2 className="text-lg font-semibold text-gray-900">Revenue Analytics</h2>
                        <div className="flex items-center gap-4 text-sm">
                            <div className="flex items-center gap-2">
                                <span className="w-3 h-3 rounded-full bg-blue-500"></span>
                                <span className="text-gray-500">Gross</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="w-3 h-3 rounded-full bg-emerald-500"></span>
                                <span className="text-gray-500">Net</span>
                            </div>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-7 gap-3 lg:gap-4">
                        {[
                            { label: 'Today', gross: revenueToday, net: netrevenueToday, growth: growthTodayVsYesterday },
                            { label: 'Yesterday', gross: revenueYesterday, net: netrevenueYesterday },
                            { label: 'Last 7 Days', gross: revenueLast7Days, net: netrevenueLast7Days },
                            { label: 'Last 30 Days', gross: revenueLast30Days, net: netrevenueLast30Days },
                            { label: 'This Month', gross: revenueThisMonth, net: netrevenueThisMonth, growth: growthThisMonthVsLastMonth },
                            { label: 'Last Month', gross: revenueLastMonth, net: netrevenueLastMonth },
                            { label: 'This Year', gross: revenueThisYear, net: netrevenueThisYear },
                        ].map((item, idx) => (
                            <div key={idx} className="p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors">
                                <p className="text-xs font-medium text-gray-500 mb-2">{item.label}</p>
                                <p className="text-base font-bold text-gray-900">{formatMoney(item.gross)}</p>
                                <p className="text-xs text-gray-400 mt-1">Net: {formatMoney(item.net)}</p>
                                {item.growth !== undefined && <div className="mt-2"><GrowthBadge value={item.growth} /></div>}
                            </div>
                        ))}
                    </div>
                </div>

                {/* Two Column Layout */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Top Selling Products */}
                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="flex items-center justify-between p-5 border-b border-gray-100">
                            <h2 className="text-lg font-semibold text-gray-900">Top Selling Products</h2>
                            <Link href="/admin/products" className="text-sm text-blue-600 hover:text-blue-700 font-medium">
                                View All <i className="bi bi-arrow-right ml-1"></i>
                            </Link>
                        </div>
                        <div className="p-5">
                            {!topSelling?.data?.length ? (
                                <div className="text-center py-8">
                                    <i className="bi bi-graph-up-arrow text-4xl text-gray-300"></i>
                                    <p className="text-sm text-gray-500 mt-2">No sales data yet</p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {topSelling.data.slice(0, 5).map((product, index) => (
                                        <div key={product.id} className="flex items-center gap-4 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                                            <div className="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 text-white flex items-center justify-center text-sm font-bold">
                                                {index + 1}
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-gray-900 truncate">{product.name}</p>
                                                <p className="text-xs text-gray-500">{product.category?.name || 'Uncategorized'}</p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-bold text-gray-900">{product.total_sold}</p>
                                                <p className="text-xs text-gray-500">sold</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Recent Orders */}
                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="flex items-center justify-between p-5 border-b border-gray-100">
                            <h2 className="text-lg font-semibold text-gray-900">Recent Orders</h2>
                            <Link href="/admin/orders" className="text-sm text-blue-600 hover:text-blue-700 font-medium">
                                View All <i className="bi bi-arrow-right ml-1"></i>
                            </Link>
                        </div>
                        <div className="p-5">
                            {!orders?.length ? (
                                <div className="text-center py-8">
                                    <i className="bi bi-bag text-4xl text-gray-300"></i>
                                    <p className="text-sm text-gray-500 mt-2">No orders yet</p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {orders.slice(0, 5).map((order) => {
                                        const statusColors = {
                                            pending: 'bg-amber-100 text-amber-700',
                                            confirmed: 'bg-blue-100 text-blue-700',
                                            shipped: 'bg-violet-100 text-violet-700',
                                            delivered: 'bg-emerald-100 text-emerald-700',
                                            completed: 'bg-emerald-100 text-emerald-700',
                                            cancelled: 'bg-red-100 text-red-700',
                                            rejected: 'bg-gray-100 text-gray-700',
                                        };
                                        return (
                                            <Link
                                                key={order.id}
                                                href={`/admin/orders/${order.id}`}
                                                className="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors group"
                                            >
                                                <div className="flex items-center gap-3">
                                                    <div className="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                                                        <i className="bi bi-receipt"></i>
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium text-gray-900">Order #{order.id}</p>
                                                        <p className="text-xs text-gray-500">
                                                            {order.user?.name || order.customer_name || `${order.first_name} ${order.last_name}`}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-sm font-bold text-gray-900">{formatMoney(order.total_amount)}</p>
                                                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[order.order_status] || 'bg-gray-100 text-gray-700'}`}>
                                                        {order.order_status}
                                                    </span>
                                                </div>
                                            </Link>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Stock Alerts */}
                {(outOfStock?.data?.length > 0 || lowStock?.data?.length > 0) && (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Out of Stock */}
                        {outOfStock?.data?.length > 0 && (
                            <div className="bg-white rounded-xl border border-red-200">
                                <div className="flex items-center gap-3 p-5 border-b border-red-100 bg-red-50/50 rounded-t-xl">
                                    <div className="p-2 bg-red-100 rounded-lg">
                                        <i className="bi bi-exclamation-triangle text-red-600"></i>
                                    </div>
                                    <h2 className="text-lg font-semibold text-red-900">Out of Stock</h2>
                                    <span className="ml-auto px-2.5 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full">
                                        {outOfStock.data.length}
                                    </span>
                                </div>
                                <div className="p-5 space-y-3">
                                    {outOfStock.data.slice(0, 4).map((product) => (
                                        <div key={product.id} className="flex items-center gap-3 p-2 rounded-lg hover:bg-red-50 transition-colors">
                                            {product.photo1_url ? (
                                                <img src={product.photo1_url} alt={product.name} className="w-10 h-10 object-cover rounded-lg" />
                                            ) : (
                                                <div className="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center text-gray-400 text-xs">
                                                    N/A
                                                </div>
                                            )}
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-gray-900 truncate">{product.name}</p>
                                                <p className="text-xs text-red-600">Out of stock</p>
                                            </div>
                                            <Link
                                                href={`/admin/products/${product.id}/edit`}
                                                className="px-3 py-1.5 text-xs font-medium text-white bg-red-500 hover:bg-red-600 rounded-lg transition-colors"
                                            >
                                                Restock
                                            </Link>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Low Stock */}
                        {lowStock?.data?.length > 0 && (
                            <div className="bg-white rounded-xl border border-amber-200">
                                <div className="flex items-center gap-3 p-5 border-b border-amber-100 bg-amber-50/50 rounded-t-xl">
                                    <div className="p-2 bg-amber-100 rounded-lg">
                                        <i className="bi bi-exclamation-circle text-amber-600"></i>
                                    </div>
                                    <h2 className="text-lg font-semibold text-amber-900">Low Stock</h2>
                                    <span className="ml-auto px-2.5 py-0.5 bg-amber-500 text-white text-xs font-bold rounded-full">
                                        {lowStock.data.length}
                                    </span>
                                </div>
                                <div className="p-5 space-y-3">
                                    {lowStock.data.slice(0, 4).map((product) => (
                                        <div key={product.id} className="flex items-center gap-3 p-2 rounded-lg hover:bg-amber-50 transition-colors">
                                            {product.photo1_url ? (
                                                <img src={product.photo1_url} alt={product.name} className="w-10 h-10 object-cover rounded-lg" />
                                            ) : (
                                                <div className="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center text-gray-400 text-xs">
                                                    N/A
                                                </div>
                                            )}
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-gray-900 truncate">{product.name}</p>
                                                <p className="text-xs text-amber-600">Only {product.stock} left</p>
                                            </div>
                                            <Link
                                                href={`/admin/products/${product.id}/edit`}
                                                className="px-3 py-1.5 text-xs font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-lg transition-colors"
                                            >
                                                Update
                                            </Link>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Promotion Insights Widgets */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="bg-white rounded-xl border border-blue-200 p-5">
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 bg-blue-50 rounded-lg">
                                <i className="bi bi-megaphone text-lg text-blue-600"></i>
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-gray-900">{activePromotions ?? 0}</p>
                                <p className="text-sm text-gray-500">Active Promotions</p>
                            </div>
                        </div>
                        <Link
                            href="/admin/promotions"
                            className="mt-3 inline-flex items-center text-xs font-medium text-blue-600 hover:text-blue-700"
                        >
                            Manage Promotions <i className="bi bi-arrow-right ml-1"></i>
                        </Link>
                    </div>

                    <div className="bg-white rounded-xl border border-emerald-200 p-5">
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 bg-emerald-50 rounded-lg">
                                <i className="bi bi-cash-stack text-lg text-emerald-600"></i>
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-gray-900">{formatMoney(promotionDiscountsThisMonth)}</p>
                                <p className="text-sm text-gray-500">Discounts This Month</p>
                            </div>
                        </div>
                        <Link
                            href="/admin/promotions/reports"
                            className="mt-3 inline-flex items-center text-xs font-medium text-emerald-600 hover:text-emerald-700"
                        >
                            View Reports <i className="bi bi-arrow-right ml-1"></i>
                        </Link>
                    </div>

                    <div className="bg-white rounded-xl border border-violet-200 p-5">
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 bg-violet-50 rounded-lg">
                                <i className="bi bi-ticket-perforated text-lg text-violet-600"></i>
                            </div>
                            <div>
                                {mostUsedCoupon ? (
                                    <>
                                        <p className="text-lg font-bold text-gray-900 truncate">{mostUsedCoupon.code}</p>
                                        <p className="text-sm text-gray-500">{mostUsedCoupon.usage_count} uses · {mostUsedCoupon.name}</p>
                                    </>
                                ) : (
                                    <>
                                        <p className="text-2xl font-bold text-gray-900">0</p>
                                        <p className="text-sm text-gray-500">Most Used Coupon</p>
                                    </>
                                )}
                            </div>
                        </div>
                        <Link
                            href="/admin/promotions/reports"
                            className="mt-3 inline-flex items-center text-xs font-medium text-violet-600 hover:text-violet-700"
                        >
                            View Coupon Stats <i className="bi bi-arrow-right ml-1"></i>
                        </Link>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}