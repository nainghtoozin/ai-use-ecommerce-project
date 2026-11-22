import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import Pagination from '@/Components/Pagination';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
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
    { value: 'pending', label: 'Pending' },
    { value: 'processing', label: 'Processing' },
    { value: 'completed', label: 'Completed' },
    { value: 'failed', label: 'Failed' },
];

export default function AdminRefundsIndex({ refunds, filters = {}, totals = {}, permissions = {} }) {
    const { platform_setting, website_info } = usePage().props;
    const cc = getCurrencyConfig(platform_setting, website_info);

    const [dialog, setDialog] = useState(null);
    const [form, setForm] = useState({ order_id: '', amount: '', method: 'manual', reference: '', notes: '', evidence: null });
    const [submitting, setSubmitting] = useState(false);

    const rows = refunds?.data || [];
    const money = (value) => formatCurrency(value, cc);

    function applyFilters(next) {
        router.get(adminUrl('/admin/refunds'), next, { preserveState: true, preserveScroll: true, replace: true });
    }

    function open(type, refund = null) {
        setDialog({ type, refund });
        setForm({ order_id: '', amount: refund?.amount || '', method: refund?.method || 'manual', reference: refund?.reference || '', notes: '', evidence: null });
    }

    function close() {
        setDialog(null);
    }

    function submitCreate() {
        setSubmitting(true);
        router.post(adminUrl('/admin/refunds'), form, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: close,
        });
    }

    function submitAction(path, payload) {
        setSubmitting(true);
        router.post(adminUrl(path), payload, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: close,
        });
    }

    return (
        <AdminLayout>
            <Head title="Refund Management" />
            <PageShell
                title="Refund Management"
                subtitle="Record manual refunds and track their progress — no automatic gateway transfers"
                actions={
                    <>
                        <BackLink href={adminUrl('/admin/orders')} label="Orders" />
                        {permissions.manage && (
                            <button type="button" onClick={() => open('create')} className="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">
                                New refund
                            </button>
                        )}
                    </>
                }
            >
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <StatCard label="Pending amount" value={money(totals.pending ?? 0)} tone="amber" />
                    <StatCard label="Completed amount" value={money(totals.completed ?? 0)} tone="emerald" />
                    <StatCard label="Failed amount" value={money(totals.failed ?? 0)} tone="rose" />
                </div>

                <Card className="mt-4">
                    <FilterBar statuses={STATUS_TABS} filters={filters} onChange={applyFilters} searchPlaceholder="Search refund, order or reference…" />

                    {rows.length === 0 ? (
                        <EmptyState title="No refunds" description={filters.status ? 'No refunds match this filter.' : 'Refund records created from cancellations and returns appear here.'} />
                    ) : (
                        <>
                            <div className="hidden overflow-x-auto sm:block">
                                <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                                    <thead className="bg-gray-50 dark:bg-gray-800/50">
                                        <tr className="text-left text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            <th className="px-4 py-3 font-semibold">Refund</th>
                                            <th className="px-4 py-3 font-semibold">Order</th>
                                            <th className="px-4 py-3 font-semibold">Amount</th>
                                            <th className="px-4 py-3 font-semibold">Method</th>
                                            <th className="px-4 py-3 font-semibold">Reference</th>
                                            <th className="px-4 py-3 font-semibold">Created</th>
                                            <th className="px-4 py-3 font-semibold text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                        {rows.map((refund) => (
                                            <tr key={refund.id} className="hover:bg-gray-50/70 dark:hover:bg-gray-800/40">
                                                <td className="px-4 py-3">
                                                    <p className="font-mono text-xs text-gray-700 dark:text-gray-200">{refund.refund_number}</p>
                                                    <div className="mt-1"><StatusBadge status={refund.status} /></div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <p className="font-mono text-xs text-gray-800 dark:text-gray-100">#{refund.order?.invoice_number || refund.order_id}</p>
                                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                                        {refund.order ? `${refund.order.first_name} ${refund.order.last_name}` : '—'}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100">{money(refund.amount)}</td>
                                                <td className="px-4 py-3 text-xs uppercase text-gray-600 dark:text-gray-300">{refund.method}</td>
                                                <td className="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{refund.reference || '—'}</td>
                                                <td className="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{formatDate(refund.created_at)}</td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        {permissions.process && refund.status === 'pending' && (
                                                            <button type="button" onClick={() => submitAction(`/admin/refunds/${refund.id}/process`, {})} className="rounded-lg border border-blue-200 px-2.5 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50">
                                                                Process
                                                            </button>
                                                        )}
                                                        {permissions.process && (refund.status === 'pending' || refund.status === 'processing') && (
                                                            <>
                                                                <button type="button" onClick={() => open('complete', refund)} className="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                                                    Complete
                                                                </button>
                                                                <button type="button" onClick={() => open('fail', refund)} className="rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                                                    Fail
                                                                </button>
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="divide-y divide-gray-100 dark:divide-gray-800 sm:hidden">
                                {rows.map((refund) => (
                                    <div key={refund.id} className="p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-mono text-xs text-gray-700 dark:text-gray-200">{refund.refund_number}</p>
                                                <p className="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{money(refund.amount)}</p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                    #{refund.order?.invoice_number || refund.order_id} · {refund.method}
                                                </p>
                                            </div>
                                            <StatusBadge status={refund.status} />
                                        </div>
                                        <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">Created {formatDate(refund.created_at)}</p>
                                        {permissions.process && (refund.status === 'pending' || refund.status === 'processing') && (
                                            <div className="mt-3 grid grid-cols-3 gap-2">
                                                {refund.status === 'pending' && (
                                                    <button type="button" onClick={() => submitAction(`/admin/refunds/${refund.id}/process`, {})} className="rounded-lg border border-blue-200 px-2 py-2 text-xs font-semibold text-blue-600">
                                                        Process
                                                    </button>
                                                )}
                                                <button type="button" onClick={() => open('complete', refund)} className="rounded-lg bg-emerald-600 px-2 py-2 text-xs font-semibold text-white">
                                                    Complete
                                                </button>
                                                <button type="button" onClick={() => open('fail', refund)} className="rounded-lg border border-rose-200 px-2 py-2 text-xs font-semibold text-rose-600">
                                                    Fail
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <Pagination meta={refunds} />
                        </>
                    )}
                </Card>
            </PageShell>

            {dialog && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4">
                    <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-5 dark:bg-gray-900 sm:rounded-2xl">
                        <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                            {dialog.type === 'create' ? 'Create refund record' : dialog.type === 'complete' ? 'Complete refund' : 'Mark refund as failed'}
                        </h3>
                        {dialog.type === 'create' ? (
                            <div className="mt-3 space-y-3">
                                <div>
                                    <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Order ID</label>
                                    <input type="number" value={form.order_id} onChange={(event) => setForm({ ...form, order_id: event.target.value })} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800" />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Amount</label>
                                        <input type="number" step="0.01" value={form.amount} onChange={(event) => setForm({ ...form, amount: event.target.value })} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800" />
                                    </div>
                                    <div>
                                        <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Method</label>
                                        <select value={form.method} onChange={(event) => setForm({ ...form, method: event.target.value })} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800">
                                            <option value="manual">Manual</option>
                                            <option value="bank_transfer">Bank transfer</option>
                                            <option value="cash">Cash</option>
                                            <option value="mobile_wallet">Mobile wallet</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Reference</label>
                                    <input value={form.reference} onChange={(event) => setForm({ ...form, reference: event.target.value })} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800" />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Notes</label>
                                    <textarea rows={2} value={form.notes} onChange={(event) => setForm({ ...form, notes: event.target.value })} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800" />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Evidence (optional)</label>
                                    <input type="file" accept="image/*" onChange={(event) => setForm({ ...form, evidence: event.target.files?.[0] || null })} className="mt-1 w-full text-xs" />
                                </div>
                            </div>
                        ) : (
                            <div className="mt-3 space-y-3">
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    {dialog.refund?.refund_number} · {money(dialog.refund?.amount || 0)}
                                </p>
                                {dialog.type === 'complete' && (
                                    <>
                                        <div>
                                            <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Reference</label>
                                            <input value={form.reference} onChange={(event) => setForm({ ...form, reference: event.target.value })} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800" />
                                        </div>
                                        <div>
                                            <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Notes</label>
                                            <textarea rows={2} value={form.notes} onChange={(event) => setForm({ ...form, notes: event.target.value })} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800" />
                                        </div>
                                    </>
                                )}
                                {dialog.type === 'fail' && (
                                    <div>
                                        <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Failure reason</label>
                                        <textarea rows={3} value={form.notes} onChange={(event) => setForm({ ...form, notes: event.target.value })} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800" />
                                    </div>
                                )}
                            </div>
                        )}
                        <div className="mt-4 flex justify-end gap-2">
                            <button type="button" onClick={close} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                Close
                            </button>
                            <button
                                type="button"
                                disabled={submitting}
                                onClick={() => {
                                    if (dialog.type === 'create') return submitCreate();
                                    if (dialog.type === 'complete') {
                                        return submitAction(`/admin/refunds/${dialog.refund.id}/complete`, { reference: form.reference, notes: form.notes });
                                    }
                                    return submitAction(`/admin/refunds/${dialog.refund.id}/fail`, { reason: form.notes });
                                }}
                                className={`rounded-lg px-4 py-2 text-sm font-semibold text-white disabled:opacity-60 ${dialog.type === 'fail' ? 'bg-rose-600' : 'bg-emerald-600'}`}
                            >
                                {submitting ? 'Saving…' : dialog.type === 'create' ? 'Create' : 'Confirm'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
