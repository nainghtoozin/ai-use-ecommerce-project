import { Head, usePage, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

export default function Index({ storeName, storeSlug, result }) {
    const { auth } = usePage().props;
    const [confirmation, setConfirmation] = useState('');
    const [finalConfirm, setFinalConfirm] = useState(false);
    const [password, setPassword] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        setSubmitting(true);
        router.post(window.location.pathname, {
            confirmation,
            accept: finalConfirm,
            password: password || undefined,
        }, {
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <AdminLayout>
            <Head title="Store Data Reset" />
            <div className="p-6 max-w-3xl mx-auto">
                <h1 className="text-2xl font-bold mb-4">Store Data Management</h1>
                <div className="border border-red-300 rounded p-4 bg-red-50">
                    <h2 className="text-lg font-semibold text-red-700">Danger Zone: Reset Store Data</h2>
                    <p className="mt-2 text-sm text-red-700">
                        This permanently deletes store business data (products, orders, customers, inventory, promotions, etc.).
                        Your store identity, subscription, billing, settings and login remain intact.
                    </p>
                    <p className="mt-2 text-sm text-red-700">
                        Download a backup first. This action cannot be undone.
                    </p>
                    <form onSubmit={submit} className="mt-4 space-y-3">
                        <div>
                            <label className="block text-sm font-medium">Type store name or slug to confirm</label>
                            <input
                                type="text"
                                value={confirmation}
                                onChange={(e) => setConfirmation(e.target.value)}
                                className="border rounded px-3 py-2 w-full"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium">Password (optional)</label>
                            <input
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                className="border rounded px-3 py-2 w-full"
                            />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={finalConfirm}
                                onChange={(e) => setFinalConfirm(e.target.checked)}
                            />
                            I understand this cannot be undone
                        </label>
                        <button
                            type="submit"
                            disabled={submitting}
                            className="bg-red-600 text-white px-4 py-2 rounded"
                        >
                            Reset Store Data
                        </button>
                    </form>
                </div>
                {result && (
                    <div className="mt-4 border rounded p-4">
                        <h3 className="font-semibold">Reset completed</h3>
                        <p>Total deleted: {result.total_deleted}</p>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}