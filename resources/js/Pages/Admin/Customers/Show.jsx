import { useState } from 'react';
import { Link, Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';

export default function CustomersShow({ customer, addresses, orderStats, lastOrder, orderHistory }) {
    const { can } = usePermission();
    const [statusModal, setStatusModal] = useState(null);
    const [reason, setReason] = useState('');
    const [reasonError, setReasonError] = useState('');
    const [selectedSuggestion, setSelectedSuggestion] = useState('');

    const reasonSuggestions = {
        suspend: [
            'Violation of store policies',
            'Suspicious or unusual account activity',
            'Repeated order/payment issues',
            'Customer requested account suspension',
            'Other',
        ],
        ban: [
            'Violation of store policies',
            'Fraudulent activity',
            'Repeated order/payment issues',
            'Abuse towards staff',
            'Other',
        ],
    };

    const statusBadge = (status) => {
        const colors = {
            active: 'bg-green-100 text-green-800',
            suspended: 'bg-yellow-100 text-yellow-800',
            banned: 'bg-red-100 text-red-800',
            inactive: 'bg-gray-100 text-gray-800',
            pending: 'bg-yellow-100 text-yellow-800',
            confirmed: 'bg-blue-100 text-blue-800',
            processing: 'bg-indigo-100 text-indigo-800',
            shipped: 'bg-purple-100 text-purple-800',
            delivered: 'bg-green-100 text-green-800',
            cancelled: 'bg-red-100 text-red-800',
            paid: 'bg-green-100 text-green-800',
            verified: 'bg-blue-100 text-blue-800',
            unpaid: 'bg-yellow-100 text-yellow-800',
            failed: 'bg-red-100 text-red-800',
            rejected: 'bg-red-100 text-red-800',
            refunded: 'bg-gray-100 text-gray-800',
        };
        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colors[status] || 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200'}`}>
                {status}
            </span>
        );
    };

    function confirmDelete() {
        if (window.confirm(`Remove "${customer.name}" from this store? Their order history is preserved.`)) {
            router.delete(adminUrl(`/admin/customers/${customer.id}`));
        }
    }

    function openStatusModal(action) {
        setStatusModal(action);
        setReason('');
        setReasonError('');
        setSelectedSuggestion('');
    }

    function closeStatusModal() {
        setStatusModal(null);
        setReason('');
        setReasonError('');
        setSelectedSuggestion('');
    }

    function pickSuggestion(suggestion) {
        setSelectedSuggestion(suggestion);
        setReason(suggestion === 'Other' ? '' : suggestion);
        if (reasonError) setReasonError('');
    }

    function confirmStatusModal() {
        if (!reason.trim()) {
            setReasonError('A reason is required.');
            return;
        }
        router.post(adminUrl(`/admin/customers/${customer.id}/${statusModal}`), { reason: reason.trim() }, {
            onSuccess: closeStatusModal,
        });
    }

    function handleActivate() {
        router.post(adminUrl(`/admin/customers/${customer.id}/activate`));
    }

    const formatDate = (value) => value ? new Date(value).toLocaleDateString() : '—';
    const history = orderHistory?.data || [];

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Customer Details</h2>}>
            <Head title={`Customer: ${customer.name}`} />

            <div className="py-6">
                <div className="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div className="bg-white dark:bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                                <div className="flex items-center gap-4">
                                    <div className="flex-shrink-0 h-16 w-16">
                                        {customer.profile_image_url ? (
                                            <img className="h-16 w-16 rounded-full object-cover" src={customer.profile_image_url} alt="" />
                                        ) : (
                                            <div className="h-16 w-16 rounded-full bg-blue-100 flex items-center justify-center">
                                                <span className="text-2xl font-medium text-blue-600">{customer.name?.charAt(0).toUpperCase()}</span>
                                            </div>
                                        )}
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">{customer.name}</h3>
                                        <p className="text-sm text-gray-500 dark:text-gray-400">{customer.email}</p>
                                        <div className="flex items-center gap-2 mt-1">
                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                Customer
                                            </span>
                                            {statusBadge(customer.status)}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {can('users.update') && (
                                        <Link href={adminUrl(`/admin/customers/${customer.id}/edit`)} className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                                            <i className="bi bi-pencil mr-1"></i> Edit
                                        </Link>
                                    )}
                                    {can('users.suspend') && customer.status === 'active' && (
                                        <button onClick={() => openStatusModal('suspend')} className="px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700">
                                            <i className="bi bi-pause-circle mr-1"></i> Suspend
                                        </button>
                                    )}
                                    {can('users.ban') && customer.status === 'active' && (
                                        <button onClick={() => openStatusModal('ban')} className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">
                                            <i className="bi bi-slash-circle mr-1"></i> Ban
                                        </button>
                                    )}
                                    {can('users.activate') && customer.status !== 'active' && (
                                        <button onClick={handleActivate} className="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                                            <i className="bi bi-check-circle mr-1"></i> Reactivate
                                        </button>
                                    )}
                                    {can('users.delete') && (
                                        <button onClick={confirmDelete} className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">
                                            <i className="bi bi-trash mr-1"></i> Remove
                                        </button>
                                    )}
                                </div>
                            </div>

                            <div className="mt-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <p className="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">Phone</p>
                                    <p className="text-gray-900 dark:text-gray-100 font-medium mt-0.5">{customer.phone || '—'}</p>
                                </div>
                                <div>
                                    <p className="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">Email</p>
                                    <p className="text-gray-900 dark:text-gray-100 font-medium mt-0.5 truncate">{customer.email}</p>
                                </div>
                                <div>
                                    <p className="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">Status</p>
                                    <div className="mt-1">{statusBadge(customer.status)}</div>
                                </div>
                                <div>
                                    <p className="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">Joined</p>
                                    <p className="text-gray-900 dark:text-gray-100 font-medium mt-0.5">{formatDate(customer.joined_at)}</p>
                                </div>
                            </div>

                            {customer.status !== 'active' && customer.status_reason && (
                                <div className="mt-4 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 p-4 text-sm">
                                    <p className="text-xs uppercase tracking-wide text-amber-700 dark:text-amber-400 font-semibold">Status reason</p>
                                    <p className="text-gray-900 dark:text-gray-100 mt-1">{customer.status_reason}</p>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Order Summary</h4>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                                <div className="rounded-lg bg-gray-50 dark:bg-gray-950 p-4">
                                    <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{orderStats?.count || 0}</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">Total orders</p>
                                </div>
                                <div className="rounded-lg bg-gray-50 dark:bg-gray-950 p-4">
                                    <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{orderStats?.total_spent || 0}</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">Total spent</p>
                                </div>
                                <div className="rounded-lg bg-gray-50 dark:bg-gray-950 p-4">
                                    {lastOrder ? (
                                        <>
                                            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">#{lastOrder.id}</p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">Last order · {formatDate(lastOrder.created_at)}</p>
                                        </>
                                    ) : (
                                        <>
                                            <p className="text-2xl font-bold text-gray-400">—</p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">No orders yet</p>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Order History</h4>
                            {history.length > 0 ? (
                                <>
                                    <div className="hidden md:block overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                                            <thead className="bg-gray-50 dark:bg-gray-950">
                                                <tr>
                                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Order</th>
                                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Items</th>
                                                    <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Payment</th>
                                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                                    <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">View</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                                                {history.map((order) => (
                                                    <tr key={order.id} className="hover:bg-gray-50 dark:bg-gray-950">
                                                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">#{order.id}</td>
                                                        <td className="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{formatDate(order.created_at)}</td>
                                                        <td className="px-4 py-3 text-gray-500 dark:text-gray-400">
                                                            <span className="font-medium text-gray-900 dark:text-gray-100">{order.items_count}</span>
                                                            <span className="block text-xs truncate max-w-[220px]">
                                                                {(order.items_summary || []).map((i) => `${i.product_name} × ${i.quantity}`).join(', ')}
                                                            </span>
                                                        </td>
                                                        <td className="px-4 py-3 text-right text-gray-900 dark:text-gray-100 whitespace-nowrap">{order.total_amount}</td>
                                                        <td className="px-4 py-3 whitespace-nowrap">{statusBadge(order.payment_status)}</td>
                                                        <td className="px-4 py-3 whitespace-nowrap">{statusBadge(order.order_status)}</td>
                                                        <td className="px-4 py-3 text-right">
                                                            <Link href={adminUrl(`/admin/orders/${order.id}`)} className="text-blue-600 hover:text-blue-900" title="View order">
                                                                <i className="bi bi-eye"></i>
                                                            </Link>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>

                                    <div className="md:hidden divide-y divide-gray-200 dark:divide-gray-800">
                                        {history.map((order) => (
                                            <div key={order.id} className="py-3">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100">Order #{order.id}</p>
                                                        <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                            {(order.items_summary || []).map((i) => `${i.product_name} × ${i.quantity}`).join(', ') || `${order.items_count} items`}
                                                        </p>
                                                        <div className="flex items-center gap-2 mt-1.5">
                                                            {statusBadge(order.order_status)}
                                                            {statusBadge(order.payment_status)}
                                                        </div>
                                                    </div>
                                                    <div className="text-right flex-shrink-0">
                                                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{order.total_amount}</p>
                                                        <Link href={adminUrl(`/admin/orders/${order.id}`)} className="text-xs text-blue-600 hover:text-blue-800">
                                                            View <i className="bi bi-arrow-right"></i>
                                                        </Link>
                                                    </div>
                                                </div>
                                                <p className="text-[11px] text-gray-400 dark:text-gray-500 mt-1">{formatDate(order.created_at)}</p>
                                            </div>
                                        ))}
                                    </div>

                                    {orderHistory?.links && orderHistory.links.length > 3 && (
                                        <div className="mt-4">
                                            {orderHistory.links.map((link, i) => (
                                                <button
                                                    key={i}
                                                    onClick={() => router.get(link.url, {}, { preserveState: true })}
                                                    disabled={!link.url}
                                                    className={`px-3 py-1 mx-0.5 text-sm rounded ${link.active ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border hover:bg-gray-50'} ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            ))}
                                            {orderHistory.total > 0 && (
                                                <span className="ml-2 text-xs text-gray-400 dark:text-gray-500">
                                                    {orderHistory.from}–{orderHistory.to} of {orderHistory.total}
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </>
                            ) : (
                                <p className="text-sm text-gray-500 dark:text-gray-400">No orders yet.</p>
                            )}
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Addresses</h4>
                            {(addresses || []).length > 0 ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    {addresses.map((address) => (
                                        <div key={address.id} className="rounded-lg border border-gray-200 dark:border-gray-800 p-4 text-sm">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium text-gray-900 dark:text-gray-100">{address.label || 'Address'}</span>
                                                {address.is_default && (
                                                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Default</span>
                                                )}
                                            </div>
                                            <p className="text-gray-500 dark:text-gray-400 mt-1">{address.name} · {address.phone}</p>
                                            <p className="text-gray-500 dark:text-gray-400">{address.address_line}</p>
                                            <p className="text-gray-500 dark:text-gray-400">{[address.township, address.city, address.postal_code].filter(Boolean).join(', ')}</p>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-gray-500 dark:text-gray-400">No saved addresses.</p>
                            )}
                        </div>
                    </div>

                    <div>
                        <Link href={adminUrl('/admin/customers')} className="text-sm text-blue-600 hover:text-blue-800">
                            <i className="bi bi-arrow-left mr-1"></i> Back to customers
                        </Link>
                    </div>
                </div>
            </div>

            {statusModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" onClick={closeStatusModal} />
                    <div className="relative bg-white dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
                        <div className="px-6 pt-6 pb-4 border-b border-gray-100 dark:border-gray-800">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 capitalize">
                                {statusModal} customer
                            </h3>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {customer.name} ({customer.email}) will not be able to log in to this store.
                            </p>
                        </div>
                        <div className="p-6 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Reason <span className="text-red-500">*</span>
                                </label>
                                <div className="flex flex-wrap gap-2 mb-3">
                                    {(reasonSuggestions[statusModal] || []).map((suggestion) => (
                                        <button
                                            key={suggestion}
                                            type="button"
                                            onClick={() => pickSuggestion(suggestion)}
                                            className={`px-3 py-1.5 text-xs font-medium rounded-full border transition-colors ${
                                                selectedSuggestion === suggestion
                                                    ? 'bg-blue-600 text-white border-blue-600'
                                                    : 'bg-gray-50 dark:bg-gray-950 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-700 hover:border-blue-400 hover:text-blue-600'
                                            }`}
                                        >
                                            {suggestion}
                                        </button>
                                    ))}
                                </div>
                                <textarea
                                    value={reason}
                                    onChange={(e) => { setReason(e.target.value); setSelectedSuggestion(''); if (reasonError) setReasonError(''); }}
                                    rows={4}
                                    placeholder={selectedSuggestion === 'Other' ? 'Describe the reason...' : 'Explain why this customer is being suspended or banned...'}
                                    className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                />
                                {reasonError && <p className="mt-1 text-sm text-red-600">{reasonError}</p>}
                            </div>
                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={closeStatusModal}
                                    className="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={confirmStatusModal}
                                    className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 capitalize"
                                >
                                    Confirm {statusModal}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
