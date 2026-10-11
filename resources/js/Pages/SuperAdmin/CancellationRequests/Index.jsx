import { router, Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const STATUS_TONE = {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-red-100 text-red-700',
    rejected: 'bg-gray-100 text-gray-700',
    withdrawn: 'bg-gray-100 text-gray-500',
};

export default function CancellationRequestsIndex({ requests, filters, reasons }) {
    function review(request, action) {
        const note = window.prompt(action === 'approve' ? 'Optional review note:' : 'Reason for rejecting (optional):') || '';
        router.post(`/superadmin/cancellation-requests/${request.id}/review`, { action, review_note: note }, { preserveScroll: true });
    }

    const data = requests?.data || [];

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Cancellation Requests</h2>}>
            <Head title="Cancellation Requests" />
            <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Cancellation Requests</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Review merchant subscription cancellation requests.</p>
                    </div>
                    <Link href="/superadmin/subscriptions" className="text-sm text-blue-600 hover:text-blue-700">Subscriptions</Link>
                </div>

                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <div className="p-3 border-b border-gray-100 dark:border-gray-800 flex gap-2">
                        {['pending', 'approved', 'rejected', ''].map((s) => (
                            <button
                                key={s || 'all'}
                                onClick={() => router.get('/superadmin/cancellation-requests', s ? { status: s } : {}, { preserveState: true })}
                                className={`px-3 py-1.5 text-xs font-medium rounded-full ${(filters?.status ?? 'pending') === s ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'}`}
                            >
                                {s ? s.charAt(0).toUpperCase() + s.slice(1) : 'All'}
                            </button>
                        ))}
                    </div>

                    {data.length === 0 ? (
                        <div className="p-10 text-center text-sm text-gray-500 dark:text-gray-400">No cancellation requests found.</div>
                    ) : (
                        <div className="divide-y divide-gray-100 dark:divide-gray-800">
                            {data.map((r) => (
                                <div key={r.id} className="p-4 flex flex-col sm:flex-row sm:items-start gap-3">
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span className="font-semibold text-gray-900 dark:text-gray-100">{r.tenant || 'Store'}</span>
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium ${STATUS_TONE[r.status] || 'bg-gray-100 text-gray-600'}`}>{r.status}</span>
                                        </div>
                                        <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                            <span className="font-medium">{r.reason_label}</span>
                                            {r.comment ? ` — ${r.comment}` : ''}
                                        </p>
                                        <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                            Submitted {r.created_at}
                                            {r.reviewed_at ? ` · Reviewed ${r.reviewed_at}` : ''}
                                            {r.review_note ? ` · Note: ${r.review_note}` : ''}
                                        </p>
                                    </div>
                                    {r.status === 'pending' && (
                                        <div className="flex gap-2 flex-shrink-0">
                                            <button onClick={() => review(r, 'approve')} className="px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700">Approve</button>
                                            <button onClick={() => review(r, 'reject')} className="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-medium rounded-lg hover:bg-gray-200">Reject</button>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {requests?.links && requests.links.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1.5">
                        {requests.links.map((link, i) => (
                            <button
                                key={i}
                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                disabled={!link.url}
                                className={`min-w-[36px] h-9 px-3 text-sm rounded-lg ${link.active ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-600 border border-gray-200 dark:border-gray-700'} ${!link.url ? 'opacity-40 cursor-not-allowed' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
