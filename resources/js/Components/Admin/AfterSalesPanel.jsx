import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency } from '@/Utils/currency';

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

function StatusBadge({ status }) {
    return (
        <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ${CASE_STATUS_STYLES[status] || 'bg-gray-100 text-gray-600 border-gray-200'}`}>
            {(status || 'unknown').replace(/_/g, ' ')}
        </span>
    );
}

function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString();
}

export default function AfterSalesPanel({ order, afterSales = {}, permissions = {}, currencyConfig = null }) {
    const [cancelOpen, setCancelOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [note, setNote] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const cancellation = afterSales.cancellation || {};
    const returnInfo = afterSales.return || {};
    const cancellations = afterSales.cancellations || [];
    const returns = afterSales.returns || [];
    const refunds = afterSales.refunds || [];

    const money = (value) => (currencyConfig ? formatCurrency(value, currencyConfig) : Number(value || 0).toFixed(2));

    function submitCancellation() {
        setSubmitting(true);
        router.post(
            adminUrl(`/admin/orders/${order.id}/cancel`),
            { reason_label: reason || null, note: note || null },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
                onSuccess: () => {
                    setCancelOpen(false);
                    setReason('');
                    setNote('');
                },
            }
        );
    }

    return (
        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 sm:p-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">After-Sales</h2>
                    <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Cancellations, returns and refunds for this order
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {permissions.manage_returns && (
                        <Link
                            href={adminUrl('/admin/returns')}
                            className="inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                            All returns
                        </Link>
                    )}
                    {permissions.manage_refunds && (
                        <Link
                            href={adminUrl('/admin/refunds')}
                            className="inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                            All refunds
                        </Link>
                    )}
                    {permissions.cancel && cancellation.allowed && (
                        <button
                            type="button"
                            onClick={() => setCancelOpen(true)}
                            className="inline-flex items-center rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700"
                        >
                            Cancel order
                        </button>
                    )}
                </div>
            </div>

            {cancellation.blocked_reason && (
                <p className="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    {cancellation.blocked_reason}
                </p>
            )}

            {returnInfo.allowed && (
                <p className="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                    Return window open{typeof returnInfo.days_remaining === 'number' ? ` — ${Math.max(0, returnInfo.days_remaining)} day(s) remaining` : ''}.
                </p>
            )}

            <div className="mt-5 grid gap-4 lg:grid-cols-2">
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cancellations</h3>
                    {cancellations.length === 0 ? (
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">No cancellation requests.</p>
                    ) : (
                        <ul className="mt-2 space-y-2">
                            {cancellations.map((item) => (
                                <li key={item.id} className="rounded-lg border border-gray-200 dark:border-gray-800 p-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-xs font-mono text-gray-500">{item.cancellation_number}</span>
                                        <StatusBadge status={item.status} />
                                        <span className="text-[11px] uppercase tracking-wide text-gray-400">{item.requested_by_role}</span>
                                    </div>
                                    <p className="mt-1 text-sm text-gray-800 dark:text-gray-200">{item.reason_label || 'No reason provided'}</p>
                                    {item.customer_note && <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{item.customer_note}</p>}
                                    {item.refund && (
                                        <p className="mt-1 text-xs text-purple-600 dark:text-purple-300">
                                            Refund {item.refund.refund_number} — {money(item.refund.amount)} ({item.refund.status})
                                        </p>
                                    )}
                                    <p className="mt-1 text-[11px] text-gray-400">Requested {formatDate(item.requested_at)}</p>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Returns</h3>
                    {returns.length === 0 ? (
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">No return requests.</p>
                    ) : (
                        <ul className="mt-2 space-y-2">
                            {returns.map((item) => (
                                <li key={item.id} className="rounded-lg border border-gray-200 dark:border-gray-800 p-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link href={adminUrl(`/admin/returns/${item.id}`)} className="text-xs font-mono text-blue-600 hover:underline">
                                            {item.rma_number}
                                        </Link>
                                        <StatusBadge status={item.status} />
                                        <span className="text-[11px] uppercase tracking-wide text-gray-400">{item.resolution}</span>
                                    </div>
                                    <p className="mt-1 text-sm text-gray-800 dark:text-gray-200">{item.reason_label || 'No reason provided'}</p>
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {item.items?.length || 0} item line(s) — shipping paid by {item.shipping_responsibility}
                                    </p>
                                    {item.refund && (
                                        <p className="mt-1 text-xs text-purple-600 dark:text-purple-300">
                                            Refund {item.refund.refund_number} — {money(item.refund.amount)} ({item.refund.status})
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <section className="mt-5">
                <h3 className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Refund records</h3>
                {refunds.length === 0 ? (
                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">No refund records.</p>
                ) : (
                    <div className="mt-2 overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                            <thead>
                                <tr className="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th className="py-2 pr-3">Number</th>
                                    <th className="py-2 pr-3">Amount</th>
                                    <th className="py-2 pr-3">Method</th>
                                    <th className="py-2 pr-3">Status</th>
                                    <th className="py-2">Processed</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                {refunds.map((refund) => (
                                    <tr key={refund.id}>
                                        <td className="py-2 pr-3 font-mono text-xs">{refund.refund_number}</td>
                                        <td className="py-2 pr-3 font-medium">{money(refund.amount)}</td>
                                        <td className="py-2 pr-3 text-xs uppercase">{refund.method}</td>
                                        <td className="py-2 pr-3"><StatusBadge status={refund.status} /></td>
                                        <td className="py-2 text-xs text-gray-500">{formatDate(refund.processed_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            {cancelOpen && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4">
                    <div className="w-full max-w-md rounded-t-2xl bg-white p-5 shadow-xl dark:bg-gray-900 sm:rounded-2xl">
                        <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">Cancel this order</h3>
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Stock will be restored if it was already deducted. A pending refund record is created for paid orders.
                        </p>
                        <label className="mt-4 block text-xs font-medium text-gray-600 dark:text-gray-300">Reason</label>
                        <input
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            placeholder="e.g. Customer requested cancellation"
                            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                        />
                        <label className="mt-3 block text-xs font-medium text-gray-600 dark:text-gray-300">Internal note</label>
                        <textarea
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            rows={3}
                            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                        />
                        <div className="mt-4 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setCancelOpen(false)}
                                className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200"
                            >
                                Close
                            </button>
                            <button
                                type="button"
                                onClick={submitCancellation}
                                disabled={submitting}
                                className="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-60"
                            >
                                {submitting ? 'Cancelling…' : 'Confirm cancellation'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
