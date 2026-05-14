import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function AdminOrdersShow({ order }) {
    const { props } = usePage();
    const flash = props.flash || {};
    const [markPaidOpen, setMarkPaidOpen] = useState(false);
    const [paidAmount, setPaidAmount] = useState(order.total_payable || order.total_amount);
    const [imagePreview, setImagePreview] = useState(null);
    const [rejectModalOpen, setRejectModalOpen] = useState(false);
    const [rejectionReason, setRejectionReason] = useState('');

    const cityName = order.city?.name;
    const townshipName = order.township?.name;

    const orderStatusColors = {
        pending: 'bg-yellow-100 text-yellow-800',
        confirmed: 'bg-blue-100 text-blue-800',
        shipped: 'bg-indigo-100 text-indigo-800',
        delivered: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
        verified: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-gray-100 text-gray-800',
    };

    const paymentStatusColors = {
        unpaid: 'bg-gray-100 text-gray-800',
        paid: 'bg-orange-100 text-orange-800',
        pending: 'bg-yellow-100 text-yellow-800',
        verified: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
    };

    function handleConfirm() {
        if (confirm('Confirm this order?')) {
            router.post(`/admin/orders/${order.id}/confirm`);
        }
    }

    function handleShip() {
        router.post(`/admin/orders/${order.id}/ship`);
    }

    function handleDeliver() {
        if (confirm('Mark as delivered?')) {
            router.post(`/admin/orders/${order.id}/deliver`);
        }
    }

    function handleCancel() {
        if (confirm('Are you sure you want to cancel this order? Stock will be restored.')) {
            router.post(`/admin/orders/${order.id}/cancel`);
        }
    }

    function handleVerifyPayment() {
        if (confirm('Verify this payment?')) {
            router.post(`/admin/orders/${order.id}/verify-payment`);
        }
    }

    function handleRejectPayment() {
        router.post(`/admin/orders/${order.id}/reject-payment`, {
            rejection_reason: rejectionReason,
        }, {
            onSuccess: () => {
                setRejectModalOpen(false);
                setRejectionReason('');
            },
        });
    }

    function handleMarkAsPaid(e) {
        e.preventDefault();
        router.post(`/admin/orders/${order.id}/mark-as-paid`, { paid_amount: paidAmount });
        setMarkPaidOpen(false);
    }

    function handleDelete() {
        if (confirm('Delete this cancelled order?')) {
            router.delete(`/admin/orders/${order.id}`);
        }
    }

    return (
        <AdminLayout>
            <Head title={`Order #${order.id}`} />

            {flash.success && (
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
                    <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">{flash.success}</div>
                </div>
            )}
            {flash.error && (
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
                    <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">{flash.error}</div>
                </div>
            )}

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <Link href="/admin/orders" className="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 mb-2">
                        <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        Back to Orders
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-900">Order #{order.id}</h1>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Column */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Customer Information */}
                        <div className="bg-white rounded-lg border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Customer Information</h2>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm text-gray-500">Name</p>
                                    <p className="font-medium text-gray-900">{order.first_name} {order.last_name}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Phone</p>
                                    <p className="font-medium text-gray-900">{order.phone}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Email</p>
                                    <p className="font-medium text-gray-900">{order.email || 'N/A'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">User Account</p>
                                    <p className="font-medium text-gray-900">{order.user?.name || 'Guest'}</p>
                                </div>
                            </div>
                            <div className="mt-4">
                                <p className="text-sm text-gray-500">Delivery Address</p>
                                <p className="font-medium text-gray-900">{order.address}</p>
                                {(cityName || townshipName) && (
                                    <p className="text-sm text-gray-600">{cityName}{townshipName ? `, ${townshipName}` : ''}</p>
                                )}
                                {order.postal_code && (
                                    <p className="text-sm text-gray-600">Postal Code: {order.postal_code}</p>
                                )}
                            </div>
                            {order.notes && (
                                <div className="mt-4">
                                    <p className="text-sm text-gray-500">Notes</p>
                                    <p className="font-medium text-gray-900">{order.notes}</p>
                                </div>
                            )}
                        </div>

                        {/* Order Items */}
                        <div className="bg-white rounded-lg border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Order Items</h2>
                            <div className="overflow-x-auto">
                                <table className="min-w-full">
                                    <thead>
                                        <tr className="border-b border-gray-200">
                                            <th className="text-left py-3 text-sm font-medium text-gray-500">Product</th>
                                            <th className="text-right py-3 text-sm font-medium text-gray-500">Price</th>
                                            <th className="text-right py-3 text-sm font-medium text-gray-500">Qty</th>
                                            <th className="text-right py-3 text-sm font-medium text-gray-500">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {order.items?.length ? order.items.map((item) => (
                                            <tr key={item.id} className="border-b border-gray-100">
                                                <td className="py-3">
                                                    <p className="font-medium text-gray-900 text-sm">{item.product?.name || `Product #${item.product_id}`}</p>
                                                    {item.product && <p className="text-xs text-gray-500">SKU: {item.product.id}</p>}
                                                </td>
                                                <td className="py-3 text-sm text-right">{Number(item.price).toLocaleString()} MMK</td>
                                                <td className="py-3 text-sm text-right">{item.quantity}</td>
                                                <td className="py-3 text-sm text-right font-medium">{(item.price * item.quantity).toLocaleString()} MMK</td>
                                            </tr>
                                        )) : (
                                            <tr><td colSpan="4" className="py-4 text-center text-gray-500 text-sm">No items found</td></tr>
                                        )}
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colSpan="3" className="text-right py-2 text-sm text-gray-600">Subtotal:</td>
                                            <td className="text-right py-2 text-sm font-medium">{Number(order.items_total).toLocaleString()} MMK</td>
                                        </tr>
                                        <tr>
                                            <td colSpan="3" className="text-right py-2 text-sm text-gray-600">Delivery Fee:</td>
                                            <td className="text-right py-2 text-sm font-medium">{Number(order.delivery_fee || 0).toLocaleString()} MMK</td>
                                        </tr>
                                        <tr className="font-bold">
                                            <td colSpan="3" className="text-right py-2 text-sm">Total:</td>
                                            <td className="text-right py-2 text-sm">{Number(order.total_amount).toLocaleString()} MMK</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Order Status */}
                        <div className="bg-white rounded-lg border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Order Status</h2>
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-600">Order Status:</span>
                                    <span className={`px-2.5 py-0.5 rounded-full text-xs font-medium ${orderStatusColors[order.order_status] || 'bg-gray-100 text-gray-800'}`}>
                                        {order.order_status}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-600">Payment Status:</span>
                                    <span className={`px-2.5 py-0.5 rounded-full text-xs font-medium ${paymentStatusColors[order.payment_status] || 'bg-gray-100 text-gray-800'}`}>
                                        {order.payment_status}
                                    </span>
                                </div>
                                <div className="border-t pt-3">
                                    <span className="text-sm text-gray-500">Order Date:</span>
                                    <p className="font-medium text-gray-900">{new Date(order.created_at).toLocaleString()}</p>
                                </div>
                            </div>
                        </div>

                        {/* Payment Information */}
                        <div className="bg-white rounded-lg border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Payment Information</h2>
                            <div className="space-y-3">
                                <div>
                                    <p className="text-sm text-gray-500">Payment Method:</p>
                                    <p className="font-medium text-gray-900">{order.payment_method?.name || order.paymentMethod?.name || 'N/A'}</p>
                                    {(order.paymentMethod?.account_name || order.payment_method?.account_name) && (
                                        <p className="text-sm text-gray-500">Account: {order.paymentMethod?.account_name || order.payment_method?.account_name}</p>
                                    )}
                                    {(order.paymentMethod?.account_number || order.payment_method?.account_number) && (
                                        <p className="text-sm text-gray-500">Number: {order.paymentMethod?.account_number || order.payment_method?.account_number}</p>
                                    )}
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-600">Total Payable:</span>
                                    <span className="font-bold text-lg">{Number(order.total_payable || order.total_amount).toLocaleString()} MMK</span>
                                </div>
                                {order.paid_amount && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-gray-600">Paid Amount:</span>
                                        <span className={`font-medium ${order.is_payment_amount_correct ? 'text-green-600' : 'text-red-600'}`}>
                                            {Number(order.paid_amount).toLocaleString()} MMK
                                            {!order.is_payment_amount_correct && <span className="text-xs text-red-500 ml-1">(Short payment)</span>}
                                        </span>
                                    </div>
                                )}
                                {order.payer_name && (
                                    <div>
                                        <p className="text-sm text-gray-500">Sender Name:</p>
                                        <p className="font-medium text-gray-900">{order.payer_name}</p>
                                    </div>
                                )}
                                {order.transaction_id && (
                                    <div>
                                        <p className="text-sm text-gray-500">Transaction ID:</p>
                                        <p className="font-medium text-gray-900 break-all">{order.transaction_id}</p>
                                    </div>
                                )}
                                {order.payment_screenshot && (
                                    <div>
                                        <p className="text-sm text-gray-500 mb-2">Payment Screenshot:</p>
                                        <button onClick={() => setImagePreview(order.payment_screenshot_url)} className="block">
                                            <img src={order.payment_screenshot_url} alt="Payment Screenshot"
                                                className="max-w-full h-auto rounded-md border mb-1 cursor-pointer hover:opacity-90 transition-opacity"
                                                style={{ maxHeight: 200 }} />
                                        </button>
                                        <button onClick={() => setImagePreview(order.payment_screenshot_url)}
                                            className="text-blue-600 text-sm hover:underline">
                                            View Full Image
                                        </button>
                                    </div>
                                )}
                                {order.payment_verified_at && (
                                    <div>
                                        <p className="text-sm text-gray-500">Verified At:</p>
                                        <p className="font-medium text-green-700">{new Date(order.payment_verified_at).toLocaleString()}</p>
                                    </div>
                                )}
                                {order.rejection_reason && (
                                    <div>
                                        <p className="text-sm text-gray-500">Rejection Reason:</p>
                                        <p className="font-medium text-red-600">{order.rejection_reason}</p>
                                    </div>
                                )}
                                {order.payment_proof && (
                                    <div>
                                        <p className="text-sm text-gray-500 mb-2">Payment Proof (Legacy):</p>
                                        <a href={order.payment_proof_url} target="_blank" rel="noopener noreferrer">
                                            <img src={order.payment_proof_url} alt="Payment Proof" className="max-w-full h-auto rounded-md border mb-1" style={{ maxHeight: 200 }} />
                                        </a>
                                        <a href={order.payment_proof_url} target="_blank" rel="noopener noreferrer" className="text-blue-600 text-sm hover:underline">
                                            View Full Image
                                        </a>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Actions */}
                        <div className="bg-white rounded-lg border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Actions</h2>
                            <div className="space-y-3">
                                {order.can_mark_as_paid && (
                                    <button onClick={() => setMarkPaidOpen(true)} className="w-full bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 text-sm font-medium">
                                        Mark as Paid
                                    </button>
                                )}
                                {order.can_confirm && (
                                    <button onClick={handleConfirm} className="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">
                                        Confirm Order
                                    </button>
                                )}
                                {order.can_ship && (
                                    <button onClick={handleShip} className="w-full bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 text-sm font-medium">
                                        Mark as Shipped
                                    </button>
                                )}
                                {order.can_deliver && (
                                    <button onClick={handleDeliver} className="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm font-medium">
                                        Mark as Delivered
                                    </button>
                                )}
                                {(order.can_verify_payment || order.can_approve_payment) && (
                                    <>
                                        <button onClick={handleVerifyPayment} className="w-full bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 text-sm font-medium">
                                            Verify Payment
                                        </button>
                                        <button onClick={() => setRejectModalOpen(true)} className="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm font-medium">
                                            Reject Payment
                                        </button>
                                    </>
                                )}
                                {order.can_cancel && (
                                    <button onClick={handleCancel} className="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm font-medium">
                                        Cancel Order
                                    </button>
                                )}
                                {order.order_status === 'cancelled' && (
                                    <button onClick={handleDelete} className="w-full bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 text-sm font-medium">
                                        Delete Order
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Full-size Image Preview Modal */}
            {imagePreview && (
                <div className="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4" onClick={() => setImagePreview(null)}>
                    <div className="relative max-w-4xl max-h-full" onClick={(e) => e.stopPropagation()}>
                        <button onClick={() => setImagePreview(null)}
                            className="absolute -top-3 -right-3 bg-white rounded-full p-1 shadow-lg hover:bg-gray-100 z-10">
                            <svg className="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <img src={imagePreview} alt="Payment Screenshot Full Size"
                            className="max-w-full max-h-[90vh] rounded-lg shadow-2xl" />
                    </div>
                </div>
            )}

            {/* Reject Payment Modal */}
            {rejectModalOpen && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg shadow-lg p-6 w-96">
                        <h3 className="text-lg font-semibold mb-4">Reject Payment</h3>
                        <p className="text-sm text-gray-600 mb-4">Provide a reason for rejecting this payment (optional):</p>
                        <textarea
                            value={rejectionReason}
                            onChange={(e) => setRejectionReason(e.target.value)}
                            rows={3}
                            placeholder="e.g. Screenshot is unclear, incorrect amount, etc."
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 mb-4"
                        />
                        <div className="flex justify-end gap-3">
                            <button onClick={() => { setRejectModalOpen(false); setRejectionReason(''); }}
                                className="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 text-sm">
                                Cancel
                            </button>
                            <button onClick={handleRejectPayment}
                                className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium">
                                Reject Payment
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Mark as Paid Modal */}
            {markPaidOpen && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg shadow-lg p-6 w-96">
                        <h3 className="text-lg font-semibold mb-4">Mark as Paid</h3>
                        <div className="mb-4 p-3 bg-gray-50 rounded-md">
                            <p className="text-sm text-gray-600">Payment Method:</p>
                            <p className="font-medium">{order.payment_method?.name || order.paymentMethod?.name || 'N/A'}</p>
                            {(order.paymentMethod?.account_number || order.payment_method?.account_number) && (
                                <p className="text-sm text-gray-500 mt-1">Account: {order.paymentMethod?.account_number || order.payment_method?.account_number}</p>
                            )}
                            {(order.paymentMethod?.account_name || order.payment_method?.account_name) && (
                                <p className="text-sm text-gray-500">Name: {order.paymentMethod?.account_name || order.payment_method?.account_name}</p>
                            )}
                        </div>
                        <form onSubmit={handleMarkAsPaid}>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Amount Received</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={paidAmount}
                                    onChange={(e) => setPaidAmount(e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-400"
                                    required
                                />
                                <p className="text-sm text-gray-500 mt-1">Total payable: {Number(order.total_payable || order.total_amount).toLocaleString()} MMK</p>
                            </div>
                            <div className="flex justify-end gap-3">
                                <button type="button" onClick={() => setMarkPaidOpen(false)} className="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 text-sm">
                                    Cancel
                                </button>
                                <button type="submit" className="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 text-sm">
                                    Confirm Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}