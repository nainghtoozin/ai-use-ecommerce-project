import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import { isCodUnpaid, codPaymentStatusLabel, codPaymentMethodLabel } from '@/Utils/codDisplay';
import { Search, SlidersHorizontal, Eye, FileText, X, ShoppingBag, Download } from 'lucide-react';

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

const STATUS_CHIPS = [
    { value: '', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'processing', label: 'Processing' },
    { value: 'shipped', label: 'Shipped' },
    { value: 'delivered', label: 'Delivered' },
    { value: 'cancelled', label: 'Cancelled' },
];

export default function Orders({ tenant, orders, filters = {} }) {
    const { storefront } = usePage().props;
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
    const [downloadingInvoiceId, setDownloadingInvoiceId] = useState(null);

    async function downloadInvoice(order) {
        if (downloadingInvoiceId) return;
        setDownloadingInvoiceId(order.id);
        try {
            const res = await fetch(route('storefront.customer.orders.invoice', { store_slug: storeSlug, invoice_number: order.invoice_number }));
            if (!res.ok) throw new Error('download failed');
            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `invoice-${order.invoice_number}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } catch (e) {
            alert('Unable to download invoice. Please try again.');
        } finally {
            setDownloadingInvoiceId(null);
        }
    }

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

    const [filtersOpen, setFiltersOpen] = useState(false);
    const hasActiveFilters = search || orderStatus || paymentStatus || dateRange || sort !== 'newest';
    const activeFilterCount = [search, orderStatus, paymentStatus, dateRange, sort !== 'newest' ? sort : ''].filter(Boolean).length;

    function clearOneFilter(key) {
        const clears = {
            search: () => setSearch(''),
            order_status: () => setOrderStatus(''),
            payment_status: () => setPaymentStatus(''),
            date_range: () => { setDateRange(''); setDateFrom(''); setDateTo(''); setShowCustomDate(false); },
            sort: () => setSort('newest'),
        };
        const next = {
            search: key === 'search' ? '' : search,
            order_status: key === 'order_status' ? '' : orderStatus,
            payment_status: key === 'payment_status' ? '' : paymentStatus,
            date_range: key === 'date_range' ? '' : dateRange,
            date_from: key === 'date_range' ? '' : (dateRange === 'custom' ? dateFrom : ''),
            date_to: key === 'date_range' ? '' : (dateRange === 'custom' ? dateTo : ''),
            sort: key === 'sort' ? 'newest' : sort,
        };
        (clears[key] || (() => {}))();
        applyFilters(next);
    }

    const statusLabel = (v) => v ? v.charAt(0).toUpperCase() + v.slice(1) : v;
    const paymentLabel = (v) => v === 'due_on_delivery' ? 'Due on Delivery' : statusLabel(v);
    const filterChips = [
        search && { key: 'search', label: `"${search}"` },
        orderStatus && { key: 'order_status', label: statusLabel(orderStatus) },
        paymentStatus && { key: 'payment_status', label: paymentLabel(paymentStatus) },
        dateRange && { key: 'date_range', label: dateRange === 'custom' ? `${dateFrom || '?'} → ${dateTo || '?'}` : statusLabel(dateRange) },
        sort !== 'newest' && { key: 'sort', label: `Sort: ${statusLabel(sort.replace('_', ' '))}` },
    ].filter(Boolean);

    const itemCount = (order) => order.items_count ?? order.items?.reduce((s, i) => s + i.quantity, 0) ?? 0;

    const InvoiceButton = ({ order, compact = false }) => (
        <button
            type="button"
            onClick={() => downloadInvoice(order)}
            disabled={downloadingInvoiceId === order.id}
            className={compact
                ? 'flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:bg-gray-800 rounded-lg transition-colors disabled:opacity-50'
                : 'inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:bg-gray-800 rounded-lg transition-colors disabled:opacity-50'}
            aria-label="Download invoice"
        >
            <Download className={compact ? 'w-4 h-4' : 'w-4 h-4'} />
            {downloadingInvoiceId === order.id ? 'Preparing…' : 'Download Invoice'}
        </button>
    );

    return (
        <ShopLayout>
            <Head title={`My Orders - ${storefront?.identity?.site_title || tenant.name}`} />

            {/* ===== MOBILE LAYOUT (hidden on md+) ===== */}
            <div className="md:hidden">
                <div className="px-4 pt-4 pb-24">
                    <div className="mb-3">
                        <h1 className="text-xl font-bold text-gray-900 dark:text-gray-100">My Orders</h1>
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {orders?.total ? `${orders.total} order${orders.total !== 1 ? 's' : ''}` : 'Track and manage your orders.'}
                        </p>
                    </div>

                    <div className="flex gap-2 mb-3">
                        <div className="relative flex-1">
                            <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                type="text"
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && applyFilters()}
                                placeholder="Search orders..."
                                className="w-full pl-9 pr-3 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-900"
                            />
                            {search && (
                                <button onClick={() => { setSearch(''); applyFilters({ search: '' }); }} className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <X className="w-4 h-4" />
                                </button>
                            )}
                        </div>
                        <button
                            onClick={() => setFiltersOpen(o => !o)}
                            className="relative inline-flex items-center justify-center w-10 h-10 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 flex-shrink-0"
                            aria-label="Open filters"
                        >
                            <SlidersHorizontal className="w-4 h-4" />
                            {activeFilterCount > 0 && (
                                <span className="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-[16px] h-[16px] px-1 rounded-full bg-blue-600 text-white text-[10px] font-semibold">
                                    {activeFilterCount}
                                </span>
                            )}
                        </button>
                    </div>

                    <div className="flex gap-2 overflow-x-auto pb-2 -mx-1 px-1 mb-3" style={{ scrollbarWidth: 'none' }}>
                        {STATUS_CHIPS.map(chip => (
                            <button
                                key={chip.value}
                                onClick={() => { setOrderStatus(chip.value); applyFilters({ order_status: chip.value }); }}
                                className={`flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-medium border transition-colors ${
                                    orderStatus === chip.value
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-700'
                                }`}
                            >
                                {chip.label}
                            </button>
                        ))}
                    </div>

                    {filterChips.length > 0 && (
                        <div className="flex flex-wrap gap-2 mb-3">
                            {filterChips.map(chip => (
                                <button key={chip.key} onClick={() => clearOneFilter(chip.key)} className="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 dark:bg-blue-900/30 border border-blue-100 dark:border-blue-900/40 text-blue-700 dark:text-blue-300 rounded-lg text-xs font-medium">
                                    {chip.label}
                                    <X className="w-3 h-3" />
                                </button>
                            ))}
                            <button onClick={clearFilters} className="text-xs text-gray-500 hover:text-gray-700 font-medium px-1">Clear all</button>
                        </div>
                    )}

                    {!orders?.data?.length ? (
                        <div className="text-center py-10 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm">
                            <ShoppingBag className="w-10 h-10 mx-auto text-gray-300" />
                            <h3 className="mt-3 text-base font-semibold text-gray-900 dark:text-gray-100">{hasActiveFilters ? 'No orders match your filters' : 'No orders yet'}</h3>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{hasActiveFilters ? 'Try adjusting your search or filter criteria.' : 'Your order history will appear here once you make a purchase.'}</p>
                            {hasActiveFilters ? (
                                <button onClick={clearFilters} className="mt-4 inline-block px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-medium">Clear Filters</button>
                            ) : (
                                <Link href={route('storefront.index', { store_slug: storeSlug })} className="mt-4 inline-block px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-medium">Start Shopping</Link>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-3">
                            <p className="text-xs text-gray-500 dark:text-gray-400">{orders.total} order{orders.total !== 1 ? 's' : ''} found</p>
                            {orders.data.map((order) => (
                                <div key={order.id} className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                                    <div className="p-4">
                                        <div className="flex items-start justify-between gap-2 mb-2">
                                            <h3 className="text-sm font-bold text-gray-900 dark:text-gray-100 font-mono">#{order.invoice_number}</h3>
                                            <span className={`px-2 py-0.5 rounded-full text-[11px] font-semibold ${statusColors[order.order_status] || 'bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-800'}`}>
                                                {order.order_status}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2 flex-wrap text-xs text-gray-500 dark:text-gray-400 mb-2">
                                            <span>{new Date(order.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</span>
                                            <span>&middot;</span>
                                            <span>{codPaymentMethodLabel(order)}</span>
                                            <span>&middot;</span>
                                            <span>{itemCount(order)} item{itemCount(order) !== 1 ? 's' : ''}</span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className={`px-2 py-0.5 rounded-full text-[11px] font-semibold ${isCodUnpaid(order) ? 'bg-amber-50 text-amber-700 border border-amber-200' : (paymentColors[order.payment_status] || 'bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-800')}`}>
                                                {codPaymentStatusLabel(order)}
                                            </span>
                                            <p className="text-lg font-bold text-gray-900 dark:text-gray-100">{formatCurrency(order.total_amount, cc)}</p>
                                        </div>
                                    </div>
                                    <div className="px-4 py-2 bg-gray-50 dark:bg-gray-950 border-t border-gray-100 dark:border-gray-800 flex items-center gap-2">
                                        <Link
                                            href={route('storefront.customer.orders.show', { store_slug: storeSlug, order: order.id })}
                                            className="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                            aria-label="View order details"
                                        >
                                            <Eye className="w-4 h-4" />
                                            View
                                        </Link>
                                        <InvoiceButton order={order} compact />
                                    </div>
                                </div>
                            ))}

                            {orders.links?.length > 3 && (
                                <div className="flex justify-center gap-1 pt-2 flex-wrap">
                                    {orders.links.map((link, i) => (
                                        <Link key={i} href={link.url || '#'} preserveState className={`px-3 py-1.5 text-xs rounded-xl transition-colors ${link.active ? 'bg-blue-600 text-white font-medium' : link.url ? 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:bg-gray-800' : 'text-gray-400 cursor-not-allowed'}`}>
                                            {link.label.replace('&laquo;', '\u00ab').replace('&raquo;', '\u00bb').replace('Previous', '\u2190').replace('Next', '\u2192')}
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {filtersOpen && (
                    <div className="fixed inset-0 z-50 flex items-end" onClick={() => setFiltersOpen(false)}>
                        <div className="absolute inset-0 bg-black/40" />
                        <div
                            className="relative w-full bg-white dark:bg-gray-900 rounded-t-2xl shadow-xl max-h-[85vh] flex flex-col"
                            onClick={e => e.stopPropagation()}
                            style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
                        >
                            <div className="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-800">
                                <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">Filters</h3>
                                <button onClick={() => setFiltersOpen(false)} className="text-gray-400 hover:text-gray-600" aria-label="Close filters">
                                    <X className="w-5 h-5" />
                                </button>
                            </div>
                            <div className="overflow-y-auto px-5 py-4 space-y-4 flex-1">
                                <div>
                                    <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Order Status</label>
                                    <select value={orderStatus} onChange={e => setOrderStatus(e.target.value)} className="w-full border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-900">
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
                                    <select value={paymentStatus} onChange={e => setPaymentStatus(e.target.value)} className="w-full border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-900">
                                        <option value="">All Payments</option>
                                        <option value="due_on_delivery">Due on Delivery</option>
                                        <option value="pending">Pending</option>
                                        <option value="paid">Paid</option>
                                        <option value="failed">Failed</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Date Range</label>
                                    <select value={dateRange} onChange={e => { const v = e.target.value; setDateRange(v); setShowCustomDate(v === 'custom'); }} className="w-full border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-900">
                                        <option value="">All Time</option>
                                        <option value="today">Today</option>
                                        <option value="7days">Last 7 Days</option>
                                        <option value="30days">Last 30 Days</option>
                                        <option value="this_month">This Month</option>
                                        <option value="custom">Custom Range</option>
                                    </select>
                                </div>
                                {showCustomDate && (
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">From</label>
                                            <input type="date" value={dateFrom} onChange={e => setDateFrom(e.target.value)} className="w-full border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-900" />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">To</label>
                                            <input type="date" value={dateTo} onChange={e => setDateTo(e.target.value)} className="w-full border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-900" />
                                        </div>
                                    </div>
                                )}
                                <div>
                                    <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Sort By</label>
                                    <select value={sort} onChange={e => setSort(e.target.value)} className="w-full border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm bg-white dark:bg-gray-900">
                                        <option value="newest">Newest First</option>
                                        <option value="oldest">Oldest First</option>
                                        <option value="amount_high">Highest Amount</option>
                                        <option value="amount_low">Lowest Amount</option>
                                    </select>
                                </div>
                            </div>
                            <div className="px-5 py-4 border-t border-gray-200 dark:border-gray-800 flex gap-2">
                                <button onClick={() => { clearFilters(); setFiltersOpen(false); }} className="flex-1 px-4 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-700 rounded-xl">Reset</button>
                                <button onClick={() => { applyFilters(); setFiltersOpen(false); }} className="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-medium">Apply Filters</button>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* ===== DESKTOP/TABLET LAYOUT (hidden on mobile) ===== */}
            <div className="hidden md:block max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div className="flex flex-col gap-3 mb-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">My Orders</h1>
                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Track and manage your orders.</p>
                        </div>
                        <Link href={route('storefront.customer.account', { store_slug: storeSlug })} className="text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors flex-shrink-0">
                            &larr; Back to Account
                        </Link>
                    </div>
                    <div className="flex gap-2">
                        <input
                            type="text"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && applyFilters()}
                            placeholder="Search orders..."
                            className="flex-1 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <button onClick={() => applyFilters()} className="px-4 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 text-sm font-medium transition-colors flex-shrink-0">
                            Search
                        </button>
                        <button onClick={() => setFiltersOpen(o => !o)} className="inline-flex items-center gap-1.5 px-4 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors flex-shrink-0">
                            Filters
                            <svg className={`w-3.5 h-3.5 transition-transform ${filtersOpen ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
                            {activeFilterCount > 0 && (
                                <span className="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-blue-600 text-white text-[11px] font-semibold">
                                    {activeFilterCount}
                                </span>
                            )}
                        </button>
                    </div>
                    {filterChips.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {filterChips.map(chip => (
                                <button key={chip.key} onClick={() => clearOneFilter(chip.key)} className="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 dark:bg-blue-900/30 border border-blue-100 dark:border-blue-900/40 text-blue-700 dark:text-blue-300 rounded-lg text-xs font-medium hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors">
                                    {chip.label}
                                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            ))}
                            <button onClick={clearFilters} className="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 font-medium px-1">Clear all</button>
                        </div>
                    )}
                </div>

                {filtersOpen && (
                <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm p-5 mb-6">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
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
                                <option value="due_on_delivery">Due on Delivery</option>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="failed">Failed</option>
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
                    <div className="flex justify-end gap-2 mt-4">
                        <button onClick={clearFilters} className="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 transition-colors">Clear</button>
                        <button onClick={() => { applyFilters(); setFiltersOpen(false); }} className="px-5 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 text-sm font-medium transition-colors">Apply Filters</button>
                    </div>
                </div>
                )}

                {!orders?.data?.length ? (
                    <div className="text-center py-10 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm">
                        <svg className="w-10 h-10 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                        <h3 className="mt-3 text-lg font-semibold text-gray-900 dark:text-gray-100">{hasActiveFilters ? 'No orders match your filters' : 'No orders yet'}</h3>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{hasActiveFilters ? 'Try adjusting your search or filter criteria.' : 'Your order history will appear here once you make a purchase.'}</p>
                        {hasActiveFilters ? (
                            <button onClick={clearFilters} className="mt-4 inline-block px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 text-sm font-medium transition-colors">
                                Clear Filters
                            </button>
                        ) : (
                            <Link href={route('storefront.index', { store_slug: storeSlug })} className="mt-4 inline-block px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 text-sm font-medium transition-colors">
                                Start Shopping
                            </Link>
                        )}
                    </div>
                ) : (
                    <div className="space-y-4">
                        <p className="text-sm text-gray-500 dark:text-gray-400">{orders.total} order{orders.total !== 1 ? 's' : ''} found</p>
                        {orders.data.map((order) => (
                            <div key={order.id} className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                                <div className="p-4">
                                    <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2.5 flex-wrap">
                                                <h3 className="text-base font-bold text-gray-900 dark:text-gray-100 font-mono">Order #{order.invoice_number}</h3>
                                                <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${statusColors[order.order_status] || 'bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-800'}`}>
                                                    {order.order_status}
                                                </span>
                                                <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${isCodUnpaid(order) ? 'bg-amber-50 text-amber-700 border border-amber-200' : (paymentColors[order.payment_status] || 'bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-800')}`}>
                                                    {codPaymentStatusLabel(order)}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-3 mt-2 text-sm text-gray-500 dark:text-gray-400">
                                                <span>{new Date(order.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</span>
                                                <span>&middot;</span>
                                                <span>{codPaymentMethodLabel(order)}</span>
                                                <span>&middot;</span>
                                                <span>{itemCount(order)} item{itemCount(order) !== 1 ? 's' : ''}</span>
                                            </div>
                                        </div>
                                        <div className="text-left sm:text-right shrink-0">
                                            <p className="text-xl font-bold text-gray-900 dark:text-gray-100">{formatCurrency(order.total_amount, cc)}</p>
                                        </div>
                                    </div>
                                </div>
                                <div className="px-4 py-2 bg-gray-50 dark:bg-gray-950 border-t border-gray-100 dark:border-gray-800 flex items-center gap-2">
                                    <Link href={route('storefront.customer.orders.show', { store_slug: storeSlug, order: order.id })} className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        View Order
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={() => downloadInvoice(order)}
                                        disabled={downloadingInvoiceId === order.id}
                                        className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:bg-gray-800 rounded-lg transition-colors disabled:opacity-50"
                                        aria-label="Download invoice"
                                    >
                                        <Download className="w-4 h-4" />
                                        {downloadingInvoiceId === order.id ? 'Preparing…' : 'Download Invoice'}
                                    </button>
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
