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
        processing: 'bg-purple-100 text-purple-800',
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

    const workflowSteps = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
    const currentStepIndex = workflowSteps.indexOf(order.order_status);

    function handleConfirm() {
        if (confirm('Confirm this order? Stock will be deducted.')) {
            router.post(`/admin/orders/${order.id}/confirm`);
        }
    }

    function handleProcess() {
        router.post(`/admin/orders/${order.id}/process`);
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

    function renderNextActionButton() {
        if (order.order_status === 'cancelled' || order.order_status === 'delivered') {
            return null;
        }

        if (order.order_status === 'pending') {
            if (order.payment_status !== 'verified') {
                return (
                    <div className="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                        <p className="text-sm text-amber-700">
                            Please verify payment before confirming this order.
                        </p>
                    </div>
                );
            }
            return (
                <button onClick={handleConfirm}
                    className="w-full bg-blue-600 text-white px-4 py-2.5 rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors">
                    Confirm Order
                </button>
            );
        }

        if (order.order_status === 'confirmed') {
            return (
                <button onClick={handleProcess}
                    className="w-full bg-purple-600 text-white px-4 py-2.5 rounded-lg hover:bg-purple-700 text-sm font-medium transition-colors">
                    Move to Processing
                </button>
            );
        }

        if (order.order_status === 'processing') {
            return (
                <button onClick={handleShip}
                    className="w-full bg-indigo-600 text-white px-4 py-2.5 rounded-lg hover:bg-indigo-700 text-sm font-medium transition-colors">
                    Mark as Shipped
                </button>
            );
        }

        if (order.order_status === 'shipped') {
            return (
                <button onClick={handleDeliver}
                    className="w-full bg-green-600 text-white px-4 py-2.5 rounded-lg hover:bg-green-700 text-sm font-medium transition-colors">
                    Mark as Delivered
                </button>
            );
        }

        return null;
    }

    function renderPaymentActions() {
        if (order.payment_status === 'verified') {
            return (
                <div className="space-y-2">
                    <div className="flex items-center gap-2 text-green-700">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span className="font-medium">Payment Verified</span>
                    </div>
                    {order.payment_verified_at && (
                        <p className="text-sm text-gray-500">
                            Verified At: {new Date(order.payment_verified_at).toLocaleString()}
                        </p>
                    )}
                </div>
            );
        }

        if (order.payment_status === 'rejected') {
            return (
                <div className="space-y-2">
                    <div className="flex items-center gap-2 text-red-700">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span className="font-medium">Payment Rejected</span>
                    </div>
                    {order.rejection_reason && (
                        <p className="text-sm text-gray-600">Reason: {order.rejection_reason}</p>
                    )}
                </div>
            );
        }

        if (order.payment_status === 'unpaid') {
            return (
                <button onClick={() => setMarkPaidOpen(true)}
                    className="w-full bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 text-sm font-medium transition-colors">
                    Mark as Paid
                </button>
            );
        }

        return (
            <div className="space-y-2">
                <button onClick={handleVerifyPayment}
                    className="w-full bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 text-sm font-medium transition-colors">
                    Verify Payment
                </button>
                <button onClick={() => setRejectModalOpen(true)}
                    className="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm font-medium transition-colors">
                    Reject Payment
                </button>
            </div>
        );
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
                        {/* Order Status with Progress Timeline */}
                        <div className="bg-white rounded-lg border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Order Progress</h2>

                            <div className="space-y-3">
                                {workflowSteps.map((step, index) => {
                                    const isCompleted = currentStepIndex > index;
                                    const isCurrent = currentStepIndex === index;
                                    const isPending = currentStepIndex < index;

                                    return (
                                        <div key={step} className="flex items-center gap-3">
                                            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium flex-shrink-0 ${
                                                isCompleted ? 'bg-green-600 text-white' :
                                                isCurrent ? 'bg-blue-600 text-white ring-2 ring-blue-300' :
                                                'bg-gray-200 text-gray-400'
                                            }`}>
                                                {isCompleted ? '✓' : index + 1}
                                            </div>
                                            <div>
                                                <p className={`text-sm font-medium capitalize ${
                                                    isCompleted ? 'text-green-700' :
                                                    isCurrent ? 'text-blue-700' :
                                                    'text-gray-400'
                                                }`}>{step}</p>
                                            </div>
                                            {index < workflowSteps.length - 1 && (
                                                <div className={`flex-1 h-0.5 mx-2 ${
                                                    isCompleted ? 'bg-green-400' :
                                                    isCurrent ? 'bg-blue-300' :
                                                    'bg-gray-200'
                                                }`}></div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>

                            <div className="mt-4 pt-4 border-t border-gray-200 space-y-2">
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
                                <div className="pt-2 border-t">
                                    <span className="text-sm text-gray-500">Order Date:</span>
                                    <p className="font-medium text-gray-900">{new Date(order.created_at).toLocaleString()}</p>
                                </div>
                            </div>
                        </div>

                        {/* Payment Section */}
                        <div className="bg-white rounded-lg border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Payment</h2>
                            <div className="space-y-3">
                                <div>
                                    <p className="text-sm text-gray-500">Payment Method:</p>
                                    <p className="font-medium text-gray-900">{order.payment_method?.name || order.paymentMethod?.name || 'N/A'}</p>
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
                                <div className="pt-3 border-t">
                                    {renderPaymentActions()}
                                </div>
                            </div>
                        </div>

                        {/* Next Action */}
                        {(order.order_status !== 'delivered' && order.order_status !== 'cancelled') && (
                            <div className="bg-white rounded-lg border border-gray-200 p-6">
                                <h2 className="text-lg font-semibold text-gray-900 mb-4">Next Action</h2>
                                {renderNextActionButton()}
                            </div>
                        )}

                        {/* Danger Zone */}
                        <div className="bg-white rounded-lg border border-red-200 p-6">
                            <h2 className="text-lg font-semibold text-red-700 mb-4">Danger Zone</h2>
                            <div className="space-y-3">
                                {order.can_cancel && (
                                    <button onClick={handleCancel}
                                        className="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm font-medium transition-colors">
                                        Cancel Order
                                    </button>
                                )}
                                {order.order_status === 'cancelled' && (
                                    <button onClick={handleDelete}
                                        className="w-full bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 text-sm font-medium transition-colors">
                                        Delete Order
                                    </button>
                                )}
                                {!order.can_cancel && order.order_status !== 'cancelled' && (
                                    <p className="text-sm text-gray-500">No destructive actions available.</p>
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
