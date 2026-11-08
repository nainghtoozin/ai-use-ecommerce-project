import { Link, router } from '@inertiajs/react';

const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    queued: 'bg-blue-100 text-blue-700',
    sending: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700',
    partially_failed: 'bg-amber-100 text-amber-700',
    failed: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-700',
    archived: 'bg-gray-100 text-gray-700',
};

export default function CampaignTable({ campaigns, showActions = true, returnTo }) {
    const viewHref = (id) => returnTo ? `/superadmin/email-communications/${id}?return_to=${encodeURIComponent(returnTo)}` : `/superadmin/email-communications/${id}`;
    const list = campaigns?.data || campaigns || [];
    return (
        <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-gray-500">
                        <th className="p-2">Subject</th>
                        <th className="p-2">Type</th>
                        <th className="p-2">Recipients</th>
                        <th className="p-2">Sent</th>
                        <th className="p-2">Failed</th>
                        <th className="p-2">Status</th>
                        <th className="p-2">Created</th>
                        {showActions && <th className="p-2">Actions</th>}
                    </tr>
                </thead>
                <tbody>
                    {list.length === 0 && (
                        <tr><td colSpan={showActions ? 8 : 7} className="p-4 text-center text-gray-500">No emails found.</td></tr>
                    )}
                    {list.map((c) => (
                        <tr key={c.id} className="border-b hover:bg-gray-50">
                            <td className="p-2 max-w-xs truncate" title={c.subject}>{c.subject}</td>
                            <td className="p-2"><span className="px-2 py-0.5 rounded bg-gray-100 text-xs">{c.email_type}</span></td>
                            <td className="p-2">{c.recipient_count}</td>
                            <td className="p-2">{c.sent_count ?? 0}</td>
                            <td className="p-2">{c.failed_count ?? 0}</td>
                            <td className="p-2"><span className={`px-2 py-0.5 rounded text-xs ${statusColors[c.status] || 'bg-gray-100 text-gray-700'}`}>{c.status}{c.archived_at ? ' · Archived' : ''}</span></td>
                            <td className="p-2">{new Date(c.created_at).toLocaleString()}</td>
                            {showActions && (
                                <td className="p-2">
                                    <Link href={viewHref(c.id)} className="text-blue-600 hover:underline text-xs">View</Link>
                                    {c.status === 'draft' && <Link href={`/superadmin/email-communications//edit`} className="ml-2 text-blue-600 hover:underline text-xs">Edit</Link>}
                                    {c.archived_at && <button onClick={() => router.post(`/superadmin/email-communications//restore`)} className="ml-2 text-blue-600 hover:underline text-xs">Restore</button>}
                                    {c.archived_at && !c.recipients?.data?.some((r) => r.status === 'sent') && <button onClick={() => { if (confirm('Delete permanently?')) router.delete(`/superadmin/email-communications/`); }} className="ml-2 text-red-600 hover:underline text-xs">Delete</button>}
                                    {!c.archived_at && ['completed', 'partially_failed', 'failed', 'cancelled'].includes(c.status) && <button onClick={() => router.post(`/superadmin/email-communications//archive`)} className="ml-2 text-gray-600 hover:underline text-xs">Archive</button>}
                                    {!c.archived_at && ['partially_failed', 'failed'].includes(c.status) && <button onClick={() => router.post(`/superadmin/email-communications//retry-failed`)} className="ml-2 text-blue-600 hover:underline text-xs">Retry</button>}
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}


