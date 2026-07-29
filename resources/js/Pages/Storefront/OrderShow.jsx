import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';

const orderStatusColors = {
    pending: 'bg-amber-50 text-amber-700 border border-amber-200',
    confirmed: 'bg-blue-50 text-blue-700 border border-blue-200',
    processing: 'bg-indigo-50 text-indigo-700 border border-indigo-200',
    shipped: 'bg-sky-50 text-sky-700 border border-sky-200',
    delivered: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    cancelled: 'bg-red-50 text-red-700 border border-red-200',
};

const paymentStatusColors = {
    unpaid: 'bg-gray-50 text-gray-600 border border-gray-200',
    paid: 'bg-blue-50 text-blue-700 border border-blue-200',
    pending: 'bg-amber-50 text-amber-700 border border-amber-200',
    verified: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    rejected: 'bg-red-50 text-red-700 border border-red-200',
};

const timelineSteps = [
    { key: 'pending', label: 'Order Placed', icon: 'M5 13l4 4L19 7' },
    { key: 'confirmed', label: 'Confirmed', icon: 'M5 13l4 4L19 7' },
    { key: 'processing', label: 'Processing', icon: 'M5 13l4 4L19 7' },
    { key: 'shipped', label: 'Shipped', icon: 'M5 13l4 4L19 7' },
    { key: 'delivered', label: 'Delivered', icon: 'M5 13l4 4L19 7' },
];

function getStatusIndex(status) {
    const idx = timelineSteps.findIndex(s => s.key === status);
    return idx >= 0 ? idx : -1;
}

export default function OrderShow({ tenant, order }) {
    const storeSlug = tenant.slug;
    const { props } = usePage();
    const cc = getCurrencyConfig(props.platform_setting, props.website_info);
    const flash = props.flash || {};
    const { data, setData, post, processing, reset } = useForm({
        transaction_id: '',
        payment_proof: null,
    });

    const cityLabel = order.city?.name || order.city;
    const townshipLabel = order.township?.name;
    const isCancelled = order.order_status === 'cancelled';
    const currentStepIdx = isCancelled ? -1 : getStatusIndex(order.order_status);

    function handleCancel() {
        if (confirm('Cancel this order?')) {
            router.post(route('storefront.customer.orders.cancel', { store_slug: storeSlug, order: order.id }));
        }
    }

    function handleUploadPayment(e) {
        e.preventDefault();
        post(route('storefront.customer.orders.upload-payment', { store_slug: storeSlug, order: order.id }), {
            onSuccess: () => reset('transaction_id', 'payment_proof'),
        });
    }

    return (
        <ShopLayout>
            <Head title={`${order.invoice_number} - ${tenant.name}`} />

            <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {flash.success && (
                    <div className="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm font-medium">{flash.success}</div>
                )}
                {flash.error && (
                    <div className="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm font-medium">{flash.error}</div>
                )}

                <div className="flex flex-wrap justify-between items-start gap-4 mb-8">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 font-mono">{order.invoice_number}</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Placed on {new Date(order.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <a href={route('storefront.customer.orders.invoice', { store_slug: storeSlug, invoice_number: order.invoice_number })} target="_blank" className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl hover:bg-gray-50 transition-colors">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                            Print Invoice
                        </a>
                        <Link href={route('storefront.customer.orders', { store_slug: storeSlug })} className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl hover:bg-gray-50 transition-colors">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                            Back
                        </Link>
                    </div>
                </div>

                {/* Order Timeline */}
                {!isCancelled && (
                    <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm p-6 mb-6">
                        <div className="flex items-center justify-between">
                            {timelineSteps.map((step, idx) => {
                                const isComplete = idx <= currentStepIdx;
                                const isCurrent = idx === currentStepIdx;
                                return (
                                    <div key={step.key} className="flex-1 flex flex-col items-center relative">
                                        {idx > 0 && (
                                            <div className={`absolute top-4 right-1/2 w-full h-0.5 -translate-y-1/2 ${idx <= currentStepIdx ? 'bg-emerald-400' : 'bg-gray-200'}`} />
                                        )}
                                        <div className={`relative z-10 w-8 h-8 rounded-full flex items-center justify-center ${isComplete ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-400 dark:text-gray-500'} ${isCurrent ? 'ring-4 ring-emerald-100' : ''}`}>
                                            {isComplete ? (
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d={step.icon} /></svg>
                                            ) : (
                                                <span className="text-xs font-bold">{idx + 1}</span>
                                            )}
                                        </div>
                                        <p className={`mt-2 text-xs font-medium text-center ${isComplete ? 'text-emerald-700' : 'text-gray-400 dark:text-gray-500'}`}>{step.label}</p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {isCancelled && (
                    <div className="bg-red-50 rounded-2xl border border-red-200 p-6 mb-6 text-center">
                        <svg className="w-10 h-10 mx-auto text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                        <p className="mt-2 text-base font-semibold text-red-700">This order has been cancelled</p>
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        {/* Items */}
                        <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                            <div className="p-6">
                                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Items</h2>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full">
                                        <thead>
                                            <tr className="border-b border-gray-200 dark:border-gray-800">
                                                <th className="text-left py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Product</th>
                                                <th className="text-right py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Price</th>
                                                <th className="text-right py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Qty</th>
                                                <th className="text-right py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {order.items?.length ? order.items.map((item) => (
                                                <tr key={item.id} className="border-b border-gray-100 dark:border-gray-800 last:border-0">
                                                    <td className="py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{item.product?.name || `Product #${item.product_id}`}</td>
                                                    <td className="py-3 text-sm text-right text-gray-600 dark:text-gray-400">{formatCurrency(item.price, cc)}</td>
                                                    <td className="py-3 text-sm text-right text-gray-600 dark:text-gray-400">{item.quantity}</td>
                                                    <td className="py-3 text-sm text-right font-semibold text-gray-900 dark:text-gray-100">{formatCurrency(item.price * item.quantity, cc)}</td>
                                                </tr>
                                            )) : (
                                                <tr><td colSpan="4" className="py-6 text-center text-gray-500 dark:text-gray-400 text-sm">No items found.</td></tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div className="px-6 py-4 bg-gray-50 dark:bg-gray-950 border-t border-gray-100 dark:border-gray-800 space-y-2">
                                <div className="flex justify-between text-sm"><span className="text-gray-500">Subtotal</span><span className="font-medium text-gray-900 dark:text-gray-100">{formatCurrency(order.subtotal || order.items_total, cc)}</span></div>
                                <div className="flex justify-between text-sm"><span className="text-gray-500">Delivery Fee</span><span className="font-medium text-gray-900 dark:text-gray-100">{formatCurrency(order.delivery_fee || 0, cc)}</span></div>
                                {order.discount_amount > 0 && <div className="flex justify-between text-sm"><span className="text-emerald-600">Discount</span><span className="font-medium text-emerald-600">-{formatCurrency(order.discount_amount, cc)}</span></div>}
                                <div className="flex justify-between text-base font-bold pt-2 border-t border-gray-200 dark:border-gray-800"><span className="text-gray-900 dark:text-gray-100">Total</span><span className="text-gray-900 dark:text-gray-100">{formatCurrency(order.total_amount, cc)}</span></div>
                            </div>
                        </div>

                        {/* Upload Payment */}
                        {order.payment_status === 'unpaid' && (
                            <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm p-6">
                                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Upload Payment Proof</h2>
                                <form onSubmit={handleUploadPayment} encType="multipart/form-data" className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Transaction ID</label>
                                        <input type="text" value={data.transaction_id} onChange={(e) => setData('transaction_id', e.target.value)} className="w-full border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Payment Proof</label>
                                        <input type="file" onChange={(e) => setData('payment_proof', e.target.files[0])} accept="image/*" required className="w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                    </div>
                                    <button type="submit" disabled={processing} className="px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 disabled:opacity-50 text-base font-medium transition-colors">
                                        {processing ? 'Uploading...' : 'Submit Payment Proof'}
                                    </button>
                                </form>
                            </div>
                        )}
                    </div>

                    <div className="space-y-6">
                        {/* Status */}
                        <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm p-5">
                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">Status</h2>
                            <div className="space-y-3">
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium mb-1">Order</p>
                                    <span className={`inline-block px-3 py-1 rounded-full text-sm font-semibold ${orderStatusColors[order.order_status] || 'bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-800'}`}>
                                        {order.order_status}
                                    </span>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium mb-1">Payment</p>
                                    <span className={`inline-block px-3 py-1 rounded-full text-sm font-semibold ${paymentStatusColors[order.payment_status] || 'bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-800'}`}>
                                        {order.payment_status}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Payment Info */}
                        <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm p-5">
                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">Payment</h2>
                            <div className="space-y-3">
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium">Method</p>
                                    <p className="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-0.5">{order.payment_method?.name || order.paymentMethod?.name || 'N/A'}</p>
                                </div>
                                {order.payer_name && <div><p className="text-xs text-gray-500 uppercase tracking-wide font-medium">Sender Name</p><p className="text-sm font-medium text-gray-900 dark:text-gray-100 mt-0.5">{order.payer_name}</p></div>}
                                {order.sender_account_number && <div><p className="text-xs text-gray-500 uppercase tracking-wide font-medium">Sender Account</p><p className="text-sm font-medium text-gray-900 dark:text-gray-100 mt-0.5 font-mono">{order.sender_account_number}</p></div>}
                                {order.transaction_id && <div><p className="text-xs text-gray-500 uppercase tracking-wide font-medium">Transaction ID</p><p className="text-sm font-medium text-gray-900 dark:text-gray-100 mt-0.5 font-mono">{order.transaction_id}</p></div>}
                                {order.paid_amount && <div><p className="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium">Paid Amount</p><p className="text-base font-bold text-emerald-600 mt-0.5">{formatCurrency(order.paid_amount, cc)}</p></div>}
                            </div>
                        </div>

                        {/* Delivery */}
                        <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm p-5">
                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">Delivery</h2>
                            <div className="space-y-2.5">
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium">Name</p>
                                    <p className="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-0.5">{order.first_name} {order.last_name}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium">Phone</p>
                                    <p className="text-sm text-gray-900 dark:text-gray-100 mt-0.5">{order.phone}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium">Address</p>
                                    <p className="text-sm text-gray-900 dark:text-gray-100 mt-0.5">{order.address}</p>
                                </div>
                                {(cityLabel || townshipLabel) && (
                                    <div>
                                        <p className="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium">City / Township</p>
                                        <p className="text-sm text-gray-900 dark:text-gray-100 mt-0.5">{cityLabel}{townshipLabel ? `, ${townshipLabel}` : ''}</p>
                                    </div>
                                )}
                                {order.postal_code && (
                                    <div>
                                        <p className="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium">Postal Code</p>
                                        <p className="text-sm text-gray-900 dark:text-gray-100 mt-0.5">{order.postal_code}</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Actions */}
                        {order.can_cancel && (
                            <button onClick={handleCancel} className="w-full px-5 py-3 bg-red-600 text-white rounded-xl hover:bg-red-700 text-base font-medium transition-colors shadow-sm">
                                Cancel Order
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
