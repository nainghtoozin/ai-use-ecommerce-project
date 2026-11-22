import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';

const STEPS = [
    { key: 'requested', label: 'Requested' },
    { key: 'approved', label: 'Approved' },
    { key: 'awaiting_shipment', label: 'Ship back' },
    { key: 'in_transit', label: 'In transit' },
    { key: 'received', label: 'Received' },
    { key: 'inspection', label: 'Inspection' },
    { key: 'completed', label: 'Completed' },
];

const OPEN_STATUSES = ['requested', 'approved', 'awaiting_shipment', 'in_transit', 'received', 'inspection'];

const STATUS_STYLES = {
    requested: 'bg-amber-50 text-amber-700 border-amber-200',
    approved: 'bg-blue-50 text-blue-700 border-blue-200',
    rejected: 'bg-rose-50 text-rose-700 border-rose-200',
    awaiting_shipment: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    in_transit: 'bg-sky-50 text-sky-700 border-sky-200',
    received: 'bg-teal-50 text-teal-700 border-teal-200',
    inspection: 'bg-purple-50 text-purple-700 border-purple-200',
    completed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    cancelled: 'bg-gray-100 text-gray-600 border-gray-200',
    expired: 'bg-gray-100 text-gray-600 border-gray-200',
};

function formatDateTime(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export default function StorefrontReturnShow({ tenant, returnRequest, timeline = [] }) {
    const { props } = usePage();
    const cc = getCurrencyConfig(props.platform_setting, props.website_info);
    const [submitting, setSubmitting] = useState(false);

    const tracking = returnRequest.tracking || {};
    const hasTracking = Boolean(
        tracking.carrier_name || tracking.tracking_number || tracking.tracking_url || tracking.shipped_at || tracking.tracking_note
    );

    const status = returnRequest.status;
    const currentIndex = STEPS.findIndex((step) => step.key === status);
    const isTerminal = ['rejected', 'cancelled', 'expired'].includes(status);
    const canCancel = OPEN_STATUSES.includes(status);

    function cancelReturn() {
        if (!confirm('Cancel this return request?')) return;
        setSubmitting(true);
        router.post(route('storefront.customer.returns.cancel', { store_slug: tenant.slug, return: returnRequest.id }), {}, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
        });
    }

    return (
        <ShopLayout>
            <Head title={`Return ${returnRequest.number} - ${tenant.name}`} />
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Link href={route('storefront.customer.returns.index', { store_slug: tenant.slug })} className="text-xs font-medium text-gray-500 hover:text-gray-800 dark:text-gray-400">
                                My returns
                            </Link>
                            <span className="text-gray-300">/</span>
                            <h1 className="font-mono text-lg font-bold text-gray-900 dark:text-gray-100">{returnRequest.number}</h1>
                            <span className={`inline-block rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ${STATUS_STYLES[status] || 'border-gray-200 bg-gray-50 text-gray-600'}`}>
                                {String(status).replace(/_/g, ' ')}
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {returnRequest.order ? `Order #${returnRequest.order.invoice_number} · ` : ''}
                            Requested {formatDateTime(returnRequest.requested_at)}
                        </p>
                    </div>
                    {returnRequest.order && (
                        <Link
                            href={route('storefront.customer.orders.show', { store_slug: tenant.slug, order: returnRequest.order.id })}
                            className="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200"
                        >
                            View order
                        </Link>
                    )}
                </div>

                {!isTerminal && (
                    <div className="mt-5 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div className="flex items-center justify-between">
                            {STEPS.map((step, index) => {
                                const complete = index <= currentIndex;
                                return (
                                    <div key={step.key} className="relative flex flex-1 flex-col items-center">
                                        {index > 0 && (
                                            <div className={`absolute right-1/2 top-3 h-0.5 w-full -translate-y-1/2 ${index <= currentIndex ? 'bg-blue-400' : 'bg-gray-200 dark:bg-gray-700'}`} />
                                        )}
                                        <div className={`relative z-10 flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-bold ${complete ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400'}`}>
                                            {index + 1}
                                        </div>
                                        <p className={`mt-1 text-center text-[10px] font-medium leading-tight ${complete ? 'text-blue-700 dark:text-blue-400' : 'text-gray-400 dark:text-gray-500'}`}>
                                            {step.label}
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {isTerminal && (
                    <div className={`mt-5 rounded-xl border px-4 py-3 text-sm font-medium ${
                        status === 'rejected' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-gray-200 bg-gray-50 text-gray-600'
                    }`}>
                        This return request is {status}.
                        {returnRequest.rejection_reason ? ` Reason: ${returnRequest.rejection_reason}` : ''}
                    </div>
                )}

                <div className="mt-5 grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Returned items</h2>
                            <ul className="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                                {(returnRequest.items || []).map((item) => (
                                    <li key={item.order_item_id} className="flex items-center justify-between gap-3 py-2">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-gray-800 dark:text-gray-200">{item.product_name || `Item #${item.order_item_id}`}</p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">Quantity {item.quantity}</p>
                                        </div>
                                        <div className="text-right text-xs">
                                            <p className="capitalize text-gray-600 dark:text-gray-300">{String(item.condition).replace(/_/g, ' ')}</p>
                                            {item.received_quantity > 0 && <p className="text-gray-400">{item.received_quantity} received</p>}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Progress updates</h2>
                            <ol className="mt-3 space-y-3">
                                {timeline.length === 0 && <li className="text-sm text-gray-500 dark:text-gray-400">No updates yet.</li>}
                                {timeline.map((entry) => (
                                    <li key={entry.id} className="border-l border-gray-200 pl-4 dark:border-gray-800">
                                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-300">
                                            {String(entry.action || '').replace(/_/g, ' ')}
                                        </p>
                                        <p className="text-[11px] text-gray-400">{formatDateTime(entry.created_at)}</p>
                                        {entry.note && <p className="mt-1 text-xs text-gray-600 dark:text-gray-300">{entry.note}</p>}
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Details</h2>
                            <dl className="mt-3 space-y-2 text-xs">
                                <div className="flex justify-between gap-3">
                                    <dt className="text-gray-500 dark:text-gray-400">Resolution</dt>
                                    <dd className="font-semibold uppercase text-gray-800 dark:text-gray-200">{returnRequest.resolution}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-gray-500 dark:text-gray-400">Reason</dt>
                                    <dd className="text-right text-gray-800 dark:text-gray-200">{returnRequest.reason || '—'}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-gray-500 dark:text-gray-400">Return shipping</dt>
                                    <dd className="uppercase text-gray-800 dark:text-gray-200">{returnRequest.shipping_responsibility}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-gray-500 dark:text-gray-400">Refund</dt>
                                    <dd className="text-right text-gray-800 dark:text-gray-200">
                                        {returnRequest.refund ? `${formatCurrency(returnRequest.refund.amount, cc)} · ${returnRequest.refund.status}` : '—'}
                                    </dd>
                                </div>
                            </dl>
                            {returnRequest.note && <p className="mt-3 text-xs text-gray-600 dark:text-gray-300">{returnRequest.note}</p>}
                        </div>

                        {hasTracking && (
                            <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Shipment tracking</h2>
                                <dl className="mt-3 space-y-2 text-xs">
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-gray-500 dark:text-gray-400">Carrier</dt>
                                        <dd className="text-right text-gray-800 dark:text-gray-200">{tracking.carrier_name || '—'}</dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-gray-500 dark:text-gray-400">Tracking no.</dt>
                                        <dd className="break-all text-right font-mono text-gray-800 dark:text-gray-200">{tracking.tracking_number || '—'}</dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-gray-500 dark:text-gray-400">Shipped</dt>
                                        <dd className="text-gray-800 dark:text-gray-200">{tracking.shipped_at ? formatDateTime(tracking.shipped_at) : '—'}</dd>
                                    </div>
                                    {tracking.tracking_url && (
                                        <div className="flex justify-between gap-3">
                                            <dt className="text-gray-500 dark:text-gray-400">Track parcel</dt>
                                            <dd className="text-right">
                                                <a href={tracking.tracking_url} target="_blank" rel="noopener noreferrer" className="font-semibold text-blue-600 hover:underline dark:text-blue-400">
                                                    Open tracking link
                                                </a>
                                            </dd>
                                        </div>
                                    )}
                                </dl>
                                {tracking.tracking_note && (
                                    <p className="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                        {tracking.tracking_note}
                                    </p>
                                )}
                            </div>
                        )}

                        {(returnRequest.evidence || []).length > 0 && (
                            <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Evidence</h2>
                                <div className="mt-2 grid grid-cols-2 gap-2">
                                    {returnRequest.evidence.map((url, index) => (
                                        <a key={`${url}-${index}`} href={url} target="_blank" rel="noreferrer" className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
                                            <img src={url} alt={`Evidence ${index + 1}`} className="h-20 w-full object-cover" />
                                        </a>
                                    ))}
                                </div>
                            </div>
                        )}

                        {canCancel && (
                            <button
                                type="button"
                                onClick={cancelReturn}
                                disabled={submitting}
                                className="w-full rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-100 disabled:opacity-60"
                            >
                                {submitting ? 'Cancelling…' : 'Cancel return request'}
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
