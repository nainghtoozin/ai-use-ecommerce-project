import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { BackLink, Card, PageShell } from '@/Components/Admin/AfterSalesUI';

const CANCELLABLE_STATUSES = ['pending', 'confirmed', 'processing', 'shipped'];

const MODE_OPTIONS = [
    { value: 'instant', label: 'Instant cancellation' },
    { value: 'approval', label: 'Requires approval' },
    { value: 'not_allowed', label: 'Not allowed' },
];

const DEFAULT_RULES = {
    pending: 'instant',
    confirmed: 'approval',
    processing: 'approval',
    shipped: 'not_allowed',
    delivered: 'return_only',
};

function Toggle({ label, description, checked, onChange }) {
    return (
        <label className="flex cursor-pointer items-start justify-between gap-4 py-2">
            <span>
                <span className="block text-sm font-medium text-gray-800 dark:text-gray-200">{label}</span>
                {description && <span className="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{description}</span>}
            </span>
            <span className="relative mt-0.5 inline-flex h-5 w-9 shrink-0 items-center">
                <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className="peer sr-only" />
                <span className="h-5 w-9 rounded-full bg-gray-300 transition-colors peer-checked:bg-blue-600 dark:bg-gray-700" />
                <span className="absolute left-0.5 h-4 w-4 rounded-full bg-white transition-transform peer-checked:translate-x-4" />
            </span>
        </label>
    );
}

export default function CancellationPolicy({ settings }) {
    const [form, setForm] = useState({
        customer_cancel_enabled: settings.customer_cancel_enabled ?? true,
        merchant_cancel_enabled: settings.merchant_cancel_enabled ?? true,
        status_rules: { ...DEFAULT_RULES, ...(settings.status_rules || {}) },
        instant_cancel_window_hours: settings.instant_cancel_window_hours ?? 24,
        max_cancellations: settings.max_cancellations ?? 3,
        limit_period_days: settings.limit_period_days ?? 30,
        limit_exceeded_action: settings.limit_exceeded_action || 'require_approval',
        allow_request_after_limit: settings.allow_request_after_limit ?? true,
        require_reason: settings.require_reason ?? true,
        require_note: settings.require_note ?? false,
        refund_shipping_fee: settings.refund_shipping_fee ?? false,
        reasons: settings.reasons || [],
    });
    const [processing, setProcessing] = useState(false);

    function update(key, value) {
        setForm((current) => ({ ...current, [key]: value }));
    }

    function updateRule(status, mode) {
        setForm((current) => ({ ...current, status_rules: { ...current.status_rules, [status]: mode } }));
    }

    function updateReason(index, value) {
        setForm((current) => {
            const reasons = [...current.reasons];
            reasons[index] = { ...reasons[index], label: value };
            return { ...current, reasons };
        });
    }

    function addReason() {
        setForm((current) => ({ ...current, reasons: [...current.reasons, { code: '', label: '' }] }));
    }

    function removeReason(index) {
        setForm((current) => ({ ...current, reasons: current.reasons.filter((_, i) => i !== index) }));
    }

    function submit(event) {
        event.preventDefault();
        setProcessing(true);
        router.post(adminUrl('/admin/settings/cancellation-policy'), form, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <AdminLayout>
            <Head title="Cancellation Policy" />
            <PageShell
                title="Cancellation Policy"
                subtitle="Control how customers can cancel orders in this store"
                actions={<BackLink href={adminUrl('/admin/cancellations')} label="Cancellation requests" />}
            >
                <form onSubmit={submit} className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <Card className="p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Availability</h2>
                            <div className="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
                                <Toggle label="Allow customer cancellations" description="Customers can request cancellation from their order page." checked={form.customer_cancel_enabled} onChange={(value) => update('customer_cancel_enabled', value)} />
                                <Toggle label="Allow merchant cancellations" description="Staff can cancel orders directly from the dashboard." checked={form.merchant_cancel_enabled} onChange={(value) => update('merchant_cancel_enabled', value)} />
                            </div>
                        </Card>

                        <Card className="p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Rules by order status</h2>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Shipped orders can only require approval, never instant cancellation. Delivered orders always use the return workflow.
                            </p>
                            <div className="mt-3 space-y-2">
                                {CANCELLABLE_STATUSES.map((status) => (
                                    <div key={status} className="flex flex-col gap-2 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                                        <span className="text-sm font-medium capitalize text-gray-800 dark:text-gray-200">{status}</span>
                                        <select
                                            value={form.status_rules[status] || 'not_allowed'}
                                            onChange={(event) => updateRule(status, event.target.value)}
                                            className="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 sm:w-56"
                                        >
                                            {MODE_OPTIONS.filter((option) => !(status === 'shipped' && option.value === 'instant')).map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                ))}
                                <div className="flex items-center justify-between rounded-lg border border-dashed border-gray-200 px-3 py-2 dark:border-gray-800">
                                    <span className="text-sm font-medium capitalize text-gray-500 dark:text-gray-400">delivered</span>
                                    <span className="text-xs text-gray-400">Return workflow only</span>
                                </div>
                            </div>
                        </Card>

                        <Card className="p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Limits and requirements</h2>
                            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Instant cancellation window (hours)</label>
                                    <input type="number" min="0" max="8760" value={form.instant_cancel_window_hours ?? ''} onChange={(event) => update('instant_cancel_window_hours', event.target.value)} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800" />
                                    <p className="mt-1 text-[11px] text-gray-400">Leave empty for no time limit.</p>
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Max cancellations per customer</label>
                                    <input type="number" min="0" max="1000" value={form.max_cancellations ?? ''} onChange={(event) => update('max_cancellations', event.target.value)} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800" />
                                    <p className="mt-1 text-[11px] text-gray-400">Leave empty to disable the limit.</p>
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Limit period (days)</label>
                                    <input type="number" min="1" max="365" value={form.limit_period_days} onChange={(event) => update('limit_period_days', event.target.value)} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800" />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-gray-600 dark:text-gray-300">When the limit is reached</label>
                                    <select value={form.limit_exceeded_action} onChange={(event) => update('limit_exceeded_action', event.target.value)} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800">
                                        <option value="require_approval">Require merchant approval</option>
                                        <option value="block">Block cancellation</option>
                                    </select>
                                </div>
                            </div>
                            <div className="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
                                <Toggle label="Allow requests after the instant window or limit" description="Requests become approval-based instead of being blocked." checked={form.allow_request_after_limit} onChange={(value) => update('allow_request_after_limit', value)} />
                                <Toggle label="Require a cancellation reason" checked={form.require_reason} onChange={(value) => update('require_reason', value)} />
                                <Toggle label="Require an additional note" checked={form.require_note} onChange={(value) => update('require_note', value)} />
                                <Toggle label="Refund delivery, packaging and COD fees" description="When disabled, only goods value is refunded on cancellation." checked={form.refund_shipping_fee} onChange={(value) => update('refund_shipping_fee', value)} />
                            </div>
                        </Card>

                        <Card className="p-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Cancellation reasons</h2>
                                <button type="button" onClick={addReason} className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                    Add reason
                                </button>
                            </div>
                            <div className="mt-3 space-y-2">
                                {form.reasons.map((reason, index) => (
                                    <div key={index} className="flex gap-2">
                                        <input value={reason.label || ''} onChange={(event) => updateReason(index, event.target.value)} placeholder="Reason label" className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800" />
                                        <button type="button" onClick={() => removeReason(index)} className="rounded-lg border border-rose-200 px-3 text-xs font-semibold text-rose-600">
                                            Remove
                                        </button>
                                    </div>
                                ))}
                                {form.reasons.length === 0 && <p className="text-xs text-gray-500 dark:text-gray-400">No custom reasons — defaults will be used.</p>}
                            </div>
                        </Card>
                    </div>

                    <div className="space-y-4">
                        <Card className="p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Summary</h2>
                            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Customer cancellations are {form.customer_cancel_enabled ? 'enabled' : 'disabled'}. The first{' '}
                                {form.max_cancellations ?? 'unlimited'} successful cancellation(s) per {form.limit_period_days} day(s) are counted per customer.
                            </p>
                            <button type="submit" disabled={processing} className="mt-4 w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                                {processing ? 'Saving…' : 'Save policy'}
                            </button>
                        </Card>
                    </div>
                </form>
            </PageShell>
        </AdminLayout>
    );
}
