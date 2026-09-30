import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import { usePermission } from '@/Hooks/usePermission';
import { isCodUnpaid, codPaymentStatusLabel, codPaymentMethodLabel } from '@/Utils/codDisplay';

export default function AdminOrdersShow({ order, isCodOrder = false }) {
    const { auth, flash: pageFlash, platform_setting, website_info } = usePage().props;
    const cc = getCurrencyConfig(platform_setting, website_info);
    const flash = pageFlash || {};
    const [imagePreview, setImagePreview] = useState(null);
    const [rejectModalOpen, setRejectModalOpen] = useState(false);
    const [rejectionReason, setRejectionReason] = useState('');
    const [overrideModal, setOverrideModal] = useState(null);
    const [overrideNewStatus, setOverrideNewStatus] = useState('');
    const [overrideReason, setOverrideReason] = useState('');

    const { can } = usePermission();
    const canOverrideStatus = can('orders.override-status');
    const canOverridePayment = can('orders.override-payment');

    const cityName = order.city?.name;
    const townshipName = order.township?.name;
    const isCancelled = order.order_status === 'cancelled';

    const orderStatusColors = {
        pending: 'bg-yellow-100 text-yellow-800',
        confirmed: 'bg-blue-100 text-blue-800',
        processing: 'bg-purple-100 text-purple-800',
        shipped: 'bg-indigo-100 text-indigo-800',
        delivered: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    };

    const paymentStatusColors = {
        pending: 'bg-yellow-100 text-yellow-800',
        paid: 'bg-green-100 text-green-800',
        failed: 'bg-red-100 text-red-800',
        refunded: 'bg-purple-100 text-purple-800',
    };

    const workflowSteps = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
    const currentStepIndex = workflowSteps.indexOf(order.order_status);

    function handleConfirm() {
        if (confirm('Confirm this order? Stock will be deducted.')) {
            router.post(adminUrl(`/admin/orders/${order.id}/confirm`));
        }
    }

    function handleProcess() {
        router.post(adminUrl(`/admin/orders/${order.id}/process`));
    }

    function handleShip() {
        router.post(adminUrl(`/admin/orders/${order.id}/ship`));
    }

    function handleDeliver() {
        if (confirm('Mark as delivered?')) {
            router.post(adminUrl(`/admin/orders/${order.id}/deliver`));
        }
    }

    function handleCancel() {
        if (confirm('Are you sure you want to cancel this order? Stock will be restored.')) {
            router.post(adminUrl(`/admin/orders/${order.id}/cancel`));
        }
    }

    function handleVerifyPayment() {
        if (confirm('Verify this payment?')) {
            router.post(adminUrl(`/admin/orders/${order.id}/verify-payment`));
        }
    }

    function handleCollectCodPayment() {
        if (confirm(`Confirm cash of ${formatCurrency(order.total_amount, cc)} was collected for this order?`)) {
            router.post(adminUrl(`/admin/orders/${order.id}/collect-cod-payment`));
        }
    }

    function handleRejectPayment() {
        router.post(adminUrl(`/admin/orders/${order.id}/reject-payment`), {
            rejection_reason: rejectionReason,
        }, {
            onSuccess: () => {
                setRejectModalOpen(false);
                setRejectionReason('');
            },
        });
    }

    function handleDelete() {
        if (confirm('Delete this cancelled order?')) {
            router.delete(adminUrl(`/admin/orders/${order.id}`));
        }
    }

    function handleOverride() {
        if (!overrideModal || !overrideNewStatus || !overrideReason.trim()) return;

        const endpoint = overrideModal === 'order_status'
            ? adminUrl(`/admin/orders/${order.id}/override-status`)
            : adminUrl(`/admin/orders/${order.id}/override-payment`);

        router.post(endpoint, {
            new_status: overrideNewStatus,
            reason: overrideReason,
        }, {
            onSuccess: () => {
                setOverrideModal(null);
                setOverrideNewStatus('');
                setOverrideReason('');
            },
        });
    }

    function openOverrideModal(type) {
        setOverrideModal(type);
        setOverrideNewStatus('');
        setOverrideReason('');
    }

    const orderStatusOptions = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
    const paymentStatusOptions = ['pending', 'paid', 'failed', 'refunded'];

    function renderNextActionButton() {
        if (!can('orders.update-status')) return null;

        if (order.can_confirm) {
            return (
                <button onClick={handleConfirm}
                    className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors">
                    Confirm Order
                </button>
            );
        }

        if (order.can_process) {
            return (
                <button onClick={handleProcess}
                    className="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 text-sm font-medium transition-colors">
                    Move to Processing
                </button>
            );
        }

        if (order.can_ship) {
            return (
                <button onClick={handleShip}
                    className="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 text-sm font-medium transition-colors">
                    Mark as Shipped
                </button>
            );
        }

        if (order.can_deliver) {
            return (
                <button onClick={handleDeliver}
                    className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm font-medium transition-colors">
                    Mark as Delivered
                </button>
            );
        }

        return null;
    }

    function renderPaymentActions() {
        if (isCodOrder && order.payment_status === 'paid') {
            return (
                <div className="space-y-1.5">
                    <div className="flex items-center gap-2 text-green-700">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span className="text-sm font-medium">Cash Collected</span>
                    </div>
                    {order.payment_verified_at && (
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Collected At: {new Date(order.payment_verified_at).toLocaleString()}
                        </p>
                    )}
                </div>
            );
        }

        if (isCodOrder && order.payment_status === 'pending') {
            return (
                <div className="space-y-2">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-gray-500 dark:text-gray-400">Amount to Collect</span>
                        <span className="font-bold text-gray-900 dark:text-gray-100">{formatCurrency(order.total_amount, cc)}</span>
                    </div>
                    {can('orders.update-status') && (
                        <button onClick={handleCollectCodPayment}
                            className="w-full bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 text-sm font-medium transition-colors">
                            Mark Payment Collected
                        </button>
                    )}
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        Collect cash on delivery, then mark this order as paid.
                    </p>
                </div>
            );
        }

        if (order.payment_status === 'paid') {
            return (
                <div className="space-y-1.5">
                    <div className="flex items-center gap-2 text-green-700">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span className="text-sm font-medium">Payment Verified</span>
                    </div>
                    {order.payment_verified_at && (
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Verified At: {new Date(order.payment_verified_at).toLocaleString()}
                        </p>
                    )}
                </div>
            );
        }

        if (order.payment_status === 'failed') {
            return (
                <div className="space-y-1.5">
                    <div className="flex items-center gap-2 text-red-700">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span className="text-sm font-medium">Payment Failed</span>
                    </div>
                    {order.rejection_reason && (
                        <p className="text-xs text-gray-600 dark:text-gray-400">Reason: {order.rejection_reason}</p>
                    )}
                </div>
            );
        }

        if (order.payment_status === 'refunded') {
            return (
                <div className="space-y-1.5">
                    <div className="flex items-center gap-2 text-purple-700">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span className="text-sm font-medium">Payment Refunded</span>
                    </div>
                </div>
            );
        }

        if (order.payment_status === 'pending' && order.can_verify_payment) {
            return (
                <div className="space-y-2">
                    {can('orders.update-status') && (
                        <button onClick={() => setRejectModalOpen(true)}
                            className="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm font-medium transition-colors">
                            Reject Payment
                        </button>
                    )}
                </div>
            );
        }

        return null;
    }

    function itemImage(item) {
        const src = item.image || item.product?.image;
        return typeof src === 'string' && src ? src : null;
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
        <AdminLayout>
            <Head title={`Order #${order.invoice_number || order.id}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
                {flash.success && (
                    <div className="mb-4 bg-green-100 border border-green-400 text-green-700 px-3 py-2.5 rounded-lg text-sm">{flash.success}</div>
                )}
                {flash.error && (
                    <div className="mb-4 bg-red-100 border border-red-400 text-red-700 px-3 py-2.5 rounded-lg text-sm">{flash.error}</div>
                )}

                {/* Compact header */}
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div className="flex items-center gap-3 min-w-0">
                        <Link href={adminUrl('/admin/orders')} className="inline-flex items-center justify-center w-8 h-8 shrink-0 text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg hover:bg-gray-50" title="Back to Orders">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        </Link>
                        <div className="min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                                <h1 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-gray-100 font-mono truncate">Order #{order.invoice_number || order.id}</h1>
                                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${orderStatusColors[order.order_status] || 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200'}`}>
                                    {order.order_status}
                                </span>
                                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${isCodUnpaid(order) ? 'bg-amber-100 text-amber-800' : (paymentStatusColors[order.payment_status] || 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200')}`}>
                                    {codPaymentStatusLabel(order)}
                                </span>
                            </div>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {new Date(order.created_at).toLocaleString()} · {order.first_name} {order.last_name} · {order.phone}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Compact horizontal progress */}
                {isCancelled ? (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 mb-4 flex items-center gap-2.5">
                        <svg className="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                        <p className="text-sm font-semibold text-red-700">This order has been cancelled</p>
                    </div>
                ) : (
                    <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 px-4 py-3 mb-4">
                        <div className="flex items-center justify-between">
                            {workflowSteps.map((step, idx) => {
                                const isComplete = idx < currentStepIndex;
                                const isCurrent = idx === currentStepIndex;
                                return (
                                    <div key={step} className="flex-1 flex flex-col items-center relative">
                                        {idx > 0 && (
                                            <div className={`absolute top-3.5 right-1/2 w-full h-0.5 -translate-y-1/2 ${idx <= currentStepIndex ? 'bg-green-400' : 'bg-gray-200'}`} />
                                        )}
                                        <div className={`relative z-10 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ${isComplete ? 'bg-green-600 text-white' : isCurrent ? 'bg-blue-600 text-white ring-4 ring-blue-100 dark:ring-blue-900/40' : 'bg-gray-200 text-gray-400'}`}>
                                            {isComplete ? '✓' : idx + 1}
                                        </div>
                                        <p className={`mt-1.5 text-[11px] font-medium text-center capitalize leading-tight ${isComplete ? 'text-green-700' : isCurrent ? 'text-blue-700' : 'text-gray-400'}`}>{step}</p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* Unified Items + Price Summary (invoice style) */}
                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden mb-4">
                    <div className="px-4 pt-3 pb-1 flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Order Items</h2>
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
                                            <th className="text-right px-4 py-2 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Qty</th>
                                            <th className="text-right px-4 py-2 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total</th>
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
                                                            {item.product && <p className="text-xs text-gray-500 dark:text-gray-400">SKU: {item.product.id}</p>}
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
                                                <td className="px-4 py-2.5 text-sm text-right">{item.quantity}</td>
                                                <td className="px-4 py-2.5 text-sm text-right font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">{formatCurrency(p.price * item.quantity, cc)}</td>
                                            </tr>
                                            );
                                        }) : (
                                            <tr><td colSpan="6" className="py-5 text-center text-gray-500 dark:text-gray-400 text-sm">No items found</td></tr>
                                        )}
                                    </tbody>
                                    <tfoot>
                                        <tr className="border-t-2 border-gray-200 dark:border-gray-700">
                                            <td colSpan="5" className="px-4 pt-2.5 pb-1 text-sm text-right text-gray-500 dark:text-gray-400">Subtotal</td>
                                            <td className="px-4 pt-2.5 pb-1 text-sm text-right font-medium">{formatCurrency(order.items_total, cc)}</td>
                                        </tr>
                                        {order.discount_amount > 0 && (
                                            <tr>
                                                <td colSpan="5" className="px-4 py-1 text-sm text-right text-emerald-600 dark:text-emerald-400">Coupon Discount</td>
                                                <td className="px-4 py-1 text-sm text-right font-medium text-emerald-600 dark:text-emerald-400">-{formatCurrency(order.discount_amount, cc)}</td>
                                            </tr>
                                        )}
                                        <tr>
                                            <td colSpan="5" className="px-4 py-1 text-sm text-right text-gray-500 dark:text-gray-400">Delivery Fee</td>
                                            <td className="px-4 py-1 text-sm text-right font-medium">{formatCurrency(order.delivery_fee || 0, cc)}</td>
                                        </tr>
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

                    {/* Horizontal info grid: Customer | Payment | Delivery */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                        {/* Customer */}
                        <div className="order-1 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Customer</h2>
                            <div className="space-y-1.5 text-sm">
                                <div className="flex justify-between gap-3">
                                    <span className="text-xs text-gray-500 dark:text-gray-400">Name</span>
                                    <span className="font-medium text-gray-900 dark:text-gray-100 text-right">{order.first_name} {order.last_name}</span>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <span className="text-xs text-gray-500 dark:text-gray-400">Phone</span>
                                    <span className="font-medium text-gray-900 dark:text-gray-100 text-right">{order.phone}</span>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <span className="text-xs text-gray-500 dark:text-gray-400">Email</span>
                                    <span className="font-medium text-gray-900 dark:text-gray-100 text-right truncate">{order.email || 'N/A'}</span>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <span className="text-xs text-gray-500 dark:text-gray-400">Account</span>
                                    <span className="font-medium text-gray-900 dark:text-gray-100 text-right">{order.user?.name || 'Guest'}</span>
                                </div>
                                {order.notes && (
                                    <div className="pt-1.5 mt-1 border-t border-gray-100 dark:border-gray-800">
                                        <span className="text-xs text-gray-500 dark:text-gray-400">Notes</span>
                                        <p className="font-medium text-gray-900 dark:text-gray-100">{order.notes}</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Payment Section */}
                        <div className="order-3 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Payment</h2>
                            <div className="space-y-2">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-xs text-gray-500 dark:text-gray-400">Method</span>
                                    <span className="text-sm font-medium text-gray-900 dark:text-gray-100 text-right">{codPaymentMethodLabel(order)}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-xs text-gray-500 dark:text-gray-400">Total Payable</span>
                                    <span className="font-bold text-base">{formatCurrency(order.total_payable || order.total_amount, cc)}</span>
                                </div>
                                {order.paid_amount && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs text-gray-500 dark:text-gray-400">Paid Amount</span>
                                        <span className={`font-medium text-sm ${order.is_payment_amount_correct ? 'text-green-600' : 'text-red-600'}`}>
                                            {formatCurrency(order.paid_amount, cc)}
                                            {!order.is_payment_amount_correct && <span className="text-xs text-red-500 ml-1">(Short payment)</span>}
                                        </span>
                                    </div>
                                )}
                                {order.payer_name && (
                                    <div className="flex items-start justify-between gap-3">
                                        <span className="text-xs text-gray-500 dark:text-gray-400">Sender Name</span>
                                        <span className="text-sm font-medium text-gray-900 dark:text-gray-100 text-right">{order.payer_name}</span>
                                    </div>
                                )}
                                {order.transaction_id && (
                                    <div className="flex items-start justify-between gap-3">
                                        <span className="text-xs text-gray-500 dark:text-gray-400 shrink-0">Transaction ID</span>
                                        <span className="text-sm font-medium text-gray-900 dark:text-gray-100 text-right break-all">{order.transaction_id}</span>
                                    </div>
                                )}
                                {order.payment_screenshot && (
                                    <div>
                                        <p className="text-xs text-gray-500 dark:text-gray-400 mb-1.5">Payment Screenshot</p>
                                        <button onClick={() => setImagePreview(order.payment_screenshot_url)}
                                            className="w-full inline-flex items-center justify-center gap-1.5 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-800 px-3 py-2 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 text-xs font-medium transition-colors">
                                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                            View Payment Screenshot
                                        </button>
                                    </div>
                                )}
                                {order.payment_proof && (
                                    <div>
                                        <p className="text-xs text-gray-500 dark:text-gray-400 mb-1.5">Payment Proof (Legacy)</p>
                                        <button onClick={() => setImagePreview(order.payment_proof_url)}
                                            className="w-full inline-flex items-center justify-center gap-1.5 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-800 px-3 py-2 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 text-xs font-medium transition-colors">
                                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                            View Payment Proof
                                        </button>
                                    </div>
                                )}
                                {(() => {
                                    const actions = renderPaymentActions();
                                    return actions ? (
                                        <div className="pt-2.5 mt-1 border-t border-gray-100 dark:border-gray-800">{actions}</div>
                                    ) : null;
                                })()}
                            </div>
                        </div>

                        {/* Delivery */}
                        <div className="order-2 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Delivery</h2>
                            <div className="space-y-1.5 text-sm">
                                <div>
                                    <span className="text-xs text-gray-500 dark:text-gray-400">Address</span>
                                    <p className="font-medium text-gray-900 dark:text-gray-100">{order.address}</p>
                                </div>
                                {(cityName || townshipName) && (
                                    <div className="flex justify-between gap-3">
                                        <span className="text-xs text-gray-500 dark:text-gray-400">City / Township</span>
                                        <span className="font-medium text-gray-900 dark:text-gray-100 text-right">{cityName}{townshipName ? `, ${townshipName}` : ''}</span>
                                    </div>
                                )}
                                {order.postal_code && (
                                    <div className="flex justify-between gap-3">
                                        <span className="text-xs text-gray-500 dark:text-gray-400">Postal Code</span>
                                        <span className="font-medium text-gray-900 dark:text-gray-100">{order.postal_code}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Order Actions */}
                    {(() => {
                        const nextButton = renderNextActionButton();
                        const canVerify = can('orders.update-status') && order.payment_status === 'pending' && order.can_verify_payment;
                        const canCancelAction = can('orders.update-status') && order.can_cancel;
                        const canDeleteAction = can('orders.update-status') && order.order_status === 'cancelled';
                        if (!nextButton && !canVerify && !canCancelAction && !canDeleteAction && !canOverrideStatus && !canOverridePayment) return null;
                        return (
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 mt-3">
                                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Order Actions</h2>
                                <div className="flex flex-wrap gap-2">
                                    {nextButton}
                                    {canVerify && (
                                        <button onClick={handleVerifyPayment}
                                            className="inline-flex items-center gap-1.5 bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 text-sm font-medium transition-colors">
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Verify Payment
                                        </button>
                                    )}
                                    {canCancelAction && (
                                        <button onClick={handleCancel}
                                            className="inline-flex items-center gap-1.5 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm font-medium transition-colors">
                                            Cancel Order
                                        </button>
                                    )}
                                    {canDeleteAction && (
                                        <button onClick={handleDelete}
                                            className="inline-flex items-center gap-1.5 bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 text-sm font-medium transition-colors">
                                            Delete Order
                                        </button>
                                    )}
                                    {canOverrideStatus && (
                                        <button onClick={() => openOverrideModal('order_status')}
                                            className="inline-flex items-center gap-1.5 bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 text-sm font-medium transition-colors">
                                            Override Order Status
                                        </button>
                                    )}
                                    {canOverridePayment && (
                                        <button onClick={() => openOverrideModal('payment_status')}
                                            className="inline-flex items-center gap-1.5 bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 text-sm font-medium transition-colors">
                                            Override Payment Status
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })()}
            </div>

            {/* Full-size Image Preview Modal */}
            {imagePreview && (
                <div className="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4" onClick={() => setImagePreview(null)}>
                    <div className="bg-white dark:bg-gray-900 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Payment Screenshot</h3>
                            <button onClick={() => setImagePreview(null)}
                                className="w-7 h-7 inline-flex items-center justify-center rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div className="p-4 flex-1 overflow-auto flex items-center justify-center bg-gray-50 dark:bg-gray-950">
                            <img src={imagePreview} alt="Payment Screenshot"
                                className="max-w-full max-h-[60vh] object-contain rounded-lg" />
                        </div>
                        <div className="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                            <button onClick={() => setImagePreview(null)}
                                className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm font-medium transition-colors">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Reject Payment Modal */}
            {rejectModalOpen && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white dark:bg-gray-900 rounded-lg shadow-lg p-6 w-96 max-w-full">
                        <h3 className="text-lg font-semibold mb-4">Reject Payment</h3>
                        <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">Provide a reason for rejecting this payment (optional):</p>
                        <textarea
                            value={rejectionReason}
                            onChange={(e) => setRejectionReason(e.target.value)}
                            rows={3}
                            placeholder="e.g. Screenshot is unclear, incorrect amount, etc."
                            className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 mb-4"
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

            {/* Override Confirmation Modal */}
            {overrideModal && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white dark:bg-gray-900 rounded-lg shadow-lg p-6 w-96 max-w-full">
                        <h3 className="text-lg font-semibold mb-4">
                            Override {overrideModal === 'order_status' ? 'Order Status' : 'Payment Status'}
                        </h3>
                        <div className="mb-4 p-3 bg-orange-50 rounded-md border border-orange-200">
                            <p className="text-sm text-orange-700">
                                Current {overrideModal === 'order_status' ? 'Order' : 'Payment'} Status: <strong>{overrideModal === 'order_status' ? order.order_status : order.payment_status}</strong>
                            </p>
                        </div>
                        <div className="mb-4">
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Status</label>
                            <select
                                value={overrideNewStatus}
                                onChange={(e) => setOverrideNewStatus(e.target.value)}
                                className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                            >
                                <option value="">Select status...</option>
                                {(overrideModal === 'order_status' ? orderStatusOptions : paymentStatusOptions)
                                    .filter(s => s !== (overrideModal === 'order_status' ? order.order_status : order.payment_status))
                                    .map(s => (
                                        <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                                    ))
                                }
                            </select>
                        </div>
                        <div className="mb-4">
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason <span className="text-red-500">*</span></label>
                            <textarea
                                value={overrideReason}
                                onChange={(e) => setOverrideReason(e.target.value)}
                                rows={3}
                                placeholder="Explain why this override is necessary..."
                                className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                            />
                        </div>
                        <div className="flex justify-end gap-3">
                            <button onClick={() => { setOverrideModal(null); setOverrideReason(''); setOverrideNewStatus(''); }}
                                className="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 text-sm">
                                Cancel
                            </button>
                            <button onClick={handleOverride}
                                disabled={!overrideNewStatus || !overrideReason.trim()}
                                className="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                Confirm Override
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
