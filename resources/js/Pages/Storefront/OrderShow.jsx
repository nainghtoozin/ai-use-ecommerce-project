import { useEffect, useMemo, useState } from 'react';
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
    partially_refunded: 'bg-purple-50 text-purple-700 border border-purple-200',
    refunded: 'bg-purple-50 text-purple-700 border border-purple-200',
};

const timelineSteps = [
    { key: 'pending', label: 'Order Placed', icon: 'M5 13l4 4L19 7' },
    { key: 'confirmed', label: 'Confirmed', icon: 'M5 13l4 4L19 7' },
    { key: 'processing', label: 'Processing', icon: 'M5 13l4 4L19 7' },
    { key: 'shipped', label: 'Shipped', icon: 'M5 13l4 4L19 7' },
    { key: 'delivered', label: 'Delivered', icon: 'M5 13l4 4L19 7' },
];

const CASE_STATUS_STYLES = {
    requested: 'bg-amber-50 text-amber-700 border-amber-200',
    approved: 'bg-blue-50 text-blue-700 border-blue-200',
    rejected: 'bg-rose-50 text-rose-700 border-rose-200',
    completed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    awaiting_shipment: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    in_transit: 'bg-sky-50 text-sky-700 border-sky-200',
    received: 'bg-teal-50 text-teal-700 border-teal-200',
    inspection: 'bg-purple-50 text-purple-700 border-purple-200',
    cancelled: 'bg-gray-100 text-gray-600 border-gray-200',
    expired: 'bg-gray-100 text-gray-600 border-gray-200',
    pending: 'bg-amber-50 text-amber-700 border-amber-200',
    processing: 'bg-blue-50 text-blue-700 border-blue-200',
    failed: 'bg-rose-50 text-rose-700 border-rose-200',
};

function getStatusIndex(status) {
    const idx = timelineSteps.findIndex(s => s.key === status);
    return idx >= 0 ? idx : -1;
}

function CaseBadge({ status }) {
    return (
        <span className={`inline-block rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ${CASE_STATUS_STYLES[status] || 'bg-gray-50 text-gray-600 border-gray-200'}`}>
            {(status || 'unknown').replace(/_/g, ' ')}
        </span>
    );
}

function formatDateTime(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function Modal({ title, onClose, children, footer, wide = false }) {
    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4">
            <div className={`w-full ${wide ? 'sm:max-w-2xl' : 'sm:max-w-md'} max-h-[92vh] overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl dark:bg-gray-900 sm:rounded-2xl`}>
                <div className="flex items-start justify-between gap-3">
                    <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">{title}</h3>
                    <button type="button" onClick={onClose} className="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Close">
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div className="mt-3">{children}</div>
                {footer && <div className="mt-4 flex justify-end gap-2">{footer}</div>}
            </div>
        </div>
    );
}

export default function OrderShow({ tenant, order, isCodOrder: isCodOrderProp = false, afterSales = {} }) {
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

    const cancellation = afterSales.cancellation || {};
    const returnInfo = afterSales.return || {};
    const cancellationCases = afterSales.cancellations || [];
    const returnCases = afterSales.returns || [];
    const refunds = afterSales.refunds || [];

    const returnItems = useMemo(() => {
        const items = returnInfo.items || {};
        return Array.isArray(items) ? items : Object.values(items);
    }, [returnInfo.items]);

    const returnSettings = returnInfo.settings || {};
    const returnReasons = returnSettings.reasons || [];
    const cancellationReasons = cancellation.reasons || [];

    const [cancelOpen, setCancelOpen] = useState(false);
    const [cancelForm, setCancelForm] = useState({ reason_code: '', note: '' });
    const [returnOpen, setReturnOpen] = useState(false);
    const [returnForm, setReturnForm] = useState({ resolution: 'refund', reason_code: '', note: '', quantities: {}, evidence: [] });
    const [submitting, setSubmitting] = useState(false);
    const [downloadingInvoice, setDownloadingInvoice] = useState(false);
    const [dismissedFlash, setDismissedFlash] = useState({});

    async function downloadInvoice() {
        if (downloadingInvoice) return;
        setDownloadingInvoice(true);
        try {
            const res = await fetch(route('storefront.customer.orders.invoice', { store_slug: storeSlug, invoice_number: order.invoice_number }));
            if (!res.ok) throw new Error('download failed');
            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `invoice-${order.invoice_number}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } catch (e) {
            alert('Unable to download invoice. Please try again.');
        } finally {
            setDownloadingInvoice(false);
        }
    }

    useEffect(() => {
        setDismissedFlash({});
    }, [order.id, flash.success, flash.error]);

    const cityLabel = order.city?.name || order.city;
    const townshipLabel = order.township?.name;
    const isCancelled = order.order_status === 'cancelled';
    const currentStepIdx = isCancelled ? -1 : getStatusIndex(order.order_status);

    const hasAfterSales = cancellationCases.length > 0 || returnCases.length > 0 || refunds.length > 0;

    const resolutionOptions = [
        returnSettings.allow_refund !== false && { value: 'refund', label: 'Refund' },
        returnSettings.allow_exchange && { value: 'exchange', label: 'Exchange' },
        returnSettings.allow_replacement && { value: 'replacement', label: 'Replacement' },
    ].filter(Boolean);

    const estimatedRefund = useMemo(() => {
        const lines = returnItems.filter((item) => Number(returnForm.quantities[item.order_item_id] || 0) > 0);
        const goods = lines.reduce((sum, item) => sum + Number(item.price || 0) * Number(returnForm.quantities[item.order_item_id] || 0), 0);
        const shipping = returnSettings.refund_shipping_fee ? Number(order.delivery_fee || 0) : 0;
        return goods + shipping;
    }, [returnForm.quantities, returnItems, returnSettings.refund_shipping_fee, order.delivery_fee]);

    function openReturn() {
        const quantities = {};
        returnItems.forEach((item) => { if (item.available > 0) quantities[item.order_item_id] = item.available; });
        setReturnForm({
            resolution: resolutionOptions[0]?.value || 'refund',
            reason_code: '',
            note: '',
            quantities,
            evidence: [],
        });
        setReturnOpen(true);
    }

    function submitCancellation(event) {
        event.preventDefault();
        setSubmitting(true);
        const reason = cancellationReasons.find((item) => item.code === cancelForm.reason_code);
        router.post(route('storefront.customer.orders.cancel', { store_slug: storeSlug, order: order.id }), {
            reason_code: cancelForm.reason_code || null,
            reason_label: reason?.label || null,
            note: cancelForm.note || null,
        }, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: () => setCancelOpen(false),
        });
    }

    function submitReturn(event) {
        event.preventDefault();
        setSubmitting(true);
        const reason = returnReasons.find((item) => item.code === returnForm.reason_code);
        const items = Object.entries(returnForm.quantities)
            .filter(([, quantity]) => Number(quantity) > 0)
            .map(([orderItemId, quantity]) => ({ order_item_id: Number(orderItemId), quantity: Number(quantity) }));

        router.post(route('storefront.customer.orders.returns.store', { store_slug: storeSlug, order: order.id }), {
            resolution: returnForm.resolution,
            reason_code: returnForm.reason_code || null,
            reason_label: reason?.label || null,
            note: returnForm.note || null,
            items,
            evidence: returnForm.evidence,
        }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: () => setReturnOpen(false),
        });
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

            <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4 pb-24 md:pb-4">
                {flash.success && !dismissedFlash.success && (
                    <div className="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-2.5 rounded-lg text-sm font-medium flex items-center justify-between gap-2">
                        <span>{flash.success}</span>
                        <button type="button" onClick={() => setDismissedFlash((p) => ({ ...p, success: true }))} aria-label="Dismiss notification" className="text-emerald-700 hover:text-emerald-900 p-1">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                )}
                {flash.error && !dismissedFlash.error && (
                    <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-3 py-2.5 rounded-lg text-sm font-medium flex items-center justify-between gap-2">
                        <span>{flash.error}</span>
                        <button type="button" onClick={() => setDismissedFlash((p) => ({ ...p, error: true }))} aria-label="Dismiss notification" className="text-red-700 hover:text-red-900 p-1">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                )}

                {/* Mobile header */}
                <div className="md:hidden mb-3">
                    <div className="flex items-center gap-3 mb-2">
                        <Link href={route('storefront.customer.orders', { store_slug: storeSlug })} className="inline-flex items-center justify-center w-9 h-9 shrink-0 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl hover:bg-gray-50 transition-colors" aria-label="Back to My Orders">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        </Link>
                        <div className="min-w-0 flex-1">
                            <h1 className="text-base font-bold text-gray-900 dark:text-gray-100 font-mono truncate">Order #{order.invoice_number}</h1>
                            <p className="text-[11px] text-gray-500 dark:text-gray-400">{new Date(order.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                        </div>
                        <span className={`inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0 ${orderStatusColors[order.order_status] || 'bg-gray-50 text-gray-600 border border-gray-200'}`}>
                            {order.order_status}
                        </span>
                    </div>
                    {/* Compact action bar */}
                    <div className="flex gap-2">
                        {(returnInfo.allowed || cancellation.redirect_to_returns) && (
                            <Link href={route('storefront.customer.returns.index', { store_slug: storeSlug })} className="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded-xl hover:bg-blue-100 transition-colors" aria-label="My Returns">
                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>
                                Returns
                            </Link>
                        )}
                        <button type="button" onClick={downloadInvoice} disabled={downloadingInvoice} className="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl hover:bg-emerald-100 transition-colors disabled:opacity-50" aria-label="Download Invoice">
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                            {downloadingInvoice ? 'Preparing…' : 'Invoice'}
                        </button>
                        {cancellation.allowed && (
                            <button type="button" onClick={() => setCancelOpen(true)} className="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded-xl hover:bg-rose-100 transition-colors" aria-label="Cancel order">
                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                                Cancel
                            </button>
                        )}
                    </div>
                </div>

                {/* Desktop header */}
                <div className="hidden md:flex flex-wrap items-center justify-between gap-3 mb-4">
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
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href={route('storefront.customer.returns.index', { store_slug: storeSlug })} className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg hover:bg-gray-50 transition-colors">
                            My returns
                        </Link>
                        <button type="button" onClick={downloadInvoice} disabled={downloadingInvoice} className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50">
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                            {downloadingInvoice ? 'Preparing…' : 'Download Invoice'}
                        </button>
                    </div>
                </div>

                {/* Compact progress timeline */}
                {!isCancelled && (
                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 px-4 py-3 mb-4">
                        <div className="flex items-center justify-between">
                            {timelineSteps.map((step, idx) => {
                                const isComplete = idx <= currentStepIdx;
                                const isCurrent = idx === currentStepIdx;
                                return (
                                    <div key={step.key} className="flex-1 flex flex-col items-center relative">
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



                <div className="space-y-3">
                    {/* Items */}
                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                        <div className="px-4 pt-3 pb-1 flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Items</h2>
                            <span className="text-xs text-gray-500 dark:text-gray-400">{order.items?.length || 0} product{(order.items?.length || 0) === 1 ? '' : 's'}</span>
                        </div>
                        {/* Mobile item cards */}
                        <div className="md:hidden px-4 py-2 space-y-2">
                            {order.items?.length ? order.items.map((item) => {
                                const p = itemPricing(item);
                                return (
                                    <div key={item.id} className="flex items-center gap-3 py-2 border-b border-gray-100 dark:border-gray-800 last:border-0">
                                        {itemImage(item) ? (
                                            <img src={itemImage(item)} alt="" className="w-10 h-10 rounded-lg object-cover border border-gray-200 dark:border-gray-800 shrink-0" />
                                        ) : (
                                            <div className="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center shrink-0">
                                                <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                            </div>
                                        )}
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{item.product?.name || `Product #${item.product_id}`}</p>
                                            {itemVariant(item) && <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{itemVariant(item)}</p>}
                                        </div>
                                        <div className="text-right shrink-0">
                                            <p className="text-xs text-gray-500 dark:text-gray-400">{item.quantity} × {formatCurrency(p.price, cc)}</p>
                                            <p className="text-sm font-bold text-gray-900 dark:text-gray-100">{formatCurrency(p.price * item.quantity, cc)}</p>
                                        </div>
                                    </div>
                                );
                            }) : (
                                <p className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">No items found.</p>
                            )}
                        </div>
                        {/* Desktop item table */}
                        <div className="hidden md:block overflow-x-auto">
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

                    {/* After-sales actions */}
                    {(cancellation.allowed || returnInfo.allowed || cancellation.redirect_to_returns || cancellation.blocked_reason) && (
                        <div className="hidden md:block bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm p-4">
                            <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                {cancellation.allowed && (
                                    <button type="button" onClick={() => setCancelOpen(true)} className="w-full rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                                        Cancel order
                                    </button>
                                )}
                                {returnInfo.allowed && (
                                    <button type="button" onClick={openReturn} className="w-full rounded-lg border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700 hover:bg-blue-100">
                                        Request a return
                                    </button>
                                )}
                            </div>
                            {cancellation.blocked_reason && (
                                <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">{cancellation.blocked_reason}</p>
                            )}
                            {!returnInfo.allowed && returnInfo.reason && cancellation.redirect_to_returns && (
                                <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">{returnInfo.reason}</p>
                            )}
                        </div>
                    )}

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
                    {/* Mobile collapsible info sections */}
                    <div className="md:hidden space-y-2">
                        <details className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
                            <summary className="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center justify-between">
                                Payment
                                <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
                            </summary>
                            <div className="px-4 pb-3 space-y-2 text-sm">
                                {isCodOrder ? (
                                    <>
                                        <div className="flex justify-between"><span className="text-gray-500">Method</span><span className="font-semibold">{codPaymentMethodLabel(order)}</span></div>
                                        <div className="flex justify-between"><span className="text-gray-500">Payment</span><span>{codUnpaid ? 'Due on Delivery' : 'Paid'}</span></div>
                                        <div className="flex justify-between"><span className="text-gray-500">Amount {codUnpaid ? 'Due' : ''}</span><span className="font-bold">{formatCurrency(order.total_amount, cc)}</span></div>
                                    </>
                                ) : (
                                    <>
                                        <div className="flex justify-between"><span className="text-gray-500">Method</span><span className="font-semibold text-right">{order.payment_method?.name || order.paymentMethod?.name || 'N/A'}</span></div>
                                        {order.paid_amount && <div className="flex justify-between"><span className="text-gray-500">Paid Amount</span><span className="font-bold text-emerald-600">{formatCurrency(order.paid_amount, cc)}</span></div>}
                                        {order.transaction_id && <div className="flex justify-between gap-2"><span className="text-gray-500">Transaction ID</span><span className="font-mono text-right break-all">{order.transaction_id}</span></div>}
                                    </>
                                )}
                            </div>
                        </details>
                        <details className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
                            <summary className="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center justify-between">
                                Delivery
                                <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
                            </summary>
                            <div className="px-4 pb-3 text-sm space-y-1">
                                <p className="font-semibold">{order.first_name} {order.last_name} · {order.phone}</p>
                                <p className="text-gray-700 dark:text-gray-300">{order.address}</p>
                                {(cityLabel || townshipLabel || order.postal_code) && (
                                    <p className="text-gray-700 dark:text-gray-300">{[cityLabel, townshipLabel].filter(Boolean).join(', ')}{order.postal_code ? ` · ${order.postal_code}` : ''}</p>
                                )}
                            </div>
                        </details>
                        <details className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm" open>
                            <summary className="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center justify-between">
                                Order Summary
                                <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
                            </summary>
                            <div className="px-4 pb-3 space-y-1 text-sm">
                                <div className="flex justify-between"><span className="text-gray-500">Subtotal</span><span className="font-medium">{formatCurrency(order.subtotal || order.items_total, cc)}</span></div>
                                {order.discount_amount > 0 && <div className="flex justify-between text-emerald-600"><span>Coupon Discount</span><span>-{formatCurrency(order.discount_amount, cc)}</span></div>}
                                {order.delivery_fee > 0 && <div className="flex justify-between"><span className="text-gray-500">Delivery Fee</span><span className="font-medium">{formatCurrency(order.delivery_fee, cc)}</span></div>}
                                {order.packaging_id && order.packaging_fee > 0 && <div className="flex justify-between"><span className="text-gray-500">Packaging</span><span className="font-medium">{formatCurrency(order.packaging_fee, cc)}</span></div>}
                                <div className="flex justify-between pt-1 border-t border-gray-200 dark:border-gray-700 font-bold"><span>Total</span><span className="text-lg">{formatCurrency(order.total_amount, cc)}</span></div>
                            </div>
                        </details>
                    </div>
                    {/* Desktop info grid */}
                    <div className="hidden md:grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
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
                                    {order.paid_amount && <div className="flex items-start justify-between gap-3"><p className="text-xs text-gray-500 dark:text-gray-400">Paid Amount</p><p className="text-sm font-bold text-emerald-600 dark:text-emerald-400 text-right">{formatCurrency(order.paid_amount, cc)}</p></div>}
                                    {(order.payer_name || order.sender_account_number || order.transaction_id) && (
                                        <details className="mt-2">
                                            <summary className="cursor-pointer text-xs font-medium text-blue-600 dark:text-blue-400">Payment transaction details</summary>
                                            <div className="mt-2 space-y-2">
                                                {order.payer_name && <div className="flex items-start justify-between gap-3"><p className="text-xs text-gray-500 dark:text-gray-400">Sender Name</p><p className="text-sm font-medium text-gray-900 dark:text-gray-100 text-right">{order.payer_name}</p></div>}
                                                {order.sender_account_number && <div className="flex items-start justify-between gap-3"><p className="text-xs text-gray-500 dark:text-gray-400">Sender Account</p><p className="text-sm font-medium text-gray-900 dark:text-gray-100 text-right font-mono break-all">{order.sender_account_number}</p></div>}
                                                {order.transaction_id && <div className="flex items-start justify-between gap-3"><p className="text-xs text-gray-500 dark:text-gray-400">Transaction ID</p><p className="text-sm font-medium text-gray-900 dark:text-gray-100 text-right font-mono break-all">{order.transaction_id}</p></div>}
                                            </div>
                                        </details>
                                    )}
                                </div>
                            )}
                        </div>

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

                    {/* After-sales history */}
                    {hasAfterSales && (
                        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">After-sales</h2>

                            {cancellationCases.length > 0 && (
                                <div className="mt-3">
                                    <h3 className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cancellations</h3>
                                    <ul className="mt-2 space-y-2">
                                        {cancellationCases.map((item) => (
                                            <li key={item.id} className="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-mono text-[11px] text-gray-500">{item.number}</span>
                                                    <CaseBadge status={item.status} />
                                                    <span className="text-[11px] uppercase tracking-wide text-gray-400">{item.mode?.replace(/_/g, ' ')}</span>
                                                </div>
                                                <p className="mt-1 text-sm text-gray-800 dark:text-gray-200">{item.reason || 'No reason provided'}</p>
                                                {item.note && <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{item.note}</p>}
                                                {item.rejection_reason && <p className="mt-1 text-xs text-rose-600">Declined: {item.rejection_reason}</p>}
                                                {item.refund && (
                                                    <p className="mt-1 text-xs text-purple-600 dark:text-purple-300">
                                                        Refund {item.refund.number} · {formatCurrency(item.refund.amount, cc)} ({item.refund.status})
                                                    </p>
                                                )}
                                                <p className="mt-1 text-[11px] text-gray-400">Requested {formatDateTime(item.requested_at)}</p>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {returnCases.length > 0 && (
                                <div className="mt-4">
                                    <h3 className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Returns</h3>
                                    <ul className="mt-2 space-y-2">
                                        {returnCases.map((item) => (
                                            <li key={item.id} className="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Link href={route('storefront.customer.returns.show', { store_slug: storeSlug, return: item.id })} className="font-mono text-[11px] text-blue-600 hover:underline dark:text-blue-400">
                                                        {item.number}
                                                    </Link>
                                                    <CaseBadge status={item.status} />
                                                    <span className="text-[11px] uppercase tracking-wide text-gray-400">{item.resolution}</span>
                                                </div>
                                                <p className="mt-1 text-sm text-gray-800 dark:text-gray-200">{item.reason || 'No reason provided'}</p>
                                                {item.rejection_reason && <p className="mt-1 text-xs text-rose-600">Declined: {item.rejection_reason}</p>}
                                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    {(item.items || []).length} line(s) · return shipping paid by {item.shipping_responsibility}
                                                </p>
                                                {item.refund && (
                                                    <p className="mt-1 text-xs text-purple-600 dark:text-purple-300">
                                                        Refund {item.refund.number} · {formatCurrency(item.refund.amount, cc)} ({item.refund.status})
                                                    </p>
                                                )}
                                                <p className="mt-1 text-[11px] text-gray-400">Requested {formatDateTime(item.requested_at)}</p>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {refunds.length > 0 && (
                                <div className="mt-4">
                                    <h3 className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Refunds</h3>
                                    <ul className="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
                                        {refunds.map((refund) => (
                                            <li key={refund.number} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                                <div>
                                                    <p className="font-mono text-[11px] text-gray-500">{refund.number}</p>
                                                    <p className="text-xs uppercase text-gray-500 dark:text-gray-400">{refund.method}</p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{formatCurrency(refund.amount, cc)}</p>
                                                    <p className="text-[11px] text-gray-500">{refund.status}{refund.processed_at ? ` · ${formatDateTime(refund.processed_at)}` : ''}</p>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {cancelOpen && (
                <Modal
                    title="Cancel this order"
                    onClose={() => setCancelOpen(false)}
                    footer={
                        <>
                            <button type="button" onClick={() => setCancelOpen(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">Keep order</button>
                            <button
                                type="button"
                                onClick={submitCancellation}
                                disabled={submitting || (cancellation.require_reason && !cancelForm.reason_code) || (cancellation.require_note && !cancelForm.note.trim())}
                                className="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-60"
                            >
                                {submitting ? 'Submitting…' : cancellation.mode === 'instant' ? 'Cancel order' : 'Submit request'}
                            </button>
                        </>
                    }
                >
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        {cancellation.mode === 'instant'
                            ? 'Your order will be cancelled immediately. Any eligible paid amount becomes a refund that the store will process manually.'
                            : 'Your request will be reviewed by the store before the order is cancelled.'}
                    </p>
                    <label className="mt-4 block text-xs font-medium text-gray-600 dark:text-gray-300">Reason{cancellation.require_reason ? ' (required)' : ''}</label>
                    <select
                        value={cancelForm.reason_code}
                        onChange={(event) => setCancelForm({ ...cancelForm, reason_code: event.target.value })}
                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <option value="">Select a reason</option>
                        {cancellationReasons.map((reason) => (
                            <option key={reason.code} value={reason.code}>{reason.label}</option>
                        ))}
                    </select>
                    <label className="mt-3 block text-xs font-medium text-gray-600 dark:text-gray-300">Additional details{cancellation.require_note ? ' (required)' : ''}</label>
                    <textarea
                        rows={3}
                        value={cancelForm.note}
                        onChange={(event) => setCancelForm({ ...cancelForm, note: event.target.value })}
                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                    />
                </Modal>
            )}

            {returnOpen && (
                <Modal
                    title="Request a return"
                    onClose={() => setReturnOpen(false)}
                    wide
                    footer={
                        <>
                            <button type="button" onClick={() => setReturnOpen(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">Close</button>
                            <button
                                type="button"
                                onClick={submitReturn}
                                disabled={submitting}
                                className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                            >
                                {submitting ? 'Submitting…' : 'Submit return request'}
                            </button>
                        </>
                    }
                >
                    <div className="space-y-3">
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Return window: {returnSettings.return_window_days} day(s) after delivery
                            {typeof returnInfo.days_remaining === 'number' ? ` — ${Math.max(0, returnInfo.days_remaining)} day(s) left` : ''}.
                            {' '}Return shipping is paid by the {returnSettings.return_shipping_responsibility === 'merchant' ? 'store' : 'customer'}.
                        </p>

                        <div>
                            <p className="text-xs font-medium text-gray-600 dark:text-gray-300">Items to return</p>
                            <div className="mt-1 space-y-2">
                                {returnItems.map((item) => (
                                    <div key={item.order_item_id} className={`flex items-center justify-between gap-3 rounded-lg border px-3 py-2 dark:border-gray-800 ${item.available > 0 ? 'border-gray-200' : 'border-gray-100 opacity-60'}`}>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-gray-800 dark:text-gray-200">{item.name}</p>
                                            <p className="text-[11px] text-gray-500 dark:text-gray-400">
                                                {item.variant ? `${item.variant} · ` : ''}Purchased {item.quantity} · Available {item.available}
                                            </p>
                                        </div>
                                        <input
                                            type="number"
                                            min={0}
                                            max={item.available}
                                            disabled={item.available <= 0}
                                            value={returnForm.quantities[item.order_item_id] ?? 0}
                                            onChange={(event) => setReturnForm((current) => ({
                                                ...current,
                                                quantities: { ...current.quantities, [item.order_item_id]: Math.min(item.available, Math.max(0, Number(event.target.value))) },
                                            }))}
                                            className="w-16 rounded-lg border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>

                        {resolutionOptions.length > 1 && (
                            <div>
                                <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Resolution</label>
                                <select value={returnForm.resolution} onChange={(event) => setReturnForm({ ...returnForm, resolution: event.target.value })} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800">
                                    {resolutionOptions.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                            </div>
                        )}

                        <div>
                            <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Reason{returnSettings.require_reason ? ' (required)' : ''}</label>
                            <select value={returnForm.reason_code} onChange={(event) => setReturnForm({ ...returnForm, reason_code: event.target.value })} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800">
                                <option value="">Select a reason</option>
                                {returnReasons.map((reason) => (
                                    <option key={reason.code} value={reason.code}>{reason.label}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Additional details{returnSettings.require_note ? ' (required)' : ''}</label>
                            <textarea rows={3} value={returnForm.note} onChange={(event) => setReturnForm({ ...returnForm, note: event.target.value })} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800" />
                        </div>

                        <div>
                            <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Evidence photos{returnSettings.require_evidence ? ' (required)' : ' (optional)'}</label>
                            <input
                                type="file"
                                accept="image/*"
                                multiple
                                onChange={(event) => setReturnForm({ ...returnForm, evidence: Array.from(event.target.files || []) })}
                                className="mt-1 w-full text-xs text-gray-500 dark:text-gray-400"
                            />
                        </div>

                        {returnSettings.instructions && (
                            <p className="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">{returnSettings.instructions}</p>
                        )}

                        {returnSettings.policy_text && (
                            <p className="text-xs text-gray-500 dark:text-gray-400">{returnSettings.policy_text}</p>
                        )}

                        {returnForm.resolution === 'refund' && estimatedRefund > 0 && (
                            <p className="rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                Estimated refundable amount: <strong>{formatCurrency(estimatedRefund, cc)}</strong> (subject to inspection)
                            </p>
                        )}
                    </div>
                </Modal>
            )}
        </ShopLayout>
    );
}
