import { useState, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import PerPageSelect from '@/Components/PerPageSelect';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import { usePermission } from '@/Hooks/usePermission';
import { Download, Search, Filter, X, CheckCircle, AlertCircle, FileSpreadsheet, FileText, Printer, ChevronDown } from 'lucide-react';

const ORDER_STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
const DELETABLE_STATUSES = ['pending', 'cancelled'];

const STATUS_TINT = {
    pending: 'bg-yellow-50 text-yellow-800 border-yellow-200',
    confirmed: 'bg-blue-50 text-blue-800 border-blue-200',
    processing: 'bg-purple-50 text-purple-800 border-purple-200',
    shipped: 'bg-indigo-50 text-indigo-800 border-indigo-200',
    delivered: 'bg-green-50 text-green-800 border-green-200',
    cancelled: 'bg-red-50 text-red-800 border-red-200',
};

export default function AdminOrdersIndex({ orders, filters = {}, showPagination = true, warning = null }) {
    const { auth, platform_setting, website_info, flash } = usePage().props;
    const cc = getCurrencyConfig(platform_setting, website_info);
    const { can } = usePermission();

    const [filterForm, setFilterForm] = useState({
        order_status: filters.order_status || '',
        payment_status: filters.payment_status || '',
        search: filters.search || '',
    });
    const [exportMenuOpen, setExportMenuOpen] = useState(false);
    const [updatingId, setUpdatingId] = useState(null);
    const [deletingId, setDeletingId] = useState(null);
    const [toast, setToast] = useState(null);

    useEffect(() => {
        if (flash?.success || flash?.error) {
            setToast({ type: flash.error ? 'error' : 'success', message: flash.error || flash.success });
            const timer = setTimeout(() => setToast(null), 4000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    const orderStatusColors = {
        pending: 'bg-yellow-100 text-yellow-800',
        confirmed: 'bg-blue-100 text-blue-800',
        processing: 'bg-purple-100 text-purple-800',
        shipped: 'bg-indigo-100 text-indigo-800',
        completed: 'bg-green-100 text-green-800',
        delivered: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
        verified: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-gray-100 text-gray-800',
    };

    const paymentStatusColors = {
        unpaid: 'bg-red-100 text-red-800',
        paid: 'bg-blue-100 text-blue-800',
        pending: 'bg-yellow-100 text-yellow-800',
        verified: 'bg-green-100 text-green-800',
        rejected: 'bg-gray-100 text-gray-800',
    };

    function handleFilter(e) {
        e.preventDefault();
        router.get(adminUrl('/admin/orders'), filterForm, { preserveState: true, preserveScroll: true });
    }

    function handleStatusChange(order, newStatus) {
        if (!newStatus || newStatus === order.order_status || updatingId) return;
        setUpdatingId(order.id);
        router.post(adminUrl(`/admin/orders/${order.id}/update-status`), { order_status: newStatus }, {
            preserveScroll: true,
            onSuccess: () => setUpdatingId(null),
            onError: () => setUpdatingId(null),
            onFinish: () => setUpdatingId((current) => current === order.id ? null : current),
        });
    }

    function handleDelete(order) {
        if (!DELETABLE_STATUSES.includes(order.order_status)) return;
        if (confirm(`Delete order #${order.id}? This cannot be undone.`)) {
            setDeletingId(order.id);
            router.delete(adminUrl(`/admin/orders/${order.id}`), {
                preserveScroll: true,
                onSuccess: () => setDeletingId(null),
                onError: () => setDeletingId(null),
                onFinish: () => setDeletingId((current) => current === order.id ? null : current),
            });
        }
    }

    const activeFilterCount = [filterForm.order_status, filterForm.payment_status, filterForm.search].filter(Boolean).length;

    const exportQueryString = (() => {
        const params = new URLSearchParams();
        if (filterForm.order_status) params.set('order_status', filterForm.order_status);
        if (filterForm.payment_status) params.set('payment_status', filterForm.payment_status);
        if (filterForm.search) params.set('search', filterForm.search);
        return params.toString();
    })();

    return (
        <AdminLayout>
            <Head title="Orders" />

            <div className="p-4 lg:p-6 space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-lg lg:text-xl font-bold text-gray-900 dark:text-gray-100">Orders</h1>
                        <p className="text-[13px] text-gray-500 dark:text-gray-400 mt-0.5">
                            {orders?.total || 0} order{(orders?.total || 0) !== 1 ? 's' : ''}
                        </p>
                    </div>
                    {can('orders.view') && (
                        <div className="relative">
                            <button
                                type="button"
                                onClick={() => setExportMenuOpen((open) => !open)}
                                aria-haspopup="menu"
                                aria-expanded={exportMenuOpen}
                                className={`h-9 inline-flex items-center gap-1.5 px-3 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors text-[13px] font-medium whitespace-nowrap ${exportMenuOpen ? 'bg-gray-50 dark:bg-gray-800' : ''}`}
                            >
                                <Download className="w-4 h-4 text-gray-400 dark:text-gray-500" />
                                Export
                                <ChevronDown className={`w-3.5 h-3.5 text-gray-400 transition-transform ${exportMenuOpen ? 'rotate-180' : ''}`} />
                            </button>
                            {exportMenuOpen && (
                                <>
                                    <button type="button" aria-hidden tabIndex={-1} onClick={() => setExportMenuOpen(false)} className="fixed inset-0 z-10 cursor-default" />
                                    <div role="menu" className="absolute right-0 z-20 mt-1.5 w-56 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-lg p-1.5">
                                        <a href={adminUrl(`/admin/orders/export?format=xlsx${exportQueryString ? `&${exportQueryString}` : ''}`)} onClick={() => setExportMenuOpen(false)} className="w-full flex items-center gap-2.5 px-3 py-2 text-[13px] font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition-colors">
                                            <FileSpreadsheet className="w-4 h-4 text-gray-400 dark:text-gray-500 shrink-0" />
                                            <span>Export Excel <span className="text-gray-400 font-normal">(.xlsx)</span></span>
                                        </a>
                                        <a href={adminUrl(`/admin/orders/print${exportQueryString ? `?${exportQueryString}` : ''}`)} target="_blank" rel="noreferrer" onClick={() => setExportMenuOpen(false)} className="w-full flex items-center gap-2.5 px-3 py-2 text-[13px] font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition-colors">
                                            <FileText className="w-4 h-4 text-gray-400 dark:text-gray-500 shrink-0" />
                                            <span>Export PDF <span className="text-gray-400 font-normal">(print → Save as PDF)</span></span>
                                        </a>
                                        <a href={adminUrl(`/admin/orders/print?autoprint=1${exportQueryString ? `&${exportQueryString}` : ''}`)} target="_blank" rel="noreferrer" onClick={() => setExportMenuOpen(false)} className="w-full flex items-center gap-2.5 px-3 py-2 text-[13px] font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition-colors">
                                            <Printer className="w-4 h-4 text-gray-400 dark:text-gray-500 shrink-0" />
                                            <span>Print</span>
                                        </a>
                                    </div>
                                </>
                            )}
                        </div>
                    )}
                </div>

                {/* Filter toolbar */}
                <form onSubmit={handleFilter} className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-2.5">
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative flex-1 min-w-[170px]">
                            <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" />
                            <input
                                type="text"
                                value={filterForm.search}
                                onChange={(e) => setFilterForm((p) => ({ ...p, search: e.target.value }))}
                                placeholder="Name, phone, order ID..."
                                className="w-full h-9 border border-gray-300 dark:border-gray-700 rounded-lg pl-8 pr-3 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-900"
                            />
                        </div>
                        <select
                            value={filterForm.order_status}
                            onChange={(e) => setFilterForm((p) => ({ ...p, order_status: e.target.value }))}
                            aria-label="Order status filter"
                            className="h-9 w-auto min-w-[132px] border border-gray-300 dark:border-gray-700 rounded-lg pl-2.5 pr-8 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-900"
                        >
                            <option value="">All Statuses</option>
                            {ORDER_STATUSES.map((status) => (
                                <option key={status} value={status}>{status.charAt(0).toUpperCase() + status.slice(1)}</option>
                            ))}
                        </select>
                        <select
                            value={filterForm.payment_status}
                            onChange={(e) => setFilterForm((p) => ({ ...p, payment_status: e.target.value }))}
                            aria-label="Payment status filter"
                            className="h-9 w-auto min-w-[132px] border border-gray-300 dark:border-gray-700 rounded-lg pl-2.5 pr-8 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-900"
                        >
                            <option value="">All Payments</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                            <option value="verified">Verified</option>
                            <option value="rejected">Rejected</option>
                        </select>
                        <button type="submit" className="h-9 inline-flex items-center gap-1.5 px-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-[13px] font-semibold whitespace-nowrap">
                            <Filter className="w-3.5 h-3.5" />
                            Apply
                            {activeFilterCount > 0 && (
                                <span className="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-white/25 text-white text-[11px] font-semibold">
                                    {activeFilterCount}
                                </span>
                            )}
                        </button>
                        {(filterForm.order_status || filterForm.payment_status || filterForm.search) && (
                            <button
                                type="button"
                                onClick={() => { setFilterForm({ order_status: '', payment_status: '', search: '' }); router.get(adminUrl('/admin/orders')); }}
                                className="h-9 inline-flex items-center gap-1.5 px-3 rounded-lg text-[13px] font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                            >
                                <X className="w-3.5 h-3.5" />
                                Clear
                            </button>
                        )}
                    </div>
                </form>

                {/* Per Page Selector */}
                <div className="flex flex-wrap justify-between items-center gap-2 px-0.5">
                    <div className="flex items-center gap-3">
                        <PerPageSelect />
                        <span className="text-[13px] text-gray-500 dark:text-gray-400">
                            {orders?.total || 0} order{(orders?.total || 0) !== 1 ? 's' : ''}
                        </span>
                    </div>
                    {warning && (
                        <p className="text-[13px] text-amber-600">{warning}</p>
                    )}
                </div>

                {/* Orders Table */}
                <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead className="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Order</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Customer</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Order Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Payment</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Items</th>
                                    <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                                    <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                                {!orders?.data?.length ? (
                                    <tr>
                                        <td colSpan="7" className="px-4 py-12 text-center text-gray-500 dark:text-gray-400">No orders found.</td>
                                    </tr>
                                ) : (
                                    orders.data.map((order) => (
                                        <tr key={order.id} className="hover:bg-gray-50 dark:bg-gray-950">
                                            <td className="px-4 py-4">
                                                <Link href={adminUrl(`/admin/orders/${order.id}`)} className="text-sm font-medium text-blue-600 hover:underline">
                                                    #{order.id}
                                                </Link>
                                                <p className="text-xs text-gray-500 dark:text-gray-400">{new Date(order.created_at).toLocaleDateString()}</p>
                                            </td>
                                            <td className="px-4 py-4">
                                                <p className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate max-w-[150px]">
                                                    {order.user?.name || `${order.first_name} ${order.last_name}`}
                                                </p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400">{order.phone}</p>
                                            </td>
                                            <td className="px-4 py-4">
                                                {can('orders.update-status') ? (
                                                    <select
                                                        value={order.order_status}
                                                        onChange={(e) => handleStatusChange(order, e.target.value)}
                                                        disabled={updatingId === order.id}
                                                        aria-label={`Change status of order #${order.id}`}
                                                        className={`h-8 max-w-[132px] rounded-lg border text-xs font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-900 cursor-pointer disabled:opacity-60 disabled:cursor-wait ${STATUS_TINT[order.order_status] || 'border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300'}`}
                                                    >
                                                        {ORDER_STATUSES.map((status) => (
                                                            <option key={status} value={status}>{status.charAt(0).toUpperCase() + status.slice(1)}</option>
                                                        ))}
                                                    </select>
                                                ) : (
                                                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${orderStatusColors[order.order_status] || 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200'}`}>
                                                        {order.order_status}
                                                    </span>
                                                )}
                                                {updatingId === order.id && (
                                                    <span className="ml-1.5 text-[11px] text-blue-600">Saving...</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-4">
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${paymentStatusColors[order.payment_status] || 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200'}`}>
                                                    {order.payment_status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-600 dark:text-gray-400">
                                                {order.items?.reduce((s, i) => s + i.quantity, 0) || 0} items
                                            </td>
                                            <td className="px-4 py-4 text-sm font-medium text-gray-900 dark:text-gray-100 text-right">
                                                {formatCurrency(order.total_amount, cc)}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm">
                                                <div className="flex justify-end items-center gap-3">
                                                    {can('orders.view') && (
                                                        <Link href={adminUrl(`/admin/orders/${order.id}`)} className="text-[13px] font-medium text-blue-600 hover:text-blue-800">View</Link>
                                                    )}
                                                    {can('orders.update-status') && DELETABLE_STATUSES.includes(order.order_status) && (
                                                        <button
                                                            onClick={() => handleDelete(order)}
                                                            disabled={deletingId === order.id}
                                                            className="text-[13px] font-medium text-red-600 hover:text-red-800 disabled:opacity-60 disabled:cursor-wait"
                                                        >
                                                            {deletingId === order.id ? 'Deleting...' : 'Delete'}
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {showPagination && orders?.links && orders.links.length > 3 && (
                        <div className="px-6 py-4 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Showing {orders.from} to {orders.to} of {orders.total} results
                            </p>
                            <div className="flex gap-1">
                                {orders.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url || '#'}
                                        className={`px-3 py-1 text-sm rounded-md transition-colors ${
                                            link.active ? 'bg-blue-600 text-white' : link.url ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed'
                                        }`}
                                    >
                                        {link.label.replace('&laquo;', '«').replace('&raquo;', '»').replace('Previous', '←').replace('Next', '→')}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {toast && (
                <div className={`fixed bottom-4 right-4 z-50 flex items-center gap-2 px-4 py-2.5 rounded-xl shadow-lg text-sm font-medium ${toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'}`}>
                    {toast.type === 'error' ? <AlertCircle className="w-4 h-4 shrink-0" /> : <CheckCircle className="w-4 h-4 shrink-0" />}
                    <span>{toast.message}</span>
                    <button type="button" onClick={() => setToast(null)} aria-label="Dismiss" className="ml-1 opacity-80 hover:opacity-100">
                        <X className="w-4 h-4" />
                    </button>
                </div>
            )}

        </AdminLayout>
    );
}
