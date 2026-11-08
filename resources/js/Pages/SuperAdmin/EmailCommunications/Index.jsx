import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import CampaignTable from '@/Components/SuperAdmin/EmailCommunications/CampaignTable';

const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    queued: 'bg-blue-100 text-blue-700',
    sending: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700',
    partially_failed: 'bg-amber-100 text-amber-700',
    failed: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-700',
};

export default function EmailCommunicationsIndex({ stats, email_types, campaigns, filters }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [emailType, setEmailType] = useState(filters?.email_type || '');
    const [status, setStatus] = useState(filters?.status || '');
    const [archived, setArchived] = useState(filters?.archived || '');
    const [from, setFrom] = useState(filters?.from || '');
    const [to, setTo] = useState(filters?.to || '');

    function apply() {
        router.get('/superadmin/email-communications', {
            search, email_type: emailType, status, archived, from, to, per_page: filters?.per_page || 10,
        }, { preserveState: true, replace: true });
    }

    function clear() {
        setSearch(''); setEmailType(''); setStatus(''); setArchived(''); setFrom(''); setTo('');
        router.get('/superadmin/email-communications', {}, { preserveState: true, replace: true });
    }

    const list = campaigns?.data || [];

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Email Communications</h2>}>
            <Head title="Email Communications" />

            <div className="py-4">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="text-sm text-gray-500">Manage merchant communications and monitor email delivery.</p>
                        </div>
                        <div className="flex gap-2">
                            <Link href="/superadmin/email-communications/compose" className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                                Compose Email
                            </Link>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-5">
                        <div className="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg p-4">
                            <p className="text-xs text-gray-500">Eligible Merchants</p>
                            <p className="mt-1 text-xl font-semibold text-blue-600">{stats.eligible_merchants}</p>
                        </div>
                        <div className="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg p-4">
                            <p className="text-xs text-gray-500">Total Emails</p>
                            <p className="mt-1 text-xl font-semibold text-indigo-600">{stats.total_campaigns}</p>
                        </div>
                        <div className="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg p-4">
                            <p className="text-xs text-gray-500">Emails Sent</p>
                            <p className="mt-1 text-xl font-semibold text-green-600">{stats.total_sent}</p>
                        </div>
                        <div className="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg p-4">
                            <p className="text-xs text-gray-500">Pending / Sending</p>
                            <p className="mt-1 text-xl font-semibold text-amber-600">{stats.pending}</p>
                        </div>
                        <div className="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg p-4">
                            <p className="text-xs text-gray-500">Failed</p>
                            <p className="mt-1 text-xl font-semibold text-red-600">{stats.failed}</p>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg p-4 space-y-3">
                        <div className="flex flex-wrap gap-2">
                            <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search subject..." className="border rounded px-3 py-1.5 text-sm" />
                            <select value={emailType} onChange={(e) => setEmailType(e.target.value)} className="border rounded px-3 py-1.5 text-sm">
                                <option value="">All types</option>
                                {Object.entries(email_types || {}).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                            <select value={status} onChange={(e) => setStatus(e.target.value)} className="border rounded px-3 py-1.5 text-sm">
                                <option value="">All statuses</option>
                                <option value="draft">Draft</option>
                                <option value="queued">Queued</option>
                                <option value="sending">Sending</option>
                                <option value="completed">Completed</option>
                                <option value="partially_failed">Partially Failed</option>
                                <option value="failed">Failed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <select value={archived} onChange={(e) => setArchived(e.target.value)} className="border rounded px-3 py-1.5 text-sm">
                                <option value="">Active</option>
                                <option value="1">Archived</option>
                            </select>
                            <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="border rounded px-3 py-1.5 text-sm" />
                            <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="border rounded px-3 py-1.5 text-sm" />
                            <button onClick={apply} className="px-3 py-1.5 bg-blue-600 text-white rounded text-sm">Apply</button>
                            <button onClick={clear} className="px-3 py-1.5 bg-gray-100 rounded text-sm">Clear</button>
                        </div>

                        <CampaignTable campaigns={campaigns} returnTo="/superadmin/email-communications" />

                        {campaigns?.links && (
                            <div className="flex items-center justify-between text-sm mt-3">
                                <span>Total: {campaigns.total}</span>
                                <div className="flex gap-1 flex-wrap">
                                    {campaigns.links.map((link, i) => (
                                        <button key={i} disabled={!link.url}
                                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                            className={`px-2 py-1 rounded ${link.active ? 'bg-blue-600 text-white' : 'bg-gray-100'}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }} />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}


