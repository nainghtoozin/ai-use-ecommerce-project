import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { assetUrl } from '@/Utils/helpers';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';

export default function ClientOrdersShow({ order }) {
    const { props } = usePage();
    const cc = getCurrencyConfig(props.platform_setting, props.website_info);
    const flash = props.flash || {};
    const { data, setData, post, processing, reset } = useForm({
        transaction_id: '',
        payment_proof: null,
    });

    const cityLabel = order.city?.name || order.city;
    const townshipLabel = order.township?.name;

    const orderStatusColors = {
        pending: 'bg-yellow-100 text-yellow-800',
        confirmed: 'bg-blue-100 text-blue-800',
        shipped: 'bg-indigo-100 text-indigo-800',
        delivered: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    };

    const paymentStatusColors = {
        unpaid: 'bg-gray-100 text-gray-800',
        paid: 'bg-blue-100 text-blue-800',
        verified: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
    };

    function handleCancel() {
        if (confirm('Cancel this order?')) {
            router.post(`/orders/${order.id}/cancel`);
        }
    }

    function handleUploadPayment(e) {
        e.preventDefault();
        post(`/orders/${order.id}/upload-payment`, {
            onSuccess: () => reset('transaction_id', 'payment_proof'),
        });
    }

    return (
        <ShopLayout>
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
                <div className="flex flex-wrap justify-between items-center gap-3 mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Order #{order.id}</h1>
                        <p className="text-gray-500 text-sm">{new Date(order.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                    </div>
                    <Link href="/client/orders" className="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        Back to Orders
                    </Link>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main - Items */}
                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-white rounded-lg border border-gray-200">
                            <div className="p-6">
                                <h2 className="text-lg font-semibold text-gray-900 mb-4">Items</h2>
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
                                                    <td className="py-3 text-sm font-medium text-gray-900">{item.product?.name || `Product #${item.product_id}`}</td>
                                                    <td className="py-3 text-sm text-right">{formatCurrency(item.price, cc)}</td>
                                                    <td className="py-3 text-sm text-right">{item.quantity}</td>
                                                    <td className="py-3 text-sm text-right font-medium">{formatCurrency(item.price * item.quantity, cc)}</td>
                                                </tr>
                                            )) : (
                                                <tr><td colSpan="4" className="py-4 text-center text-gray-500 text-sm">No items found.</td></tr>
                                            )}
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colSpan="3" className="text-right py-2 text-sm text-gray-600">Subtotal</td>
                                                <td className="text-right py-2 text-sm text-gray-900">{formatCurrency(order.subtotal || order.items_total, cc)}</td>
                                            </tr>
                                            <tr>
                                                <td colSpan="3" className="text-right py-2 text-sm text-gray-600">Delivery Fee</td>
                                                <td className="text-right py-2 text-sm text-gray-900">{formatCurrency(order.delivery_fee || 0, cc)}</td>
                                            </tr>
                                            <tr className="font-bold">
                                                <td colSpan="3" className="text-right py-2 text-sm text-gray-900">Total</td>
                                                <td className="text-right py-2 text-sm text-gray-900">{formatCurrency(order.total_amount, cc)}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {order.payment_status === 'unpaid' && (
                            <div className="bg-white rounded-lg border border-gray-200">
                                <div className="p-6">
                                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Upload Payment Proof</h2>
                                    <form onSubmit={handleUploadPayment} encType="multipart/form-data">
                                        <div className="mb-4">
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Transaction ID</label>
                                            <input type="text" value={data.transaction_id} onChange={(e) => setData('transaction_id', e.target.value)} className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                        </div>
                                        <div className="mb-4">
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Payment Proof</label>
                                            <input type="file" onChange={(e) => setData('payment_proof', e.target.files[0])} accept="image/*" required className="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                        </div>
                                        <button type="submit" disabled={processing} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium">
                                            {processing ? 'Uploading...' : 'Submit Payment Proof'}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        <div className="bg-white rounded-lg border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Order Status</h2>
                            <div className="space-y-3">
                                <div>
                                    <p className="text-sm text-gray-500">Order Status</p>
                                    <span className={`inline-block mt-1 px-2.5 py-0.5 rounded-full text-xs font-medium ${orderStatusColors[order.order_status] || 'bg-gray-100 text-gray-800'}`}>
                                        {order.order_status}
                                    </span>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Payment Status</p>
                                    <span className={`inline-block mt-1 px-2.5 py-0.5 rounded-full text-xs font-medium ${paymentStatusColors[order.payment_status] || 'bg-gray-100 text-gray-800'}`}>
                                        {order.payment_status}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white rounded-lg border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Payment Information</h2>
                            <div className="space-y-3">
                                <div>
                                    <p className="text-sm text-gray-500">Payment Method</p>
                                    <p className="font-medium text-gray-900">{order.payment_method?.name || order.paymentMethod?.name || 'N/A'}</p>
                                </div>
                                {order.payer_name && (
                                    <div>
                                        <p className="text-sm text-gray-500">Sender Name</p>
                                        <p className="font-medium text-gray-900">{order.payer_name}</p>
                                    </div>
                                )}
                                {order.transaction_id && (
                                    <div>
                                        <p className="text-sm text-gray-500">Transaction ID</p>
                                        <p className="font-medium text-gray-900">{order.transaction_id}</p>
                                    </div>
                                )}
                                {order.payment_screenshot && (
                                    <div>
                                        <p className="text-sm text-gray-500 mb-2">Payment Screenshot</p>
                                        <a href={order.payment_screenshot_url} target="_blank" rel="noopener noreferrer">
                                            <img src={order.payment_screenshot_url} alt="Payment Screenshot" className="w-40 h-40 rounded-lg border border-gray-200 object-cover hover:opacity-90 transition-opacity" />
                                        </a>
                                    </div>
                                )}
                                {order.paid_amount && (
                                    <div>
                                        <p className="text-sm text-gray-500">Paid Amount</p>
                                        <p className="font-medium text-green-600">{formatCurrency(order.paid_amount, cc)}</p>
                                    </div>
                                )}
                                {order.payment_proof && (
                                    <div>
                                        <p className="text-sm text-gray-500 mb-2">Payment Proof</p>
                                        <a href={order.payment_proof_url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100">
                                            <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                            View Proof
                                        </a>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="bg-white rounded-lg border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Delivery Information</h2>
                            <div className="space-y-3">
                                <div>
                                    <p className="text-sm text-gray-500">Name</p>
                                    <p className="font-medium text-gray-900">{order.first_name} {order.last_name}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Phone</p>
                                    <p className="font-medium text-gray-900">{order.phone}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Address</p>
                                    <p className="font-medium text-gray-900">{order.address}</p>
                                </div>
                                {(cityLabel || townshipLabel) && (
                                    <div>
                                        <p className="text-sm text-gray-500">City / Township</p>
                                        <p className="font-medium text-gray-900">{cityLabel}{townshipLabel ? `, ${townshipLabel}` : ''}</p>
                                    </div>
                                )}
                                {order.postal_code && (
                                    <div>
                                        <p className="text-sm text-gray-500">Postal Code</p>
                                        <p className="font-medium text-gray-900">{order.postal_code}</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="bg-white rounded-lg border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Actions</h2>
                            {order.can_cancel ? (
                                <button onClick={handleCancel} className="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium">
                                    Cancel Order
                                </button>
                            ) : (
                                <p className="text-sm text-gray-500">No actions available for this order.</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
