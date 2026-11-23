import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { BackLink, Card, PageShell } from '@/Components/Admin/AfterSalesUI';

export default function OrderWorkflow({ workflow_mode }) {
    const [mode, setMode] = useState(workflow_mode || 'standard');
    const [saving, setSaving] = useState(false);

    function submit(event) {
        event.preventDefault();
        setSaving(true);
        router.post(adminUrl('/admin/settings/order-workflow'), { workflow_mode: mode }, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    }

    return (
        <AdminLayout>
            <Head title="Order Workflow" />
            <PageShell
                title="Order Workflow"
                description="Choose how merchants progress orders through fulfillment."
                actions={<BackLink href={adminUrl('/admin/settings')} label="Settings" />}
            >
                <Card>
                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                                <input type="radio" name="workflow_mode" value="standard" checked={mode === 'standard'} onChange={() => setMode('standard')} className="mt-1" />
                                <span>
                                    <span className="block text-sm font-semibold text-gray-900 dark:text-gray-100">Standard Workflow</span>
                                    <span className="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Manage each order stage separately: Confirmed, Processing, Shipped, and Delivered.</span>
                                </span>
                            </label>
                        </div>
                        <div>
                            <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                                <input type="radio" name="workflow_mode" value="simplified" checked={mode === 'simplified'} onChange={() => setMode('simplified')} className="mt-1" />
                                <span>
                                    <span className="block text-sm font-semibold text-gray-900 dark:text-gray-100">Simplified Workflow</span>
                                    <span className="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Accept an order and start processing in one action. The system records the internal confirmation automatically.</span>
                                </span>
                            </label>
                        </div>
                        <p className="text-xs text-gray-500 dark:text-gray-400">Changing the mode does not change existing order statuses.</p>
                        <button type="submit" disabled={saving} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                            {saving ? 'Saving...' : 'Save workflow'}
                        </button>
                    </form>
                </Card>
            </PageShell>
        </AdminLayout>
    );
}
