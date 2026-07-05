import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import PerPageSelect from '@/Components/PerPageSelect';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';

export default function AdminOrdersIndex({ orders, filters = {}, showPagination = true, warning = null }) {
    const { auth, platform_setting, website_info } = usePage().props;
    const cc = getCurrencyConfig(platform_setting, website_info);
    const permissions = auth?.user?.permissions || [];
    const can = (perm) => permissions.includes(perm);

    const [filterForm, setFilterForm] = useState({
        order_status: filters.order_status || '',
        payment_status: filters.payment_status || '',
        search: filters.search || '',
    });

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

    function handleDelete(id) {
        if (confirm('Are you sure you want to delete this cancelled order?')) {
            router.delete(adminUrl(`/admin/orders/${id}`));
        }
    }

    return (
        <AdminLayout>
            <Head title="Orders" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <h1 className="text-2xl font-bold text-gray-900 mb-6">Orders</h1>

                {/* Filters */}
                <form onSubmit={handleFilter} className="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Order Status</label>
                            <select
                                value={filterForm.order_status}
                                onChange={(e) => setFilterForm((p) => ({ ...p, order_status: e.target.value }))}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">All</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                            <select
                                value={filterForm.payment_status}
                                onChange={(e) => setFilterForm((p) => ({ ...p, payment_status: e.target.value }))}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">All</option>
                                <option value="unpaid">Unpaid</option>
                                <option value="paid">Paid</option>
                                <option value="pending">Pending</option>
                                <option value="verified">Verified</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input
                                type="text"
                                value={filterForm.search}
                                onChange={(e) => setFilterForm((p) => ({ ...p, search: e.target.value }))}
                                placeholder="Name, phone, order ID..."
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    </div>
                    <div className="flex justify-end mt-4 gap-2">
                        <button
                            type="button"
                            onClick={() => { setFilterForm({ order_status: '', payment_status: '', search: '' }); router.get(adminUrl('/admin/orders')); }}
                            className="px-4 py-2 text-gray-600 hover:text-gray-800 text-sm"
                        >
                            Clear
                        </button>
                        <button type="submit" className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                            Apply Filters
                        </button>
                    </div>
                </form>

                {/* Active Filters */}
                {(filterForm.order_status || filterForm.payment_status || filterForm.search) && (
                    <div className="flex items-center gap-2 mb-4">
                        <span className="text-sm text-gray-500">Active filters:</span>
                        {filterForm.order_status && (
                            <span className="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">{filterForm.order_status}</span>
                        )}
                        {filterForm.payment_status && (
                            <span className="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">{filterForm.payment_status}</span>
                        )}
                        {filterForm.search && (
                            <span className="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-medium">"{filterForm.search}"</span>
                        )}
                    </div>
                )}

                {/* Per Page Selector */}
                <div className="flex justify-between items-center mb-4">
                    <PerPageSelect />
                    {warning && (
                        <p className="text-sm text-amber-600">{warning}</p>
                    )}
                </div>

                {/* Orders Table */}
                <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                                    <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                    <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {!orders?.data?.length ? (
                                    <tr>
                                        <td colSpan="7" className="px-4 py-12 text-center text-gray-500">No orders found.</td>
                                    </tr>
                                ) : (
                                    orders.data.map((order) => (
                                        <tr key={order.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-4">
                                                <Link href={adminUrl(`/admin/orders/${order.id}`)} className="text-sm font-medium text-blue-600 hover:underline">
                                                    #{order.id}
                                                </Link>
                                                <p className="text-xs text-gray-500">{new Date(order.created_at).toLocaleDateString()}</p>
                                            </td>
                                            <td className="px-4 py-4">
                                                <p className="text-sm font-medium text-gray-900 truncate max-w-[150px]">
                                                    {order.user?.name || `${order.first_name} ${order.last_name}`}
                                                </p>
                                                <p className="text-xs text-gray-500">{order.phone}</p>
                                            </td>
                                            <td className="px-4 py-4">
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${orderStatusColors[order.order_status] || 'bg-gray-100 text-gray-800'}`}>
                                                    {order.order_status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4">
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${paymentStatusColors[order.payment_status] || 'bg-gray-100 text-gray-800'}`}>
                                                    {order.payment_status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-600">
                                                {order.items?.reduce((s, i) => s + i.quantity, 0) || 0} items
                                            </td>
                                            <td className="px-4 py-4 text-sm font-medium text-gray-900 text-right">
                                                {formatCurrency(order.total_amount, cc)}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm">
                                                <div className="flex justify-end gap-2">
                                                    {can('orders.view') && (
                                                        <Link href={adminUrl(`/admin/orders/${order.id}`)} className="text-blue-600 hover:text-blue-800">View</Link>
                                                    )}
                                                    {can('orders.update-status') && order.order_status === 'cancelled' && (
                                                        <button onClick={() => handleDelete(order.id)} className="text-red-600 hover:text-red-800">Delete</button>
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
                        <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                            <p className="text-sm text-gray-500">
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
        </AdminLayout>
    );
}
