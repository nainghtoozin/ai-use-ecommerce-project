import { useState, useEffect, useCallback } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import axios from 'axios';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';

export default function Reports({ promotions, products, categories }) {
    const cc = getCurrencyConfig(usePage().props.platform_setting, usePage().props.website_info);
    const [filters, setFilters] = useState({
        start_date: '',
        end_date: '',
        promotion_id: '',
        product_id: '',
        category_id: '',
    });
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);

    const formatMoney = (amount) => formatCurrency(amount);

    useEffect(() => {
        const now = new Date();
        const thirtyDaysAgo = new Date(now);
        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
        setFilters(prev => ({
            ...prev,
            start_date: thirtyDaysAgo.toISOString().split('T')[0],
            end_date: now.toISOString().split('T')[0],
        }));
    }, []);

    const fetchData = useCallback(async (f) => {
        setLoading(true);
        try {
            const params = {};
            if (f.start_date) params.start_date = f.start_date;
            if (f.end_date) params.end_date = f.end_date;
            if (f.promotion_id) params.promotion_id = f.promotion_id;
            if (f.product_id) params.product_id = f.product_id;
            if (f.category_id) params.category_id = f.category_id;
            const res = await axios.get(adminUrl('/admin/promotions/reports/data'), { params });
            setData(res.data);
        } catch (e) {
            console.error('Failed to fetch report data', e);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (filters.start_date && filters.end_date) {
            fetchData(filters);
        }
    }, [filters, fetchData]);

    const handleFilterChange = (key, value) => {
        setFilters(prev => ({ ...prev, [key]: value }));
    };

    const statCards = data ? [
        { label: 'Total Discounts Given', value: formatMoney(data.summary.total_discounts_given), icon: 'bi-cash', color: 'blue', sub: `${data.summary.orders_using_promotions} orders` },
        { label: 'Orders Using Promos', value: data.summary.orders_using_promotions, icon: 'bi-receipt-cutoff', color: 'violet', sub: `${data.summary.conversion_rate}% of all orders` },
        { label: 'Avg Discount / Order', value: formatMoney(data.summary.avg_discount_per_order), icon: 'bi-percent', color: 'emerald', sub: 'per discounted order' },
        { label: 'Revenue Impact', value: `${data.summary.revenue_impact_percent}%`, icon: 'bi-graph-down-arrow', color: 'rose', sub: `of gross revenue given as discounts` },
        { label: 'Discounted Revenue', value: formatMoney(data.summary.discounted_revenue), icon: 'bi-cart-check', color: 'amber', sub: `${data.summary.orders_using_promotions} orders` },
        { label: 'Non-Discounted Revenue', value: formatMoney(data.summary.non_discounted_revenue), icon: 'bi-cart', color: 'slate', sub: `${data.summary.non_discounted_orders} orders` },
    ] : [];

    const colorMap = {
        blue: { icon: 'text-blue-600', bg: 'bg-blue-50' },
        violet: { icon: 'text-violet-600', bg: 'bg-violet-50' },
        emerald: { icon: 'text-emerald-600', bg: 'bg-emerald-50' },
        rose: { icon: 'text-rose-600', bg: 'bg-rose-50' },
        amber: { icon: 'text-amber-600', bg: 'bg-amber-50' },
        slate: { icon: 'text-slate-600', bg: 'bg-slate-50' },
    };

    function TrendChart({ data: trendData }) {
        if (!trendData?.length) return <p className="text-sm text-gray-500 text-center py-8">No trend data available</p>;
        const maxDiscount = Math.max(...trendData.map(d => d.total_discount), 1);
        return (
            <div className="space-y-2">
                {trendData.map(d => (
                    <div key={d.date} className="flex items-center gap-3">
                        <span className="text-xs text-gray-500 w-20 flex-shrink-0">{d.date}</span>
                        <div className="flex-1 bg-gray-100 rounded-full h-5 overflow-hidden">
                            <div
                                className="h-full bg-blue-500 rounded-full transition-all duration-500"
                                style={{ width: `${(d.total_discount / maxDiscount) * 100}%` }}
                            ></div>
                        </div>
                        <span className="text-xs font-medium text-gray-700 w-24 text-right">{formatMoney(d.total_discount)}</span>
                        <span className="text-xs text-gray-400 w-16 text-right">({d.order_count})</span>
                    </div>
                ))}
            </div>
        );
    }

    function TypeBadge({ type }) {
        const colors = {
            percentage: 'bg-blue-100 text-blue-700',
            fixed: 'bg-emerald-100 text-emerald-700',
            free_shipping: 'bg-amber-100 text-amber-700',
        };
        const labels = {
            percentage: '% Off',
            fixed: 'Fixed',
            free_shipping: 'Free Shipping',
        };
        return (
            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${colors[type] || 'bg-gray-100 text-gray-700'}`}>
                {labels[type] || type}
            </span>
        );
    }

    return (
        <AdminLayout>
            <Head title="Promotion Reports" />

            <div className="p-4 lg:p-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Promotion Reports</h1>
                        <p className="text-sm text-gray-500 mt-1">Analytics and performance metrics for promotions and coupons.</p>
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Start Date</label>
                            <input
                                type="date"
                                value={filters.start_date}
                                onChange={e => handleFilterChange('start_date', e.target.value)}
                                className="w-full rounded-lg border-gray-200 text-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">End Date</label>
                            <input
                                type="date"
                                value={filters.end_date}
                                onChange={e => handleFilterChange('end_date', e.target.value)}
                                className="w-full rounded-lg border-gray-200 text-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Promotion</label>
                            <select
                                value={filters.promotion_id}
                                onChange={e => handleFilterChange('promotion_id', e.target.value)}
                                className="w-full rounded-lg border-gray-200 text-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="">All Promotions</option>
                                {promotions.map(p => (
                                    <option key={p.id} value={p.id}>{p.name} ({p.code})</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Product</label>
                            <select
                                value={filters.product_id}
                                onChange={e => handleFilterChange('product_id', e.target.value)}
                                className="w-full rounded-lg border-gray-200 text-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="">All Products</option>
                                {products.map(p => (
                                    <option key={p.id} value={p.id}>{p.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Category</label>
                            <select
                                value={filters.category_id}
                                onChange={e => handleFilterChange('category_id', e.target.value)}
                                className="w-full rounded-lg border-gray-200 text-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="">All Categories</option>
                                {categories.map(c => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                </div>

                {loading && (
                    <div className="flex items-center justify-center py-12">
                        <div className="flex items-center gap-3 text-gray-500">
                            <div className="w-5 h-5 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                            <span>Loading report data...</span>
                        </div>
                    </div>
                )}

                {!loading && data && (
                    <>
                        {/* Summary Cards */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6 gap-4">
                            {statCards.map((stat, idx) => {
                                const colors = colorMap[stat.color];
                                return (
                                    <div key={idx} className="bg-white rounded-xl border border-gray-200 p-4 lg:p-5 hover:shadow-lg transition-shadow duration-200">
                                        <div className={`p-2 lg:p-2.5 rounded-lg ${colors.bg} inline-flex`}>
                                            <i className={`bi ${stat.icon} text-base lg:text-lg ${colors.icon}`}></i>
                                        </div>
                                        <p className="text-lg sm:text-xl font-bold text-gray-900 mt-2 lg:mt-3 break-words">{stat.value}</p>
                                        <p className="text-xs sm:text-sm text-gray-500 mt-1">{stat.label}</p>
                                        {stat.sub && <p className="text-xs text-gray-400 mt-0.5">{stat.sub}</p>}
                                    </div>
                                );
                            })}
                        </div>

                        {/* Two Column: Top Promotions + Coupon Usage */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {/* Top Performing Promotions */}
                            <div className="bg-white rounded-xl border border-gray-200">
                                <div className="flex items-center justify-between p-5 border-b border-gray-100">
                                    <h2 className="text-lg font-semibold text-gray-900">Top Performing Promotions</h2>
                                </div>
                                <div className="p-5">
                                    {!data.top_promotions?.length ? (
                                        <div className="text-center py-8">
                                            <i className="bi bi-trophy text-4xl text-gray-300"></i>
                                            <p className="text-sm text-gray-500 mt-2">No promotion usage data</p>
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            {data.top_promotions.map((p, i) => (
                                                <div key={p.id} className="flex items-center gap-4 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                                                    <div className="w-8 h-8 rounded-full bg-gradient-to-br from-amber-500 to-amber-600 text-white flex items-center justify-center text-sm font-bold">
                                                        {i + 1}
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <p className="text-sm font-medium text-gray-900 truncate">{p.name}</p>
                                                        <div className="flex items-center gap-2 mt-0.5">
                                                            {p.code && <span className="text-xs font-mono text-gray-400">{p.code}</span>}
                                                            <TypeBadge type={p.type} />
                                                            <span className="text-xs text-gray-400">{p.type === 'percentage' ? `${p.value}%` : formatCurrency(p.value, cc)}</span>
                                                        </div>
                                                    </div>
                                                    <div className="text-right">
                                                        <p className="text-sm font-bold text-gray-900">{formatMoney(p.total_discount)}</p>
                                                        <p className="text-xs text-gray-500">{p.usage_count} uses · {p.unique_users} users</p>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Coupon Usage */}
                            <div className="bg-white rounded-xl border border-gray-200">
                                <div className="flex items-center justify-between p-5 border-b border-gray-100">
                                    <h2 className="text-lg font-semibold text-gray-900">Usage by Coupon</h2>
                                </div>
                                <div className="p-5">
                                    {!data.coupon_usage?.length ? (
                                        <div className="text-center py-8">
                                            <i className="bi bi-ticket text-4xl text-gray-300"></i>
                                            <p className="text-sm text-gray-500 mt-2">No coupon usage data</p>
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            {data.coupon_usage.map((c, i) => (
                                                <div key={c.code + i} className="flex items-center gap-4 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                                                    <div className="w-8 h-8 rounded-full bg-gradient-to-br from-violet-500 to-violet-600 text-white flex items-center justify-center text-sm font-bold">
                                                        {i + 1}
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <p className="text-sm font-medium text-gray-900 truncate">{c.name}</p>
                                                        <div className="flex items-center gap-2 mt-0.5">
                                                            <span className="text-xs font-mono text-gray-400">{c.code}</span>
                                                            <TypeBadge type={c.type} />
                                                        </div>
                                                    </div>
                                                    <div className="text-right">
                                                        <p className="text-sm font-bold text-gray-900">{formatMoney(c.total_discount)}</p>
                                                        <p className="text-xs text-gray-500">{c.usage_count} uses</p>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Three Column Row */}
                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            {/* Daily Discount Trend */}
                            <div className="lg:col-span-2 bg-white rounded-xl border border-gray-200">
                                <div className="flex items-center justify-between p-5 border-b border-gray-100">
                                    <h2 className="text-lg font-semibold text-gray-900">Daily Discount Trend</h2>
                                </div>
                                <div className="p-5">
                                    <TrendChart data={data.daily_trend} />
                                </div>
                            </div>

                            {/* Type Breakdown */}
                            <div className="bg-white rounded-xl border border-gray-200">
                                <div className="flex items-center justify-between p-5 border-b border-gray-100">
                                    <h2 className="text-lg font-semibold text-gray-900">By Type</h2>
                                </div>
                                <div className="p-5">
                                    {!data.type_breakdown?.length ? (
                                        <div className="text-center py-8">
                                            <i className="bi bi-pie-chart text-4xl text-gray-300"></i>
                                            <p className="text-sm text-gray-500 mt-2">No data</p>
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            {data.type_breakdown.map(t => (
                                                <div key={t.type} className="p-4 rounded-lg bg-gray-50">
                                                    <div className="flex items-center justify-between mb-2">
                                                        <TypeBadge type={t.type} />
                                                        <span className="text-sm font-bold text-gray-900">{formatMoney(t.total_discount)}</span>
                                                    </div>
                                                    <p className="text-xs text-gray-500">{t.usage_count} uses</p>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Monthly Comparison */}
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="flex items-center justify-between p-5 border-b border-gray-100">
                                <h2 className="text-lg font-semibold text-gray-900">Monthly Comparison</h2>
                            </div>
                            <div className="p-5 overflow-x-auto">
                                {!data.monthly_comparison?.length ? (
                                    <div className="text-center py-8">
                                        <i className="bi bi-bar-chart text-4xl text-gray-300"></i>
                                        <p className="text-sm text-gray-500 mt-2">No monthly data</p>
                                    </div>
                                ) : (
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-gray-200">
                                                <th className="text-left py-3 px-2 font-medium text-gray-500">Month</th>
                                                <th className="text-right py-3 px-2 font-medium text-gray-500">Total Orders</th>
                                                <th className="text-right py-3 px-2 font-medium text-gray-500">Discounted</th>
                                                <th className="text-right py-3 px-2 font-medium text-gray-500">Conv. Rate</th>
                                                <th className="text-right py-3 px-2 font-medium text-gray-500">Revenue</th>
                                                <th className="text-right py-3 px-2 font-medium text-gray-500">Discounts Given</th>
                                                <th className="text-right py-3 px-2 font-medium text-gray-500">Impact %</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {data.monthly_comparison.map(m => (
                                                <tr key={m.month} className="border-b border-gray-100 hover:bg-gray-50">
                                                    <td className="py-3 px-2 font-medium text-gray-900">{m.label}</td>
                                                    <td className="py-3 px-2 text-right text-gray-700">{m.total_orders}</td>
                                                    <td className="py-3 px-2 text-right text-gray-700">{m.discounted_orders}</td>
                                                    <td className="py-3 px-2 text-right">
                                                        <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${
                                                            m.conversion_rate > 30 ? 'bg-green-100 text-green-700' :
                                                            m.conversion_rate > 10 ? 'bg-blue-100 text-blue-700' :
                                                            'bg-gray-100 text-gray-700'
                                                        }`}>
                                                            {m.conversion_rate}%
                                                        </span>
                                                    </td>
                                                    <td className="py-3 px-2 text-right text-gray-700">{formatMoney(m.total_revenue)}</td>
                                                    <td className="py-3 px-2 text-right text-red-600 font-medium">{formatMoney(m.total_discounts)}</td>
                                                    <td className="py-3 px-2 text-right">
                                                        {m.total_revenue > 0
                                                            ? `${((m.total_discounts / (m.total_revenue + m.total_discounts)) * 100).toFixed(1)}%`
                                                            : '0%'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </div>
                        </div>
                    </>
                )}

                {!loading && !data && (
                    <div className="text-center py-16">
                        <i className="bi bi-bar-chart-line text-5xl text-gray-300"></i>
                        <p className="text-gray-500 mt-3">Select a date range and click the filters to load report data.</p>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
