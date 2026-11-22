import { useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import {
    BackLink,
    Card,
    StatusBadge,
    formatDate,
} from '@/Components/Admin/AfterSalesUI';

const OPEN_STATUSES = ['requested', 'approved', 'awaiting_shipment', 'in_transit', 'received', 'inspection'];
const TRACKING_STATUSES = ['approved', 'awaiting_shipment', 'in_transit', 'received', 'inspection'];

function toDateTimeInput(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export default function AdminReturnsShow({ returnRequest, tracking = {}, timeline = [], permissions = {} }) {
    const { platform_setting, website_info } = usePage().props;
    const cc = getCurrencyConfig(platform_setting, website_info);

    const [action, setAction] = useState(null);
    const [note, setNote] = useState('');
    const [method, setMethod] = useState('manual');
    const [trackingForm, setTrackingForm] = useState(() => ({
        carrier_name: tracking.carrier_name || '',
        tracking_number: tracking.tracking_number || '',
        tracking_url: tracking.tracking_url || '',
        tracking_note: tracking.tracking_note || '',
        shipped_at: toDateTimeInput(tracking.shipped_at),
    }));
    const [received, setReceived] = useState(() => {
        const map = {};
        (returnRequest.items || []).forEach((item) => { map[item.id] = item.received_quantity || item.quantity; });
        return map;
    });
    const [inspection, setInspection] = useState(() => {
        const map = {};
        (returnRequest.items || []).forEach((item) => {
            map[item.id] = { condition: item.condition === 'pending' ? 'resellable' : item.condition, restock: item.restock ?? true, note: item.inspection_note || '' };
        });
        return map;
    });
    const [submitting, setSubmitting] = useState(false);

    const order = returnRequest.order || {};
    const items = returnRequest.items || [];
    const evidence = returnRequest.evidence_urls || [];
    const status = returnRequest.status;

    const canReceive = ['approved', 'awaiting_shipment', 'in_transit', 'received'].includes(status);
    const canInspect = ['received', 'inspection'].includes(status);
    const canComplete = ['received', 'inspection'].includes(status);
    const canMarkInTransit = status === 'awaiting_shipment';

    const totalUnits = useMemo(
        () => items.reduce((sum, item) => sum + Number(item.quantity || 0), 0),
        [items]
    );

    const money = (value) => formatCurrency(value, cc);

    function post(path, payload, success) {
        setSubmitting(true);
        router.post(adminUrl(path), payload, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: () => {
                setAction(null);
                setNote('');
            },
        });
    }

    function submitApprove() {
        post(`/admin/returns/${returnRequest.id}/approve`, { note }, 'approve');
    }

    function submitReject() {
        post(`/admin/returns/${returnRequest.id}/reject`, { reason: note }, 'reject');
    }

    function submitReceive() {
        const payload = Object.entries(received).map(([id, quantity]) => ({ id: Number(id), received_quantity: Number(quantity) }));
        post(`/admin/returns/${returnRequest.id}/receive`, { items: payload }, 'receive');
    }

    function submitMarkInTransit() {
        post(`/admin/returns/${returnRequest.id}/in-transit`, { ...trackingForm }, 'in_transit');
    }

    function submitUpdateTracking() {
        post(`/admin/returns/${returnRequest.id}/tracking`, { ...trackingForm }, 'tracking');
    }

    function submitInspect() {
        const payload = Object.entries(inspection).map(([id, value]) => ({
            id: Number(id),
            condition: value.condition,
            restock: value.condition === 'resellable' ? Boolean(value.restock) : false,
            note: value.note || null,
        }));
        post(`/admin/returns/${returnRequest.id}/inspect`, { items: payload }, 'inspect');
    }

    function submitComplete() {
        post(`/admin/returns/${returnRequest.id}/complete`, { merchant_note: note, method }, 'complete');
    }

    function submitCancel() {
        post(`/admin/returns/${returnRequest.id}/cancel`, {}, 'cancel');
    }

    return (
        <AdminLayout>
            <Head title={`Return ${returnRequest.rma_number}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <BackLink href={adminUrl('/admin/returns')} label="Returns" />
                            <span className="text-gray-300">/</span>
                            <h1 className="font-mono text-lg font-bold text-gray-900 dark:text-gray-100">{returnRequest.rma_number}</h1>
                            <StatusBadge status={status} />
                        </div>
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Order #{order.invoice_number || returnRequest.order_id} · {order.first_name} {order.last_name} · {order.phone}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href={adminUrl(`/admin/orders/${returnRequest.order_id}`)} className="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">
                            View order
                        </Link>
                        {permissions.update_tracking && TRACKING_STATUSES.includes(status) && status !== 'awaiting_shipment' && (
                            <button type="button" onClick={() => setAction('tracking')} className="rounded-lg border border-blue-300 px-3 py-2 text-xs font-semibold text-blue-700 dark:border-blue-800 dark:text-blue-300">
                                Update tracking
                            </button>
                        )}
                        {OPEN_STATUSES.includes(status) && permissions.approve && (
                            <button type="button" onClick={() => setAction('cancel')} className="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600">
                                Cancel return
                            </button>
                        )}
                    </div>
                </div>

                {permissions.approve && status === 'requested' && (
                    <div className="mt-4 flex flex-wrap gap-2">
                        <button type="button" onClick={() => setAction('approve')} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                            Approve return
                        </button>
                        {permissions.reject && (
                            <button type="button" onClick={() => setAction('reject')} className="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600">
                                Reject return
                            </button>
                        )}
                    </div>
                )}

                {canReceive && permissions.receive && status !== 'received' && (
                    <div className="mt-4 flex flex-wrap gap-2">
                        <button type="button" onClick={() => setAction('receive')} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Record received items
                        </button>
                        {canMarkInTransit && permissions.in_transit && (
                            <button type="button" onClick={() => setAction('in_transit')} className="rounded-lg border border-blue-300 px-4 py-2 text-sm font-semibold text-blue-700 dark:border-blue-800 dark:text-blue-300">
                                Mark in transit
                            </button>
                        )}
                    </div>
                )}

                {canInspect && permissions.inspect && status !== 'inspection' && (
                    <div className="mt-4">
                        <button type="button" onClick={() => setAction('inspect')} className="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700">
                            Start inspection
                        </button>
                    </div>
                )}

                {canComplete && (
                    <div className="mt-2">
                        <button type="button" onClick={() => setAction('complete')} className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                            Complete return
                        </button>
                    </div>
                )}

                <div className="mt-5 grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <Card>
                            <div className="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Returned items</h2>
                                <p className="text-xs text-gray-500 dark:text-gray-400">{items.length} line(s) · {totalUnits} unit(s)</p>
                            </div>
                            <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                                {items.map((item) => (
                                    <li key={item.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {item.order_item?.product?.name || `Item #${item.order_item_id}`}
                                            </p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {item.order_item?.variant?.label ? `${item.order_item.variant.label} · ` : ''}
                                                Qty {item.quantity} requested · {item.received_quantity || 0} received
                                            </p>
                                            {item.inspection_note && (
                                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Note: {item.inspection_note}</p>
                                            )}
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2 text-xs">
                                            <StatusBadge status={item.condition === 'pending' ? 'pending' : item.condition} />
                                            <span className={`rounded-full border px-2 py-0.5 text-[11px] font-semibold ${item.restock ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-gray-200 bg-gray-50 text-gray-600'}`}>
                                                {item.restock ? 'restock' : 'write-off'}
                                            </span>
                                            {item.stock_restored_at && (
                                                <span className="text-[11px] text-gray-400">restored {formatDate(item.stock_restored_at)}</span>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </Card>

                        <Card>
                            <div className="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">History</h2>
                            </div>
                            <ol className="space-y-3 px-4 py-3">
                                {timeline.length === 0 && <li className="text-sm text-gray-500 dark:text-gray-400">No history recorded.</li>}
                                {timeline.map((entry) => (
                                    <li key={entry.id} className="relative border-l border-gray-200 pl-4 dark:border-gray-800">
                                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                                            {(entry.action || '').replace(/_/g, ' ')}
                                        </p>
                                        <p className="text-[11px] text-gray-400">
                                            {formatDate(entry.created_at)}
                                            {entry.actor_role ? ` · ${entry.actor_role}` : ''}
                                            {entry.from_status || entry.to_status ? ` · ${entry.from_status || '—'} → ${entry.to_status || '—'}` : ''}
                                        </p>
                                        {entry.note && <p className="mt-1 text-xs text-gray-700 dark:text-gray-300">{entry.note}</p>}
                                    </li>
                                ))}
                            </ol>
                        </Card>
                    </div>

                    <div className="space-y-4">
                        <Card className="p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Request details</h2>
                            <dl className="mt-3 space-y-2 text-xs">
                                <div className="flex justify-between gap-3">
                                    <dt className="text-gray-500 dark:text-gray-400">Resolution</dt>
                                    <dd className="font-semibold uppercase text-gray-800 dark:text-gray-200">{returnRequest.resolution}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-gray-500 dark:text-gray-400">Reason</dt>
                                    <dd className="text-right text-gray-800 dark:text-gray-200">{returnRequest.reason_label || '—'}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-gray-500 dark:text-gray-400">Shipping paid by</dt>
                                    <dd className="uppercase text-gray-800 dark:text-gray-200">{returnRequest.shipping_responsibility}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-gray-500 dark:text-gray-400">Order total</dt>
                                    <dd className="text-gray-800 dark:text-gray-200">{money(order.total_amount)}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-gray-500 dark:text-gray-400">Requested</dt>
                                    <dd className="text-gray-800 dark:text-gray-200">{formatDate(returnRequest.requested_at || returnRequest.created_at)}</dd>
                                </div>
                                {returnRequest.expires_at && (
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-gray-500 dark:text-gray-400">Window ends</dt>
                                        <dd className="text-gray-800 dark:text-gray-200">{formatDate(returnRequest.expires_at)}</dd>
                                    </div>
                                )}
                                <div className="flex justify-between gap-3">
                                    <dt className="text-gray-500 dark:text-gray-400">Refund</dt>
                                    <dd className="text-right text-gray-800 dark:text-gray-200">
                                        {returnRequest.refund ? `${returnRequest.refund.refund_number} · ${money(returnRequest.refund.amount)} (${returnRequest.refund.status})` : '—'}
                                    </dd>
                                </div>
                                {returnRequest.replacement_order && (
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-gray-500 dark:text-gray-400">Replacement</dt>
                                        <dd className="text-right text-gray-800 dark:text-gray-200">#{returnRequest.replacement_order.invoice_number || returnRequest.replacement_order.id}</dd>
                                    </div>
                                )}
                            </dl>
                        </Card>

                        <Card className="p-4">
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
                                    <dt className="text-gray-500 dark:text-gray-400">Track online</dt>
                                    <dd className="text-right">
                                        {tracking.tracking_url ? (
                                            <a href={tracking.tracking_url} target="_blank" rel="noopener noreferrer" className="font-semibold text-blue-600 hover:underline dark:text-blue-400">
                                                Open link
                                            </a>
                                        ) : (
                                            <span className="text-gray-800 dark:text-gray-200">—</span>
                                        )}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-gray-500 dark:text-gray-400">Shipped</dt>
                                    <dd className="text-gray-800 dark:text-gray-200">{tracking.shipped_at ? formatDate(tracking.shipped_at) : '—'}</dd>
                                </div>
                            </dl>
                            {tracking.tracking_note && (
                                <p className="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    {tracking.tracking_note}
                                </p>
                            )}
                        </Card>

                        <Card className="p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Customer notes</h2>
                            <p className="mt-2 text-xs text-gray-700 dark:text-gray-300">{returnRequest.customer_note || 'No additional note provided.'}</p>
                            {returnRequest.rejection_reason && (
                                <p className="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">Rejected: {returnRequest.rejection_reason}</p>
                            )}
                            {returnRequest.decision_note && (
                                <p className="mt-2 rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-700">Decision: {returnRequest.decision_note}</p>
                            )}
                        </Card>

                        {evidence.length > 0 && (
                            <Card className="p-4">
                                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Evidence</h2>
                                <div className="mt-3 grid grid-cols-2 gap-2">
                                    {evidence.map((url, index) => (
                                        <a key={`${url}-${index}`} href={url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
                                            <img src={url} alt={`Evidence ${index + 1}`} className="h-24 w-full object-cover" />
                                        </a>
                                    ))}
                                </div>
                            </Card>
                        )}
                    </div>
                </div>
            </div>

            {action && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4">
                    <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-5 dark:bg-gray-900 sm:rounded-2xl">
                        <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                            {{
                                approve: 'Approve return',
                                reject: 'Reject return',
                                receive: 'Record received items',
                                in_transit: 'Mark return in transit',
                                tracking: 'Update shipment tracking',
                                inspect: 'Inspect returned items',
                                complete: 'Complete return',
                                cancel: 'Cancel return',
                            }[action]}
                        </h3>

                        {action === 'approve' && (
                            <>
                                <p className="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                                    The customer will be asked to ship the items back. Stock is not restored at this step.
                                </p>
                                <textarea value={note} onChange={(event) => setNote(event.target.value)} rows={3} placeholder="Decision note (optional)" className="mt-3 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800" />
                            </>
                        )}

                        {action === 'reject' && (
                            <textarea value={note} onChange={(event) => setNote(event.target.value)} rows={3} placeholder="Rejection reason (required)" className="mt-3 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800" />
                        )}

                        {action === 'in_transit' && (
                            <>
                                <p className="mt-2 rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-700">
                                    The customer will see that the parcel is on its way back. Stock is not restored at this step.
                                </p>
                                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                    <label className="block text-xs font-medium text-gray-600 dark:text-gray-300">
                                        Carrier
                                        <input
                                            value={trackingForm.carrier_name}
                                            onChange={(event) => setTrackingForm((current) => ({ ...current, carrier_name: event.target.value }))}
                                            maxLength={100}
                                            placeholder="e.g. DHL"
                                            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                                        />
                                    </label>
                                    <label className="block text-xs font-medium text-gray-600 dark:text-gray-300">
                                        Tracking number
                                        <input
                                            value={trackingForm.tracking_number}
                                            onChange={(event) => setTrackingForm((current) => ({ ...current, tracking_number: event.target.value }))}
                                            maxLength={100}
                                            placeholder="e.g. 1Z999AA10123456784"
                                            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                                        />
                                    </label>
                                </div>
                                <label className="mt-3 block text-xs font-medium text-gray-600 dark:text-gray-300">
                                    Tracking URL
                                    <input
                                        value={trackingForm.tracking_url}
                                        onChange={(event) => setTrackingForm((current) => ({ ...current, tracking_url: event.target.value }))}
                                        maxLength={500}
                                        placeholder="https://carrier.example/track"
                                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                                    />
                                </label>
                                <label className="mt-3 block text-xs font-medium text-gray-600 dark:text-gray-300">
                                    Note (optional)
                                    <input
                                        value={trackingForm.tracking_note}
                                        onChange={(event) => setTrackingForm((current) => ({ ...current, tracking_note: event.target.value }))}
                                        maxLength={500}
                                        placeholder="Handed to courier"
                                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                                    />
                                </label>
                            </>
                        )}

                        {action === 'tracking' && (
                            <>
                                <p className="mt-2 rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-700">
                                    Update the shipment details without changing the return status. The customer will see the updated information.
                                </p>
                                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                    <label className="block text-xs font-medium text-gray-600 dark:text-gray-300">
                                        Carrier
                                        <input
                                            value={trackingForm.carrier_name}
                                            onChange={(event) => setTrackingForm((current) => ({ ...current, carrier_name: event.target.value }))}
                                            maxLength={100}
                                            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                                        />
                                    </label>
                                    <label className="block text-xs font-medium text-gray-600 dark:text-gray-300">
                                        Tracking number
                                        <input
                                            value={trackingForm.tracking_number}
                                            onChange={(event) => setTrackingForm((current) => ({ ...current, tracking_number: event.target.value }))}
                                            maxLength={100}
                                            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                                        />
                                    </label>
                                </div>
                                <label className="mt-3 block text-xs font-medium text-gray-600 dark:text-gray-300">
                                    Tracking URL
                                    <input
                                        value={trackingForm.tracking_url}
                                        onChange={(event) => setTrackingForm((current) => ({ ...current, tracking_url: event.target.value }))}
                                        maxLength={500}
                                        placeholder="https://"
                                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                                    />
                                </label>
                                <label className="mt-3 block text-xs font-medium text-gray-600 dark:text-gray-300">
                                    Shipped at
                                    <input
                                        type="datetime-local"
                                        value={trackingForm.shipped_at}
                                        onChange={(event) => setTrackingForm((current) => ({ ...current, shipped_at: event.target.value }))}
                                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                                    />
                                </label>
                                <label className="mt-3 block text-xs font-medium text-gray-600 dark:text-gray-300">
                                    Note
                                    <textarea
                                        value={trackingForm.tracking_note}
                                        onChange={(event) => setTrackingForm((current) => ({ ...current, tracking_note: event.target.value }))}
                                        rows={2}
                                        maxLength={500}
                                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                                    />
                                </label>
                            </>
                        )}

                        {action === 'receive' && (
                            <div className="mt-3 space-y-3">
                                <p className="text-xs text-gray-500 dark:text-gray-400">Adjust quantities if fewer items arrived.</p>
                                {items.map((item) => (
                                    <div key={item.id} className="flex items-center justify-between gap-3">
                                        <span className="min-w-0 truncate text-xs text-gray-700 dark:text-gray-200">
                                            {item.order_item?.product?.name || `Item #${item.order_item_id}`} (max {item.quantity})
                                        </span>
                                        <input
                                            type="number"
                                            min={0}
                                            max={item.quantity}
                                            value={received[item.id]}
                                            onChange={(event) => setReceived((current) => ({ ...current, [item.id]: event.target.value }))}
                                            className="w-20 rounded-lg border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
                                        />
                                    </div>
                                ))}
                            </div>
                        )}

                        {action === 'inspect' && (
                            <div className="mt-3 space-y-3">
                                {items.map((item) => (
                                    <div key={item.id} className="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                                        <p className="text-xs font-medium text-gray-800 dark:text-gray-200">
                                            {item.order_item?.product?.name || `Item #${item.order_item_id}`}
                                        </p>
                                        <div className="mt-2 flex flex-wrap items-center gap-3">
                                            <select
                                                value={inspection[item.id]?.condition || 'resellable'}
                                                onChange={(event) => setInspection((current) => ({ ...current, [item.id]: { ...current[item.id], condition: event.target.value } }))}
                                                className="rounded-lg border border-gray-300 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-800"
                                            >
                                                <option value="resellable">Resellable</option>
                                                <option value="damaged">Damaged</option>
                                            </select>
                                            <label className="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300">
                                                <input
                                                    type="checkbox"
                                                    checked={inspection[item.id]?.condition === 'resellable' && inspection[item.id]?.restock !== false}
                                                    disabled={inspection[item.id]?.condition !== 'resellable'}
                                                    onChange={(event) => setInspection((current) => ({ ...current, [item.id]: { ...current[item.id], restock: event.target.checked } }))}
                                                />
                                                Restock
                                            </label>
                                        </div>
                                        <input
                                            value={inspection[item.id]?.note || ''}
                                            onChange={(event) => setInspection((current) => ({ ...current, [item.id]: { ...current[item.id], note: event.target.value } }))}
                                            placeholder="Inspection note"
                                            className="mt-2 w-full rounded-lg border border-gray-300 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-800"
                                        />
                                    </div>
                                ))}
                            </div>
                        )}

                        {action === 'complete' && (
                            <div className="mt-3 space-y-3">
                                <p className="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    Resolution: <strong className="uppercase">{returnRequest.resolution}</strong>
                                    {returnRequest.resolution === 'refund' ? ' — a pending refund record will be created.' : ' — a stock-checked replacement order will be created.'}
                                </p>
                                {returnRequest.resolution === 'refund' && (
                                    <select value={method} onChange={(event) => setMethod(event.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800">
                                        <option value="manual">Manual</option>
                                        <option value="bank_transfer">Bank transfer</option>
                                        <option value="cash">Cash</option>
                                        <option value="mobile_wallet">Mobile wallet</option>
                                    </select>
                                )}
                                <textarea value={note} onChange={(event) => setNote(event.target.value)} rows={3} placeholder="Merchant note (optional)" className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800" />
                            </div>
                        )}

                        {action === 'cancel' && (
                            <p className="mt-3 text-sm text-gray-600 dark:text-gray-300">
                                Cancel this return? The customer will no longer be able to ship the items back.
                            </p>
                        )}

                        {action === 'tracking' && (
                            <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                Confirming will save these shipment details and notify the customer.
                            </p>
                        )}

                        <div className="mt-4 flex justify-end gap-2">
                            <button type="button" onClick={() => setAction(null)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                Close
                            </button>
                            <button
                                type="button"
                                disabled={submitting || (action === 'reject' && !note.trim())}
                                onClick={
                                    action === 'approve' ? submitApprove
                                        : action === 'reject' ? submitReject
                                            : action === 'receive' ? submitReceive
                                                : action === 'in_transit' ? submitMarkInTransit
                                                    : action === 'tracking' ? submitUpdateTracking
                                                    : action === 'inspect' ? submitInspect
                                                        : action === 'complete' ? submitComplete
                                                            : submitCancel
                                }
                                className={`rounded-lg px-4 py-2 text-sm font-semibold text-white disabled:opacity-60 ${
                                    action === 'reject' || action === 'cancel' ? 'bg-rose-600' : action === 'complete' ? 'bg-emerald-700' : 'bg-blue-600'
                                }`}
                            >
                                {submitting ? 'Saving…' : 'Confirm'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
