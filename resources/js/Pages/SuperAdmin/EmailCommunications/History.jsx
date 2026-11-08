import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import CampaignTable from '@/Components/SuperAdmin/EmailCommunications/CampaignTable';

export default function History({ campaigns, filters, email_types }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [emailType, setEmailType] = useState(filters?.email_type || '');
    const [status, setStatus] = useState(filters?.status || '');
    const [from, setFrom] = useState(filters?.from || '');
    const [to, setTo] = useState(filters?.to || '');
    const [archived, setArchived] = useState(filters?.archived || '');

    function apply() {
        router.get('/superadmin/email-communications/history', {
            search, email_type: emailType, status, from, to, archived,
        }, { preserveState: true, replace: true });
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Email History</h2>}>
            <Head title="Email History" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div className="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg p-6">
                        <div className="flex flex-wrap gap-2 mb-4">
                            <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Subject..." className="border rounded px-3 py-1.5 text-sm" />
                            <select value={emailType} onChange={(e) => setEmailType(e.target.value)} className="border rounded px-3 py-1.5 text-sm">
                                <option value="">All types</option>
                                {Object.entries(email_types || {}).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                            <select value={status} onChange={(e) => setStatus(e.target.value)} className="border rounded px-3 py-1.5 text-sm">
                                <option value="">All statuses</option>
                                <option value="queued">Queued</option>
                                <option value="sending">Sending</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                            </select>
                            <select value={archived} onChange={(e) => setArchived(e.target.value)} className="border rounded px-3 py-1.5 text-sm">
                                <option value="">Active</option>
                                <option value="1">Archived</option>
                            </select>
                            <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="border rounded px-3 py-1.5 text-sm" />
                            <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="border rounded px-3 py-1.5 text-sm" />
                            <button onClick={apply} className="px-3 py-1.5 bg-blue-600 text-white rounded text-sm">Filter</button>
                        </div>

                        <CampaignTable campaigns={campaigns} returnTo="/superadmin/email-communications/history" />

                        {campaigns?.links && (
                            <div className="flex gap-1 flex-wrap text-sm mt-4">
                                {campaigns.links.map((link, i) => (
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


