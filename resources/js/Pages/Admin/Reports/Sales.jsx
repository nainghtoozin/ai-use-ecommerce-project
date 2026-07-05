import { memo, useState, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import {
    DollarSign, ShoppingCart, Clock, CheckCircle, XCircle, Receipt,
    Search, Filter, FileText, Eye,
} from 'lucide-react';
import OrderDetailModal from '@/Components/OrderDetailModal';
import { formatCurrency } from '@/Utils/currency';

const PER_PAGE_OPTIONS = [25, 50, 100, 500, 1000];

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800',
    verified: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-gray-100 text-gray-800',
    confirmed: 'bg-blue-100 text-blue-800',
    shipped: 'bg-indigo-100 text-indigo-800',
    delivered: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
};

const statusLabels = {
    pending: 'Pending',
    verified: 'Verified',
    rejected: 'Rejected',
    confirmed: 'Confirmed',
    shipped: 'Shipped',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
};

const colorMap = {
    blue: { bg: 'bg-blue-50', text: 'text-blue-600' },
    emerald: { bg: 'bg-emerald-50', text: 'text-emerald-600' },
    amber: { bg: 'bg-amber-50', text: 'text-amber-600' },
    violet: { bg: 'bg-violet-50', text: 'text-violet-600' },
    red: { bg: 'bg-red-50', text: 'text-red-600' },
    slate: { bg: 'bg-slate-50', text: 'text-slate-600' },
    indigo: { bg: 'bg-indigo-50', text: 'text-indigo-600' },
};

function Card({ icon: Icon, label, value, color, sublabel }) {
    const c = colorMap[color] || colorMap.slate;
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-4 lg:p-5">
            <div className="flex flex-col gap-1.5 lg:gap-2">
                <div className={`p-2 rounded-lg w-fit ${c.bg}`}>
                    <Icon className={`w-4 h-4 lg:w-5 lg:h-5 ${c.text}`} />
                </div>
                <div className="min-w-0">
                    <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">{label}</p>
                    <p className="text-sm sm:text-base lg:text-lg font-bold text-gray-900 break-words mt-0.5 tabular-nums">{value}</p>
                    {sublabel && <p className="text-xs text-gray-400 mt-0.5 tabular-nums">{sublabel}</p>}
                </div>
            </div>
        </div>
    );
}

const TableRow = memo(function TableRow({ order, onView }) {
    return (
        <tr className="hover:bg-gray-50 transition-colors">
            <td className="px-3 sm:px-5 py-3 sm:py-3.5">
                <span className="text-xs sm:text-sm font-medium text-gray-900">
                    #{order.id}
                </span>
            </td>
            <td className="px-3 sm:px-5 py-3 sm:py-3.5">
                <p className="text-xs sm:text-sm font-medium text-gray-900 truncate max-w-[100px] sm:max-w-[180px]">
                    {order.first_name
                        ? `${order.first_name} ${order.last_name}`
                        : order.user?.name || '-'}
                </p>
                {order.phone && <p className="text-xs text-gray-400 hidden sm:block">{order.phone}</p>}
            </td>
            <td className="px-3 sm:px-5 py-3 sm:py-3.5 text-xs sm:text-sm text-gray-600 text-right tabular-nums">
                {order.items_count || 0}
            </td>
            <td className="px-3 sm:px-5 py-3 sm:py-3.5 text-xs sm:text-sm text-gray-900 text-right tabular-nums">
                {formatCurrency(order.gross_total)}
            </td>
            <td className="px-3 sm:px-5 py-3 sm:py-3.5 text-xs sm:text-sm text-right tabular-nums">
                {Number(order.discount_amount) > 0 ? (
                    <span className="text-red-500">{formatCurrency(order.discount_amount)}</span>
                ) : (
                    <span className="text-gray-300">&mdash;</span>
                )}
            </td>
            <td className="px-3 sm:px-5 py-3 sm:py-3.5 text-xs sm:text-sm font-semibold text-gray-900 text-right tabular-nums">
                {formatCurrency(order.total_amount)}
            </td>
            <td className="px-3 sm:px-5 py-3 sm:py-3.5">
                <span className={`inline-flex items-center px-1.5 sm:px-2 py-0.5 rounded-full text-[10px] sm:text-xs font-medium ${statusColors[order.order_status] || 'bg-gray-100 text-gray-800'}`}>
                    {statusLabels[order.order_status] || order.order_status}
                </span>
            </td>
            <td className="px-3 sm:px-5 py-3 sm:py-3.5 text-xs sm:text-sm text-gray-500 tabular-nums whitespace-nowrap">
                {order.created_at?.substring(0, 10) || '-'}
            </td>
            <td className="px-3 sm:px-5 py-3 sm:py-3.5">
                <button
                    onClick={() => onView(order.id)}
                    className="flex items-center gap-1 px-2 sm:px-3 py-1.5 text-xs font-medium text-blue-600 hover:text-white bg-blue-50 hover:bg-blue-600 rounded-lg transition-colors whitespace-nowrap"
                >
                    <Eye className="w-3.5 h-3.5" />
                    <span className="hidden sm:inline">View</span>
                </button>
            </td>
        </tr>
    );
});

function FilterBar({ filters, baseUrl }) {
    const today = new Date().toISOString().split('T')[0];

    const [form, setForm] = useState({
        date_from: filters.date_from || today,
        date_to: filters.date_to || today,
        order_status: filters.order_status || '',
        search_by: filters.search_by || '',
        search: filters.search || '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        const params = new URLSearchParams();
        Object.entries(form).forEach(([k, v]) => { if (v) params.set(k, v); });
        router.get(baseUrl + '?' + params.toString(), {}, { preserveState: true, preserveScroll: true });
    }

    function handleReset() {
        const defaults = { date_from: today, date_to: today, order_status: '', search_by: '', search: '' };
        setForm(defaults);
        const params = new URLSearchParams();
        params.set('date_from', today);
        params.set('date_to', today);
        router.get(baseUrl + '?' + params.toString(), {}, { preserveState: true, preserveScroll: true });
    }

    const hasActiveFilters = Object.values(form).some(v => v);

    return (
        <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-gray-200 p-4 lg:p-5 space-y-4">
            <div className="flex items-center gap-2 text-sm font-semibold text-gray-700">
                <Filter className="w-4 h-4" />
                Filters
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-4">
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Date From</label>
                    <input
                        type="date"
                        value={form.date_from}
                        onChange={e => setForm(p => ({ ...p, date_from: e.target.value }))}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Date To</label>
                    <input
                        type="date"
                        value={form.date_to}
                        onChange={e => setForm(p => ({ ...p, date_to: e.target.value }))}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Order Status</label>
                    <select
                        value={form.order_status}
                        onChange={e => setForm(p => ({ ...p, order_status: e.target.value }))}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">All Statuses</option>
                        {Object.entries(statusLabels).map(([val, label]) => (
                            <option key={val} value={val}>{label}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Search By</label>
                    <select
                        value={form.search_by}
                        onChange={e => setForm(p => ({ ...p, search_by: e.target.value }))}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">All Fields</option>
                        <option value="order_id">Order ID</option>
                        <option value="customer">Customer Name</option>
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
                    <input
                        type="text"
                        value={form.search}
                        onChange={e => setForm(p => ({ ...p, search: e.target.value }))}
                        placeholder="Search..."
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
            </div>

            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-1">
                {hasActiveFilters && (
                    <div className="flex items-center gap-1.5 flex-wrap">
                        <span className="text-xs text-gray-400">Active:</span>
                        {form.date_from && <span className="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-xs font-medium">From: {form.date_from}</span>}
                        {form.date_to && <span className="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-xs font-medium">To: {form.date_to}</span>}
                        {form.order_status && <span className="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-xs font-medium">{statusLabels[form.order_status]}</span>}
                        {form.search && <span className="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-xs font-medium">"{form.search}"</span>}
                    </div>
                )}
                <div className={`flex items-center gap-2 ${hasActiveFilters ? 'sm:ml-auto' : ''}`}>
                    <button
                        type="button"
                        onClick={handleReset}
                        className="flex-1 sm:flex-none px-3 py-2 sm:py-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors text-center"
                    >
                        Reset
                    </button>
                    <button
                        type="submit"
                        className="flex-1 sm:flex-none px-4 py-2 sm:py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors flex items-center justify-center gap-1.5"
                    >
                        <Search className="w-4 h-4 sm:w-3.5 sm:h-3.5" />
                        Search
                    </button>
                </div>
            </div>
        </form>
    );
}

function PerPageSelector({ baseUrl }) {
    const params = new URLSearchParams(window.location.search);
    const current = params.get('per_page') || '25';

    function handleChange(e) {
        const p = new URLSearchParams(window.location.search);
        p.set('per_page', e.target.value);
        window.location.href = baseUrl + '?' + p.toString();
    }

    return (
        <div className="flex items-center gap-2">
            <span className="text-sm text-gray-500">Rows:</span>
            <div className="relative">
                <select
                    value={current}
                    onChange={handleChange}
                    className="border border-gray-300 rounded-lg py-1.5 pl-3 pr-8 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer appearance-none"
                >
                    {PER_PAGE_OPTIONS.map(n => (
                        <option key={n} value={n}>{n}</option>
                    ))}
                </select>
                <div className="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                    <svg className="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>
        </div>
    );
}

function Pagination({ meta, baseUrl }) {
    if (!meta || meta.last_page <= 1) return null;

    function pageUrl(page) {
        const p = new URLSearchParams(window.location.search);
        p.set('page', String(page));
        return baseUrl + '?' + p.toString();
    }

    const pages = [];
    const last = meta.last_page;
    const current = meta.current_page;
    const range = 2;
    const start = Math.max(1, current - range);
    const end = Math.min(last, current + range);

    if (start > 1) { pages.push(1); if (start > 2) pages.push('...'); }
    for (let i = start; i <= end; i++) pages.push(i);
    if (end < last) { if (end < last - 1) pages.push('...'); pages.push(last); }

    return (
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-gray-200 px-4 sm:px-5 py-4">
            <p className="text-xs sm:text-sm text-gray-500 text-center sm:text-left">
                Showing {meta.from} to {meta.to} of {meta.total.toLocaleString()} results
            </p>
            <div className="flex items-center justify-center gap-1">
                {current > 1 && (
                    <Link preserveScroll href={pageUrl(current - 1)} className="px-2 sm:px-3 py-1.5 text-xs sm:text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        &laquo;
                    </Link>
                )}
                {pages.map((p, i) =>
                    p === '...' ? (
                        <span key={`e${i}`} className="px-1.5 sm:px-2 py-1.5 text-xs sm:text-sm text-gray-400">...</span>
                    ) : (
                        <Link
                            key={p}
                            preserveScroll
                            href={pageUrl(p)}
                            className={`px-2 sm:px-3 py-1.5 text-xs sm:text-sm rounded-lg transition-colors ${
                                p === current
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-600 hover:bg-gray-100'
                            }`}
                        >
                            {p}
                        </Link>
                    )
                )}
                {current < last && (
                    <Link preserveScroll href={pageUrl(current + 1)} className="px-2 sm:px-3 py-1.5 text-xs sm:text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        &raquo;
                    </Link>
                )}
            </div>
        </div>
    );
}

export default function SalesReport({ orders, summary, filters }) {
    const baseUrl = adminUrl('/admin/reports/sales');
    const [selectedOrderId, setSelectedOrderId] = useState(null);

    const handleCloseModal = useCallback(() => setSelectedOrderId(null), []);

    const summaryCards = [
        { icon: DollarSign, label: 'Gross Sales', value: formatCurrency(summary?.gross_sales), color: 'blue', sublabel: `Discount: ${formatCurrency(summary?.discount_total)}` },
        { icon: Receipt, label: 'Net Sales', value: formatCurrency(summary?.net_sales), color: 'emerald' },
        { icon: Clock, label: 'Pending', value: formatCurrency(summary?.pending_amount), color: 'amber' },
        { icon: CheckCircle, label: 'Confirmed', value: formatCurrency(summary?.confirmed_amount), color: 'violet' },
        { icon: XCircle, label: 'Cancelled', value: formatCurrency(summary?.cancelled_amount), color: 'red' },
        { icon: ShoppingCart, label: 'Total Orders', value: Number(summary?.total_orders || 0).toLocaleString(), color: 'slate' },
    ];

    return (
        <AdminLayout>
            <Head title="Sales Report" />

            <div className="max-w-[1600px] mx-auto px-4 sm:px-5 lg:px-6 py-6 lg:py-8 space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Sales Report</h1>
                    <p className="text-sm text-gray-500 mt-1">Real-time sales performance and order summary</p>
                </div>

                <FilterBar filters={filters || {}} baseUrl={baseUrl} />

                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6 gap-3 lg:gap-4">
                    {summaryCards.map((card, i) => (
                        <Card key={i} {...card} />
                    ))}
                </div>

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 sm:px-5 py-4 border-b border-gray-100">
                        <div className="flex items-center gap-2">
                            <FileText className="w-4 h-4 text-gray-500" />
                            <h2 className="text-sm font-semibold text-gray-700">Sales Orders</h2>
                        </div>
                        <PerPageSelector baseUrl={baseUrl} />
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="bg-gray-50 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Order ID</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Customer</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3 text-right">Items</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3 text-right">Gross Total</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3 text-right">Discount</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3 text-right">Net Total</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Status</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Date</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {!orders?.data?.length ? (
                                    <tr>
                                        <td colSpan="9" className="px-5 py-16 text-center text-gray-400">
                                            <FileText className="w-10 h-10 mx-auto mb-2 text-gray-300" />
                                            No orders found matching your filters.
                                        </td>
                                    </tr>
                                ) : (
                                    orders.data.map((order) => (
                                        <TableRow key={order.id} order={order} onView={setSelectedOrderId} />
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <Pagination meta={orders} baseUrl={baseUrl} />
                </div>
            </div>

            {selectedOrderId && (
                <OrderDetailModal
                    orderId={selectedOrderId}
                    onClose={handleCloseModal}
                />
            )}
        </AdminLayout>
    );
}
