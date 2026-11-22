import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import Pagination from '@/Components/Pagination';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import { usePage } from '@inertiajs/react';
import {
    BackLink,
    Card,
    EmptyState,
    FilterBar,
    PageShell,
    StatCard,
    StatusBadge,
    formatDate,
} from '@/Components/Admin/AfterSalesUI';

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'requested', label: 'Requested' },
    { value: 'approved', label: 'Approved' },
    { value: 'completed', label: 'Completed' },
    { value: 'rejected', label: 'Rejected' },
];

export default function AdminCancellationsIndex({ cancellations, filters = {}, counts = {}, canApprove = false, canReject = false }) {
    const { platform_setting, website_info } = usePage().props;
    const cc = getCurrencyConfig(platform_setting, website_info);

    const [dialog, setDialog] = useState(null);
    const [note, setNote] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const rows = cancellations?.data || [];

    function applyFilters(next) {
        router.get(adminUrl('/admin/cancellations'), next, { preserveState: true, preserveScroll: true, replace: true });
    }

    function open(dialogType, item) {
        setDialog({ type: dialogType, item });
        setNote('');
    }

    function close() {
        setDialog(null);
        setNote('');
    }

    function submit() {
        if (!dialog) return;
        const { type, item } = dialog;
        setSubmitting(true);
        const url = type === 'approve'
            ? adminUrl(`/admin/cancellations/${item.id}/approve`)
            : adminUrl(`/admin/cancellations/${item.id}/reject`);

        router.post(url, type === 'approve' ? { note } : { reason: note }, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: close,
        });
    }

    const money = (value) => formatCurrency(value, cc);

    return (
        <AdminLayout>
            <Head title="Cancellation Requests" />
            <PageShell
                title="Cancellation Requests"
                subtitle="Review customer cancellation requests and monitor completed cancellations"
                actions={<BackLink href={adminUrl('/admin/orders')} label="Orders" />}
            >
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard label="Awaiting review" value={counts.requested ?? 0} tone="amber" />
                    <StatCard label="Approved" value={counts.approved ?? 0} tone="blue" />
                    <StatCard label="Completed" value={counts.completed ?? 0} tone="emerald" />
                    <StatCard label="Rejected" value={counts.rejected ?? 0} tone="rose" />
                </div>

                <Card className="mt-4">
                    <FilterBar
                        statuses={STATUS_TABS.map((tab) => ({ ...tab, count: tab.value ? counts[tab.value] : undefined }))}
                        filters={filters}
                        onChange={applyFilters}
                        searchPlaceholder="Search cancellation, order or customer…"
                    />

                    {rows.length === 0 ? (
                        <EmptyState
                            title="No cancellation requests"
                            description={filters.status ? 'No requests match this filter.' : 'Customer cancellation requests will appear here.'}
                        />
                    ) : (
                        <>
                            <div className="hidden overflow-x-auto sm:block">
                                <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                                    <thead className="bg-gray-50 dark:bg-gray-800/50">
                                        <tr className="text-left text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            <th className="px-4 py-3 font-semibold">Request</th>
                                            <th className="px-4 py-3 font-semibold">Order</th>
                                            <th className="px-4 py-3 font-semibold">Reason</th>
                                            <th className="px-4 py-3 font-semibold">Mode</th>
                                            <th className="px-4 py-3 font-semibold">Refund</th>
                                            <th className="px-4 py-3 font-semibold">Requested</th>
                                            <th className="px-4 py-3 font-semibold text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                        {rows.map((item) => (
                                            <tr key={item.id} className="hover:bg-gray-50/70 dark:hover:bg-gray-800/40">
                                                <td className="px-4 py-3">
                                                    <p className="font-mono text-xs text-gray-700 dark:text-gray-200">{item.cancellation_number}</p>
                                                    <div className="mt-1 flex items-center gap-2">
                                                        <StatusBadge status={item.status} />
                                                        <span className="text-[11px] uppercase text-gray-400">{item.requested_by_role}</span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <p className="font-mono text-xs text-gray-800 dark:text-gray-100">#{item.order?.invoice_number || item.order_id}</p>
                                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                                        {item.order ? `${item.order.first_name} ${item.order.last_name}` : '—'}
                                                    </p>
                                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                                        {item.order ? money(item.order.total_amount) : '—'}
                                                    </p>
                                                </td>
                                                <td className="max-w-[220px] px-4 py-3">
                                                    <p className="truncate text-xs font-medium text-gray-700 dark:text-gray-200">{item.reason_label || 'No reason'}</p>
                                                    {item.customer_note && <p className="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{item.customer_note}</p>}
                                                    {item.rejection_reason && <p className="mt-0.5 text-xs text-rose-600">Rejected: {item.rejection_reason}</p>}
                                                </td>
                                                <td className="px-4 py-3 text-xs uppercase text-gray-600 dark:text-gray-300">{item.mode?.replace(/_/g, ' ')}</td>
                                                <td className="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                                                    {item.refund ? (
                                                        <>
                                                            <span className="font-medium text-purple-600 dark:text-purple-300">{money(item.refund.amount)}</span>
                                                            <p className="text-[11px] text-gray-500">{item.refund.status}</p>
                                                        </>
                                                    ) : '—'}
                                                </td>
                                                <td className="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{formatDate(item.requested_at || item.created_at)}</td>
                                                <td className="px-4 py-3 text-right">
                                                    {item.status === 'requested' ? (
                                                        <div className="flex justify-end gap-2">
                                                            {canApprove && (
                                                                <button type="button" onClick={() => open('approve', item)} className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                                                    Approve
                                                                </button>
                                                            )}
                                                            {canReject && (
                                                                <button type="button" onClick={() => open('reject', item)} className="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                                                    Reject
                                                                </button>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-gray-400">—</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="divide-y divide-gray-100 dark:divide-gray-800 sm:hidden">
                                {rows.map((item) => (
                                    <div key={item.id} className="p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-mono text-xs text-gray-700 dark:text-gray-200">{item.cancellation_number}</p>
                                                <p className="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                    #{item.order?.invoice_number || item.order_id}
                                                </p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                    {item.order ? `${item.order.first_name} ${item.order.last_name}` : '—'}
                                                </p>
                                            </div>
                                            <StatusBadge status={item.status} />
                                        </div>
                                        <dl className="mt-3 space-y-1.5 text-xs">
                                            <div className="flex justify-between gap-3">
                                                <dt className="text-gray-500 dark:text-gray-400">Reason</dt>
                                                <dd className="text-right text-gray-800 dark:text-gray-200">{item.reason_label || 'No reason'}</dd>
                                            </div>
                                            <div className="flex justify-between gap-3">
                                                <dt className="text-gray-500 dark:text-gray-400">Mode</dt>
                                                <dd className="uppercase text-gray-800 dark:text-gray-200">{item.mode?.replace(/_/g, ' ')}</dd>
                                            </div>
                                            <div className="flex justify-between gap-3">
                                                <dt className="text-gray-500 dark:text-gray-400">Order total</dt>
                                                <dd className="text-gray-800 dark:text-gray-200">{item.order ? money(item.order.total_amount) : '—'}</dd>
                                            </div>
                                            {item.refund && (
                                                <div className="flex justify-between gap-3">
                                                    <dt className="text-gray-500 dark:text-gray-400">Refund</dt>
                                                    <dd className="text-purple-600 dark:text-purple-300">{money(item.refund.amount)} ({item.refund.status})</dd>
                                                </div>
                                            )}
                                            <div className="flex justify-between gap-3">
                                                <dt className="text-gray-500 dark:text-gray-400">Requested</dt>
                                                <dd className="text-gray-800 dark:text-gray-200">{formatDate(item.requested_at || item.created_at)}</dd>
                                            </div>
                                        </dl>
                                        {item.status === 'requested' && (canApprove || canReject) && (
                                            <div className="mt-3 grid grid-cols-2 gap-2">
                                                {canApprove && (
                                                    <button type="button" onClick={() => open('approve', item)} className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white">
                                                        Approve
                                                    </button>
                                                )}
                                                {canReject && (
                                                    <button type="button" onClick={() => open('reject', item)} className="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600">
                                                        Reject
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <Pagination meta={cancellations} />
                        </>
                    )}
                </Card>
            </PageShell>

            {dialog && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4">
                    <div className="w-full max-w-md rounded-t-2xl bg-white p-5 dark:bg-gray-900 sm:rounded-2xl">
                        <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                            {dialog.type === 'approve' ? 'Approve cancellation' : 'Reject cancellation'}
                        </h3>
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {dialog.item.cancellation_number} · Order #{dialog.item.order?.invoice_number || dialog.item.order_id}
                        </p>
                        {dialog.type === 'approve' && (
                            <p className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                Approving cancels the order, restores stock if it was deducted and creates a pending refund for paid orders.
                            </p>
                        )}
                        <label className="mt-4 block text-xs font-medium text-gray-600 dark:text-gray-300">
                            {dialog.type === 'approve' ? 'Decision note (optional)' : 'Rejection reason'}
                        </label>
                        <textarea
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            rows={3}
                            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                        />
                        <div className="mt-4 flex justify-end gap-2">
                            <button type="button" onClick={close} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                Close
                            </button>
                            <button
                                type="button"
                                onClick={submit}
                                disabled={submitting || (dialog.type === 'reject' && !note.trim())}
                                className={`rounded-lg px-4 py-2 text-sm font-semibold text-white disabled:opacity-60 ${dialog.type === 'approve' ? 'bg-emerald-600' : 'bg-rose-600'}`}
                            >
                                {submitting ? 'Saving…' : dialog.type === 'approve' ? 'Approve' : 'Reject'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
