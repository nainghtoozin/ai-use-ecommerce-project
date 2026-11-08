import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Show({ campaign, recipients, counts, filters }) {
    const [status, setStatus] = useState(filters?.status || '');

    function applyStatus() {
        router.get(`/superadmin/email-communications/${campaign.id}`, { status }, { preserveState: true, replace: true });
    }

    function retryFailed() {
        router.post(`/superadmin/email-communications/${campaign.id}/retry-failed`, {}, { preserveState: true });
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Campaign Details</h2>}>
            <Head title="Campaign Details" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div className="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg p-6">
                        <h3 className="text-lg font-semibold">{campaign.subject}</h3>
                        <p className="text-sm text-gray-500">{campaign.email_type} · {campaign.status} · {counts.total} recipients</p>
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                            <div className="p-3 bg-gray-50 rounded"><p className="text-xs text-gray-500">Total</p><p className="text-lg font-semibold">{counts.total}</p></div>
                            <div className="p-3 bg-green-50 rounded"><p className="text-xs text-gray-500">Sent</p><p className="text-lg font-semibold text-green-600">{counts.sent}</p></div>
                            <div className="p-3 bg-yellow-50 rounded"><p className="text-xs text-gray-500">Pending</p><p className="text-lg font-semibold text-yellow-600">{counts.pending}</p></div>
                            <div className="p-3 bg-red-50 rounded"><p className="text-xs text-gray-500">Failed</p><p className="text-lg font-semibold text-red-600">{counts.failed}</p></div>
                        </div>
                        <div className="mt-4 text-sm text-gray-600 whitespace-pre-wrap">{campaign.message}</div>
                    </div>

                    <div className="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg p-6">
                        <div className="flex items-center gap-2 mb-4">
                            <select value={status} onChange={(e) => setStatus(e.target.value)} className="border rounded px-3 py-1.5 text-sm">
                                <option value="">All</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="sent">Sent</option>
                                <option value="failed">Failed</option>
                            </select>
                            <button onClick={applyStatus} className="px-3 py-1.5 bg-gray-100 rounded text-sm">Filter</button>
                            {counts.failed > 0 && (
                                <button onClick={retryFailed} className="px-3 py-1.5 bg-blue-600 text-white rounded text-sm">Retry Failed</button>
                            )}
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="p-2 text-left">Merchant</th>
                                        <th className="p-2 text-left">Email</th>
                                        <th className="p-2 text-left">Status</th>
                                        <th className="p-2 text-left">Attempts</th>
                                        <th className="p-2 text-left">Sent At</th>
                                        <th className="p-2 text-left">Failure</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(recipients?.data || []).map((r) => (
                                        <tr key={r.id} className="border-b">
                                            <td className="p-2">{r.recipient_name}</td>
                                            <td className="p-2">{r.recipient_email}</td>
                                            <td className="p-2">{r.status}</td>
                                            <td className="p-2">{r.attempts}</td>
                                            <td className="p-2">{r.sent_at ? new Date(r.sent_at).toLocaleString() : '-'}</td>
                                            <td className="p-2 text-xs text-red-600">{r.failure_reason || ''}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {recipients?.links && (
                            <div className="flex gap-1 flex-wrap text-sm mt-4">
                                {recipients.links.map((link, i) => (
                                    <button key={i} disabled={!link.url}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                        className={`px-2 py-1 rounded ${link.active ? 'bg-blue-600 text-white' : 'bg-gray-100'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }} />
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
