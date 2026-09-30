import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import { isCodOrder as checkCodOrder, isCodUnpaid, codPaymentStatusLabel, codPaymentMethodLabel } from '@/Utils/codDisplay';

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

export default function OrderShow({ tenant, order, isCodOrder: isCodOrderProp = false }) {
    const storeSlug = tenant.slug;
    const isCodOrder = isCodOrderProp || checkCodOrder(order);
    const codUnpaid = isCodUnpaid(order);
    const { props } = usePage();
    const storefront = props.storefront;
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

    function itemImage(item) {
        const src = item.image || item.product?.image;
        return typeof src === 'string' && src ? src : null;
    }

    function itemVariant(item) {
        const v = item.variant_name || (typeof item.variant === 'string' ? item.variant : item.variant?.name);
        return v || null;
    }

    function itemPricing(item) {
        const price = Number(item.price);
        const rawOrig = item.original_price != null ? Number(item.original_price) : null;
        const discounted = rawOrig != null && rawOrig > price;
        let discount = null;
        if (discounted && price > 0) {
            const pct = Math.round((1 - price / rawOrig) * 100);
            discount = pct >= 1 ? `-${pct}%` : `-${formatCurrency(rawOrig - price, cc)}`;
        }
        return { price, orig: discounted ? rawOrig : null, discount };
    }

    return (
        <ShopLayout>
            <Head title={`${order.invoice_number} - ${storefront?.identity?.site_title || tenant.name}`} />

            <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
                {flash.success && (
                    <div className="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-2.5 rounded-lg text-sm font-medium">{flash.success}</div>
                )}
                {flash.error && (
                    <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-3 py-2.5 rounded-lg text-sm font-medium">{flash.error}</div>
                )}

                {/* Compact header */}
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div className="flex items-center gap-3 min-w-0">
                        <Link href={route('storefront.customer.orders', { store_slug: storeSlug })} className="inline-flex items-center justify-center w-8 h-8 shrink-0 text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg hover:bg-gray-50 transition-colors" title="Back to orders">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        </Link>
                        <div className="min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                                <h1 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-gray-100 font-mono truncate">Order #{order.invoice_number}</h1>
                                <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-semibold ${orderStatusColors[order.order_status] || 'bg-gray-50 text-gray-600 border border-gray-200'}`}>
                                    {order.order_status}
                                </span>
                                {codUnpaid ? (
                                    <span className="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">Due on Delivery</span>
                                ) : (
                                    <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-semibold ${paymentStatusColors[order.payment_status] || 'bg-gray-50 text-gray-600 border border-gray-200'}`}>
                                        {codPaymentStatusLabel(order)}
                                    </span>
                                )}
                            </div>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Placed on {new Date(order.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                            </p>
                        </div>
                    </div>
                    <a href={route('storefront.customer.orders.invoice', { store_slug: storeSlug, invoice_number: order.invoice_number })} target="_blank" className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg hover:bg-gray-50 transition-colors">
                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                        Print Invoice
                    </a>
                </div>

                {/* Compact progress timeline */}
                {!isCancelled && (
                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 px-4 py-3 mb-4">
                        <div className="flex items-center justify-between">
                            {timelineSteps.map((step, idx) => {
                                const isComplete = idx <= currentStepIdx;
                                const isCurrent = idx === currentStepIdx;
                                return (
                                    <div key={step} className="flex-1 flex flex-col items-center relative">
                                        {idx > 0 && (
                                            <div className={`absolute top-3.5 right-1/2 w-full h-0.5 -translate-y-1/2 ${idx <= currentStepIdx ? 'bg-emerald-400' : 'bg-gray-200'}`} />
                                        )}
                                        <div className={`relative z-10 w-7 h-7 rounded-full flex items-center justify-center ${isComplete ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-400 dark:text-gray-500'} ${isCurrent ? 'ring-4 ring-emerald-100 dark:ring-emerald-900/40' : ''}`}>
                                            {isComplete ? (
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d={step.icon} /></svg>
                                            ) : (
                                                <span className="text-[11px] font-bold">{idx + 1}</span>
                                            )}
                                        </div>
                                        <p className={`mt-1.5 text-[11px] font-medium text-center leading-tight ${isComplete ? 'text-emerald-700 dark:text-emerald-500' : 'text-gray-400 dark:text-gray-500'}`}>{step.label}</p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {isCancelled && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 mb-4 flex items-center gap-2.5">
                        <svg className="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                        <p className="text-sm font-semibold text-red-700">This order has been cancelled</p>
                    </div>
                )}

                <div className="space-y-4">
                        {/* Items */}
                        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                            <div className="px-4 pt-3 pb-1 flex items-center justify-between">
                                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Items</h2>
                                <span className="text-xs text-gray-500 dark:text-gray-400">{order.items?.length || 0} product{(order.items?.length || 0) === 1 ? '' : 's'}</span>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full">
                                    <thead>
                                            <tr className="border-b-2 border-gray-200 dark:border-gray-700">
                                            <th className="text-left px-4 py-2 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Product</th>
                                            <th className="hidden md:table-cell text-right px-4 py-2 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Original Price</th>
                                            <th className="hidden sm:table-cell text-right px-4 py-2 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Discount</th>
                                            <th className="text-right px-4 py-2 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Price</th>
                                            <th className="text-right py-2 px-4 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Qty</th>
                                            <th className="text-right py-2 px-4 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {order.items?.length ? order.items.map((item) => {
                                            const p = itemPricing(item);
                                            return (
                                            <tr key={item.id} className="border-b border-gray-100 dark:border-gray-800 last:border-0">
                                                <td className="px-4 py-2.5">
                                                    <div className="flex items-center gap-2.5">
                                                        {itemImage(item) ? (
                                                            <img src={itemImage(item)} alt="" className="w-9 h-9 rounded-lg object-cover border border-gray-200 dark:border-gray-800 shrink-0" />
                                                        ) : (
                                                            <div className="w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center shrink-0">
                                                                <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                                            </div>
                                                        )}
                                                        <div className="min-w-0">
                                                            <p className="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{item.product?.name || `Product #${item.product_id}`}</p>
                                                            {itemVariant(item) && (
                                                                <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{itemVariant(item)}</p>
                                                            )}
                                                            {p.discount && (
                                                                <p className="md:hidden text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                                    <span className="text-gray-400 line-through mr-1">{formatCurrency(p.orig, cc)}</span>
                                                                    {p.discount}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="hidden md:table-cell px-4 py-2.5 text-sm text-right whitespace-nowrap">
                                                    {p.orig != null ? (
                                                        <span className="text-xs text-gray-400 dark:text-gray-500 line-through">{formatCurrency(p.orig, cc)}</span>
                                                    ) : (
                                                        <span className="text-gray-300 dark:text-gray-600">—</span>
                                                    )}
                                                </td>
                                                <td className="hidden sm:table-cell px-4 py-2.5 text-right whitespace-nowrap">
                                                    {p.discount ? (
                                                        <span className="inline-block px-1.5 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800">{p.discount}</span>
                                                    ) : (
                                                        <span className="text-sm text-gray-300 dark:text-gray-600">—</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2.5 text-sm text-right font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">{formatCurrency(p.price, cc)}</td>
                                                <td className="px-4 py-2.5 text-sm text-right text-gray-600 dark:text-gray-400">{item.quantity}</td>
                                                <td className="px-4 py-2.5 text-sm text-right font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">{formatCurrency(p.price * item.quantity, cc)}</td>
                                            </tr>
                                            );
                                        }) : (
                                            <tr><td colSpan="6" className="py-5 text-center text-gray-500 dark:text-gray-400 text-sm">No items found.</td></tr>
                                        )}
                                    </tbody>
                                    <tfoot>
                                        <tr className="border-t-2 border-gray-200 dark:border-gray-700">
                                            <td colSpan="5" className="px-4 pt-2.5 pb-1 text-sm text-right text-gray-500 dark:text-gray-400">Subtotal</td>
                                            <td className="px-4 pt-2.5 pb-1 text-sm text-right font-medium">{formatCurrency(order.subtotal || order.items_total, cc)}</td>
                                        </tr>
                                        {order.discount_amount > 0 && (
                                            <tr>
                                                <td colSpan="5" className="px-4 py-1 text-sm text-right text-emerald-600 dark:text-emerald-400">Coupon Discount</td>
                                                <td className="px-4 py-1 text-sm text-right font-medium text-emerald-600 dark:text-emerald-400">-{formatCurrency(order.discount_amount, cc)}</td>
                                            </tr>
                                        )}
                                        {order.delivery_fee > 0 && (
                                            <tr>
                                                <td colSpan="5" className="px-4 py-1 text-sm text-right text-gray-500 dark:text-gray-400">Delivery Fee</td>
                                                <td className="px-4 py-1 text-sm text-right font-medium">{formatCurrency(order.delivery_fee, cc)}</td>
                                            </tr>
                                        )}
                                        {order.packaging_id && order.packaging_fee > 0 && (
                                            <tr>
                                                <td colSpan="5" className="px-4 py-1 text-sm text-right text-gray-500 dark:text-gray-400">Packaging</td>
                                                <td className="px-4 py-1 text-sm text-right font-medium">{formatCurrency(order.packaging_fee, cc)}</td>
                                            </tr>
                                        )}
                                        <tr className="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-950/60">
                                            <td colSpan="5" className="px-4 py-3 text-sm text-right font-bold text-gray-900 dark:text-gray-100 tracking-wide">TOTAL</td>
                                            <td className="px-4 py-3 text-right text-lg font-extrabold text-gray-900 dark:text-gray-100">{formatCurrency(order.total_amount, cc)}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        {/* Upload Payment */}
                        {order.payment_status === 'unpaid' && (
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm p-4">
                                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Upload Payment Proof</h2>
                                <form onSubmit={handleUploadPayment} encType="multipart/form-data" className="space-y-3">
                                    <div>
                                        <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Transaction ID</label>
                                        <input type="text" value={data.transaction_id} onChange={(e) => setData('transaction_id', e.target.value)} className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Proof</label>
                                        <input type="file" onChange={(e) => setData('payment_proof', e.target.files[0])} accept="image/*" required className="w-full text-xs text-gray-500 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                    </div>
                                    <button type="submit" disabled={processing} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium transition-colors">
                                        {processing ? 'Uploading...' : 'Submit Payment Proof'}
                                    </button>
                                </form>
                            </div>
                        )}

                    {/* Horizontal info grid: Payment | Delivery */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                        {/* Payment */}
                        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Payment</h2>
                            {isCodOrder ? (
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="text-xs text-gray-500 dark:text-gray-400">Method</p>
                                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{codPaymentMethodLabel(order)}</p>
                                    </div>
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="text-xs text-gray-500 dark:text-gray-400">Payment</p>
                                        {codUnpaid ? (
                                            <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">Due on Delivery</span>
                                        ) : (
                                            <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">Paid</span>
                                        )}
                                    </div>
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="text-xs text-gray-500 dark:text-gray-400">Amount {codUnpaid ? 'Due' : ''}</p>
                                        <p className="text-sm font-bold text-gray-900 dark:text-gray-100">{formatCurrency(order.total_amount, cc)}</p>
                                    </div>
                                    {codUnpaid && (
                                        <div className="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg px-3 py-2 mt-1">
                                            <p className="text-xs text-emerald-800 dark:text-emerald-400 font-medium">Pay the delivery person when your order arrives.</p>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <div className="flex items-start justify-between gap-3">
                                        <p className="text-xs text-gray-500 dark:text-gray-400">Method</p>
                                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100 text-right">{order.payment_method?.name || order.paymentMethod?.name || 'N/A'}</p>
                                    </div>
                                    {order.payer_name && <div className="flex items-start justify-between gap-3"><p className="text-xs text-gray-500 dark:text-gray-400">Sender Name</p><p className="text-sm font-medium text-gray-900 dark:text-gray-100 text-right">{order.payer_name}</p></div>}
                                    {order.sender_account_number && <div className="flex items-start justify-between gap-3"><p className="text-xs text-gray-500 dark:text-gray-400">Sender Account</p><p className="text-sm font-medium text-gray-900 dark:text-gray-100 text-right font-mono break-all">{order.sender_account_number}</p></div>}
                                    {order.transaction_id && <div className="flex items-start justify-between gap-3"><p className="text-xs text-gray-500 dark:text-gray-400">Transaction ID</p><p className="text-sm font-medium text-gray-900 dark:text-gray-100 text-right font-mono break-all">{order.transaction_id}</p></div>}
                                    {order.paid_amount && <div className="flex items-start justify-between gap-3"><p className="text-xs text-gray-500 dark:text-gray-400">Paid Amount</p><p className="text-sm font-bold text-emerald-600 dark:text-emerald-400 text-right">{formatCurrency(order.paid_amount, cc)}</p></div>}
                                </div>
                            )}
                        </div>

                        {/* Delivery */}
                        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Delivery</h2>
                            <div className="space-y-1 text-sm">
                                <p className="font-semibold text-gray-900 dark:text-gray-100">{order.first_name} {order.last_name} · {order.phone}</p>
                                <p className="text-gray-700 dark:text-gray-300">{order.address}</p>
                                {(cityLabel || townshipLabel || order.postal_code) && (
                                    <p className="text-gray-700 dark:text-gray-300">
                                        {[cityLabel, townshipLabel].filter(Boolean).join(', ')}
                                        {order.postal_code ? ` · ${order.postal_code}` : ''}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Actions */}
                    {order.can_cancel && (
                        <button onClick={handleCancel} className="w-full sm:w-auto sm:px-6 px-4 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium transition-colors shadow-sm">
                            Cancel Order
                        </button>
                    )}
                </div>
            </div>
        </ShopLayout>
    );
}
