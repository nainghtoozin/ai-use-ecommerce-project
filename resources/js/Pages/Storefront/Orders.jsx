import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';

const statusColors = {
    pending: 'bg-amber-50 text-amber-700 border border-amber-200',
    confirmed: 'bg-blue-50 text-blue-700 border border-blue-200',
    processing: 'bg-indigo-50 text-indigo-700 border border-indigo-200',
    shipped: 'bg-sky-50 text-sky-700 border border-sky-200',
    delivered: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    cancelled: 'bg-red-50 text-red-700 border border-red-200',
};

const paymentColors = {
    unpaid: 'bg-gray-50 text-gray-600 border border-gray-200',
    paid: 'bg-blue-50 text-blue-700 border border-blue-200',
    pending: 'bg-amber-50 text-amber-700 border border-amber-200',
    verified: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    rejected: 'bg-red-50 text-red-700 border border-red-200',
};

export default function Orders({ tenant, orders, filters = {} }) {
    const cc = getCurrencyConfig(usePage().props.platform_setting, usePage().props.website_info);
    const storeSlug = tenant.slug;

    const [search, setSearch] = useState(filters.search || '');
    const [orderStatus, setOrderStatus] = useState(filters.order_status || '');
    const [paymentStatus, setPaymentStatus] = useState(filters.payment_status || '');
    const [dateRange, setDateRange] = useState(filters.date_range || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [sort, setSort] = useState(filters.sort || 'newest');
    const [showCustomDate, setShowCustomDate] = useState(filters.date_range === 'custom');

    function applyFilters(overrides = {}) {
        const params = {
            search,
            order_status: orderStatus,
            payment_status: paymentStatus,
            date_range: dateRange,
            date_from: dateRange === 'custom' ? dateFrom : '',
            date_to: dateRange === 'custom' ? dateTo : '',
            sort,
            ...overrides,
        };
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
        router.get(route('storefront.customer.orders', { store_slug: storeSlug }), params, { preserveState: true });
    }

    function clearFilters() {
        setSearch('');
        setOrderStatus('');
        setPaymentStatus('');
        setDateRange('');
        setDateFrom('');
        setDateTo('');
        setSort('newest');
        setShowCustomDate(false);
        router.get(route('storefront.customer.orders', { store_slug: storeSlug }));
    }

    const hasActiveFilters = search || orderStatus || paymentStatus || dateRange || sort !== 'newest';

    return (
        <ShopLayout>
            <Head title={`My Orders - ${tenant.name}`} />

            <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">My Orders</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Track and manage your orders.</p>
                    </div>
                    <Link href={route('storefront.customer.account', { store_slug: storeSlug })} className="text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors">
                        &larr; Back to Account
                    </Link>
                </div>

                {/* Filters */}
                <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm p-5 mb-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Filters</h2>
                        {hasActiveFilters && (
                            <button onClick={clearFilters} className="text-xs text-blue-600 hover:text-blue-800 font-medium">Clear all</button>
                        )}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div className="sm:col-span-2 lg:col-span-4">
                            <div className="flex gap-2">
                                <input
                                    type="text"
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                    onKeyDown={e => e.key === 'Enter' && applyFilters()}
                                    placeholder="Search by invoice number, order ID, or product name..."
                                    className="flex-1 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <button onClick={() => applyFilters()} className="px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 text-sm font-medium transition-colors">
                                    Search
                                </button>
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Order Status</label>
                            <select value={orderStatus} onChange={e => { setOrderStatus(e.target.value); applyFilters({ order_status: e.target.value }); }} className="w-full border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Payment Status</label>
                            <select value={paymentStatus} onChange={e => { setPaymentStatus(e.target.value); applyFilters({ payment_status: e.target.value }); }} className="w-full border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Payments</option>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="verified">Verified</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Date Range</label>
                            <select value={dateRange} onChange={e => { const v = e.target.value; setDateRange(v); setShowCustomDate(v === 'custom'); if (v !== 'custom') applyFilters({ date_range: v }); }} className="w-full border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Time</option>
                                <option value="today">Today</option>
                                <option value="7days">Last 7 Days</option>
                                <option value="30days">Last 30 Days</option>
                                <option value="this_month">This Month</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Sort By</label>
                            <select value={sort} onChange={e => { setSort(e.target.value); applyFilters({ sort: e.target.value }); }} className="w-full border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="amount_high">Highest Amount</option>
                                <option value="amount_low">Lowest Amount</option>
                            </select>
                        </div>
                    </div>

                    {showCustomDate && (
                        <div className="grid grid-cols-2 gap-3 mt-3">
                            <div>
                                <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">From</label>
                                <input type="date" value={dateFrom} onChange={e => setDateFrom(e.target.value)} className="w-full border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">To</label>
                                <div className="flex gap-2">
                                    <input type="date" value={dateTo} onChange={e => setDateTo(e.target.value)} className="flex-1 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    <button onClick={() => applyFilters({ date_range: 'custom', date_from: dateFrom, date_to: dateTo })} className="px-4 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 text-sm font-medium transition-colors">Apply</button>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Results */}
                {!orders?.data?.length ? (
                    <div className="text-center py-16 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm">
                        <svg className="w-14 h-14 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                        <h3 className="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">{hasActiveFilters ? 'No orders match your filters' : 'No orders yet'}</h3>
                        <p className="mt-2 text-base text-gray-500 dark:text-gray-400">{hasActiveFilters ? 'Try adjusting your search or filter criteria.' : 'Your order history will appear here once you make a purchase.'}</p>
                        {hasActiveFilters ? (
                            <button onClick={clearFilters} className="mt-6 inline-block px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 text-base font-medium transition-colors">
                                Clear Filters
                            </button>
                        ) : (
                            <Link href={route('storefront.index', { store_slug: storeSlug })} className="mt-6 inline-block px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 text-base font-medium transition-colors">
                                Start Shopping
                            </Link>
                        )}
                    </div>
                ) : (
                    <div className="space-y-4">
                        <p className="text-sm text-gray-500 dark:text-gray-400">{orders.total} order{orders.total !== 1 ? 's' : ''} found</p>
                        {orders.data.map((order) => (
                            <div key={order.id} className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                                <div className="p-5">
                                    <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2.5 flex-wrap">
                                                <h3 className="text-base font-bold text-gray-900 dark:text-gray-100 font-mono">{order.invoice_number}</h3>
                                                <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${statusColors[order.order_status] || 'bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-800'}`}>
                                                    {order.order_status}
                                                </span>
                                                <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${paymentColors[order.payment_status] || 'bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-800'}`}>
                                                    {order.payment_status}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-3 mt-2 text-sm text-gray-500 dark:text-gray-400">
                                                <span>{new Date(order.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</span>
                                                <span>&middot;</span>
                                                <span>{order.payment_method?.name || 'N/A'}</span>
                                                <span>&middot;</span>
                                                <span>{order.items_count ?? order.items?.reduce((s, i) => s + i.quantity, 0)} item{(order.items_count ?? order.items?.reduce((s, i) => s + i.quantity, 0)) !== 1 ? 's' : ''}</span>
                                            </div>
                                        </div>
                                        <div className="text-left sm:text-right shrink-0">
                                            <p className="text-xl font-bold text-gray-900 dark:text-gray-100">{formatCurrency(order.total_amount, cc)}</p>
                                        </div>
                                    </div>

                                    {order.items?.length > 0 && (
                                        <div className="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800 flex gap-2 overflow-hidden">
                                            {order.items.slice(0, 4).map((item) => (
                                                <span key={item.id} className="inline-flex items-center gap-1.5 bg-gray-50 dark:bg-gray-950 px-2.5 py-1 rounded-lg text-sm text-gray-600 dark:text-gray-400">
                                                    <span className="font-medium text-gray-800 dark:text-gray-200 truncate max-w-[120px]">{item.product?.name || 'Product'}</span>
                                                    <span className="text-gray-400 dark:text-gray-500">&times;{item.quantity}</span>
                                                </span>
                                            ))}
                                            {order.items.length > 4 && (
                                                <span className="inline-flex items-center text-sm text-gray-500 dark:text-gray-400 px-1">+{order.items.length - 4} more</span>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="px-5 py-3 bg-gray-50 dark:bg-gray-950 border-t border-gray-100 dark:border-gray-800 flex items-center gap-2">
                                    <Link href={route('storefront.customer.orders.show', { store_slug: storeSlug, order: order.id })} className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        View Order
                                    </Link>
                                    <a href={route('storefront.customer.orders.invoice', { store_slug: storeSlug, invoice_number: order.invoice_number })} target="_blank" className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:bg-gray-800 rounded-lg transition-colors">
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                        Invoice
                                    </a>
                                </div>
                            </div>
                        ))}

                        {orders.links?.length > 3 && (
                            <div className="flex justify-center gap-1 pt-4">
                                {orders.links.map((link, i) => (
                                    <Link key={i} href={link.url || '#'} preserveState className={`px-4 py-2 text-sm rounded-xl transition-colors ${link.active ? 'bg-blue-600 text-white font-medium' : link.url ? 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:bg-gray-800' : 'text-gray-400 cursor-not-allowed'}`}>
                                        {link.label.replace('&laquo;', '\u00ab').replace('&raquo;', '\u00bb').replace('Previous', '\u2190').replace('Next', '\u2192')}
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
