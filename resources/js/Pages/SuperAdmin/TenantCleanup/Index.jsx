import { router, Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { AlertTriangle, Clock, ShieldAlert, CheckCircle2 } from 'lucide-react';

const STATUS_TONE = {
    grace: 'bg-blue-100 text-blue-700',
    restricted: 'bg-red-100 text-red-700',
    flagged: 'bg-amber-100 text-amber-700',
    cancelled: 'bg-gray-100 text-gray-600',
    archived: 'bg-gray-100 text-gray-500',
};

const STATUS_LABEL = {
    grace: 'Grace period',
    restricted: 'Restricted',
    flagged: 'Flagged for review',
    cancelled: 'Cancelled',
    archived: 'Archived',
};

export default function TenantCleanupIndex({ cleanups, filters, counts = {}, purgeEnabled = false }) {
    const data = cleanups?.data || [];
    const activeStatus = filters?.status ?? null;

    const tabs = [
        { value: 'grace', label: 'Grace', count: counts.grace, icon: Clock },
        { value: 'restricted', label: 'Restricted', count: counts.restricted, icon: ShieldAlert },
        { value: 'flagged', label: 'Flagged', count: counts.flagged, icon: AlertTriangle },
        { value: 'cancelled', label: 'Cancelled', count: counts.cancelled, icon: CheckCircle2 },
        { value: null, label: 'All', count: null, icon: null },
    ];

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Tenant Cleanup</h2>}>
            <Head title="Tenant Cleanup" />
            <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Tenants Awaiting Cleanup</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Expired subscriptions past their 15-day grace period. Tenants are restricted, never deleted, pending review.
                        </p>
                    </div>
                    <Link href="/superadmin/subscriptions" className="text-sm text-blue-600 hover:text-blue-700">Subscriptions</Link>
                </div>

                {!purgeEnabled && (
                    <div className="mb-4 rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-3 text-xs text-gray-500 dark:text-gray-400">
                        Permanent purge is disabled. Cleanup only restricts tenants and retains all billing history.
                    </div>
                )}

                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <div className="p-3 border-b border-gray-100 dark:border-gray-800 flex flex-wrap gap-2">
                        {tabs.map((tab) => {
                            const active = activeStatus === tab.value;
                            return (
                                <button
                                    key={tab.label}
                                    onClick={() => router.get('/superadmin/tenant-cleanups', tab.value ? { status: tab.value } : {}, { preserveState: true })}
                                    className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-full ${
                                        active ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'
                                    }`}
                                >
                                    {tab.label}
                                    {tab.count != null && <span className="opacity-75">({tab.count})</span>}
                                </button>
                            );
                        })}
                    </div>

                    {data.length === 0 ? (
                        <div className="p-10 text-center text-sm text-gray-500 dark:text-gray-400">No tenant cleanups found.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                <thead className="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        {['Store', 'Plan', 'Status', 'Expired', 'Grace Ends', 'Restricted', 'Reason'].map((h) => (
                                            <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {data.map((c) => (
                                        <tr key={c.id} className="hover:bg-gray-50 dark:hover:bg-gray-950/50">
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{c.tenant || '—'}</p>
                                                <p className="text-xs text-gray-400 dark:text-gray-500">{c.tenant_status}</p>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">{c.plan || '—'}</td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${STATUS_TONE[c.status] || 'bg-gray-100 text-gray-600'}`}>
                                                    {STATUS_LABEL[c.status] || c.status}
                                                </span>
                                                {c.status === 'grace' && c.days_remaining != null && (
                                                    <span className="ml-2 text-xs text-gray-400 dark:text-gray-500">{c.days_remaining}d left</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{c.expires_at || '—'}</td>
                                            <td className="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{c.grace_ends_at || '—'}</td>
                                            <td className="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{c.restricted_at || '—'}</td>
                                            <td className="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 max-w-xs">
                                                {c.reason || '—'}
                                                {c.last_error && <span className="block text-red-500 mt-0.5">Error: {c.last_error}</span>}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {cleanups?.links && cleanups.links.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1.5">
                        {cleanups.links.map((link, i) => (
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
