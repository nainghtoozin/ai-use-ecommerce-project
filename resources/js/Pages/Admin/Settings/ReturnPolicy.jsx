import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { BackLink, Card, PageShell } from '@/Components/Admin/AfterSalesUI';

const WINDOW_PRESETS = [3, 7, 14, 30];

function slugify(value) {
    return String(value || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
}

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

export default function ReturnPolicy({ settings }) {
    const shippingMap = settings.shipping_responsibility_by_reason || {};

    const [form, setForm] = useState({
        returns_enabled: settings.returns_enabled ?? true,
        return_window_days: settings.return_window_days ?? 7,
        allow_full_return: settings.allow_full_return ?? true,
        allow_partial_return: settings.allow_partial_return ?? true,
        require_reason: settings.require_reason ?? true,
        require_note: settings.require_note ?? false,
        require_evidence: settings.require_evidence ?? false,
        allow_refund: settings.allow_refund ?? true,
        allow_exchange: settings.allow_exchange ?? false,
        allow_replacement: settings.allow_replacement ?? false,
        return_shipping_responsibility: settings.return_shipping_responsibility || 'customer',
        refund_shipping_fee: settings.refund_shipping_fee ?? false,
        instructions: settings.instructions || '',
        policy_text: settings.policy_text || '',
        reasons: (settings.reasons || []).map((reason) => ({
            code: reason.code || slugify(reason.label),
            label: reason.label || '',
            shipping: shippingMap[reason.code] || 'merchant',
        })),
    });
    const [processing, setProcessing] = useState(false);

    function update(key, value) {
        setForm((current) => ({ ...current, [key]: value }));
    }

    function updateReason(index, patch) {
        setForm((current) => {
            const reasons = [...current.reasons];
            reasons[index] = { ...reasons[index], ...patch };
            if (patch.label !== undefined) {
                reasons[index].code = reasons[index].code || slugify(patch.label);
            }
            return { ...current, reasons };
        });
    }

    function addReason() {
        setForm((current) => ({ ...current, reasons: [...current.reasons, { code: '', label: '', shipping: 'merchant' }] }));
    }

    function removeReason(index) {
        setForm((current) => ({ ...current, reasons: current.reasons.filter((_, i) => i !== index) }));
    }

    function submit(event) {
        event.preventDefault();

        const shippingMapPayload = {};
        form.reasons.forEach((reason) => {
            const code = reason.code || slugify(reason.label);
            if (code) shippingMapPayload[code] = reason.shipping;
        });

        setProcessing(true);
        router.post(adminUrl('/admin/settings/return-policy'), {
            ...form,
            reasons: form.reasons.map((reason) => ({ code: reason.code || slugify(reason.label), label: reason.label })),
            shipping_responsibility_by_reason: shippingMapPayload,
        }, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <AdminLayout>
            <Head title="Return Policy" />
            <PageShell
                title="Return Policy"
                subtitle="Define return windows, conditions and how refunds or replacements are handled"
                actions={<BackLink href={adminUrl('/admin/returns')} label="Return requests" />}
            >
                <form onSubmit={submit} className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <Card className="p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Availability and window</h2>
                            <div className="divide-y divide-gray-100 dark:divide-gray-800">
                                <Toggle label="Enable returns" checked={form.returns_enabled} onChange={(value) => update('returns_enabled', value)} />
                            </div>
                            <div className="mt-3">
                                <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Return window (days after delivery)</label>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {WINDOW_PRESETS.map((days) => (
                                        <button
                                            key={days}
                                            type="button"
                                            onClick={() => update('return_window_days', days)}
                                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold ${Number(form.return_window_days) === days ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 dark:border-gray-700 dark:text-gray-200'}`}
                                        >
                                            {days} days
                                        </button>
                                    ))}
                                    <input
                                        type="number"
                                        min="0"
                                        max="365"
                                        value={form.return_window_days}
                                        onChange={(event) => update('return_window_days', event.target.value)}
                                        className="w-24 rounded-lg border border-gray-300 px-3 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800"
                                    />
                                </div>
                            </div>
                            <div className="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
                                <Toggle label="Allow full returns" description="Customer can return every remaining item." checked={form.allow_full_return} onChange={(value) => update('allow_full_return', value)} />
                                <Toggle label="Allow partial returns" description="Customer can return selected items or quantities." checked={form.allow_partial_return} onChange={(value) => update('allow_partial_return', value)} />
                            </div>
                        </Card>

                        <Card className="p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Request requirements</h2>
                            <div className="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
                                <Toggle label="Require a return reason" checked={form.require_reason} onChange={(value) => update('require_reason', value)} />
                                <Toggle label="Require an additional note" checked={form.require_note} onChange={(value) => update('require_note', value)} />
                                <Toggle label="Require evidence photos" checked={form.require_evidence} onChange={(value) => update('require_evidence', value)} />
                            </div>
                        </Card>

                        <Card className="p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Resolutions</h2>
                            <div className="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
                                <Toggle label="Allow refunds" checked={form.allow_refund} onChange={(value) => update('allow_refund', value)} />
                                <Toggle label="Allow exchanges" description="Same product, different variant." checked={form.allow_exchange} onChange={(value) => update('allow_exchange', value)} />
                                <Toggle label="Allow replacements" description="New order is created and linked to the return." checked={form.allow_replacement} onChange={(value) => update('allow_replacement', value)} />
                            </div>
                        </Card>

                        <Card className="p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Return shipping</h2>
                            <div className="mt-3">
                                <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Default responsibility</label>
                                <select value={form.return_shipping_responsibility} onChange={(event) => update('return_shipping_responsibility', event.target.value)} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800">
                                    <option value="customer">Customer pays return shipping</option>
                                    <option value="merchant">Merchant pays return shipping</option>
                                </select>
                            </div>
                            <div className="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
                                <Toggle label="Refund shipping fees" description="Include original shipping in refunds when applicable." checked={form.refund_shipping_fee} onChange={(value) => update('refund_shipping_fee', value)} />
                            </div>
                        </Card>

                        <Card className="p-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Return reasons</h2>
                                <button type="button" onClick={addReason} className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                    Add reason
                                </button>
                            </div>
                            <div className="mt-3 space-y-2">
                                {form.reasons.map((reason, index) => (
                                    <div key={index} className="flex flex-col gap-2 sm:flex-row">
                                        <input
                                            value={reason.label}
                                            onChange={(event) => updateReason(index, { label: event.target.value })}
                                            placeholder="Reason label"
                                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                                        />
                                        <select
                                            value={reason.shipping}
                                            onChange={(event) => updateReason(index, { shipping: event.target.value })}
                                            className="rounded-lg border border-gray-300 px-2 py-2 text-xs dark:border-gray-700 dark:bg-gray-800 sm:w-44"
                                        >
                                            <option value="merchant">Merchant pays shipping</option>
                                            <option value="customer">Customer pays shipping</option>
                                        </select>
                                        <button type="button" onClick={() => removeReason(index)} className="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600">
                                            Remove
                                        </button>
                                    </div>
                                ))}
                                {form.reasons.length === 0 && <p className="text-xs text-gray-500 dark:text-gray-400">No custom reasons — defaults will be used.</p>}
                            </div>
                        </Card>

                        <Card className="p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Customer instructions</h2>
                            <textarea
                                rows={3}
                                value={form.instructions}
                                onChange={(event) => update('instructions', event.target.value)}
                                placeholder="e.g. Pack the item securely and include the RMA number in the parcel."
                                className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                            />
                            <h2 className="mt-4 text-sm font-semibold text-gray-900 dark:text-gray-100">Policy text</h2>
                            <textarea
                                rows={4}
                                value={form.policy_text}
                                onChange={(event) => update('policy_text', event.target.value)}
                                placeholder="Short policy summary shown to customers."
                                className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                            />
                        </Card>
                    </div>

                    <div className="space-y-4">
                        <Card className="p-4">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Summary</h2>
                            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Returns {form.returns_enabled ? 'are enabled' : 'are disabled'} within {form.return_window_days} day(s) of delivery.{' '}
                                {form.allow_partial_return ? 'Partial returns are allowed.' : 'Only full returns are allowed.'}
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
