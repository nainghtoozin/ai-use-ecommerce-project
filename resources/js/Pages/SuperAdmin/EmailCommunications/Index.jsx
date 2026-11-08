import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function EmailCommunicationsIndex({ stats, email_types, recent_campaigns }) {
    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Email Communications</h2>}>
            <Head title="Email Communications" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div className="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg p-6">
                            <p className="text-sm text-gray-500">Eligible Merchants</p>
                            <p className="mt-1 text-2xl font-semibold text-blue-600">{stats.eligible_merchants}</p>
                        </div>
                        <div className="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg p-6">
                            <p className="text-sm text-gray-500">Emails Sent</p>
                            <p className="mt-1 text-2xl font-semibold text-green-600">{stats.total_sent}</p>
                        </div>
                        <div className="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg p-6">
                            <p className="text-sm text-gray-500">Pending</p>
                            <p className="mt-1 text-2xl font-semibold text-yellow-600">{stats.pending}</p>
                        </div>
                        <div className="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg p-6">
                            <p className="text-sm text-gray-500">Failed</p>
                            <p className="mt-1 text-2xl font-semibold text-red-600">{stats.failed}</p>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <Link href="/superadmin/email-communications/compose" className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                            Compose Email
                        </Link>
                        <Link href="/superadmin/email-communications/history" className="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">
                            View History
                        </Link>
                    </div>

                    <div className="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg p-6">
                        <h3 className="text-lg font-semibold mb-4">Recent Communications</h3>
                        {recent_campaigns.length === 0 && (
                            <p className="text-sm text-gray-500">No communications yet.</p>
                        )}
                        <div className="space-y-3">
                            {recent_campaigns.map((c) => (
                                <div key={c.id} className="flex items-center justify-between border-b pb-3 last:border-0">
                                    <div>
                                        <p className="font-medium text-gray-800 dark:text-gray-200">{c.subject}</p>
                                        <p className="text-xs text-gray-500">{c.email_type} · {c.recipient_count} recipients · {new Date(c.created_at).toLocaleString()}</p>
                                    </div>
                                    <Link href={`/superadmin/email-communications/${c.id}`} className="text-sm text-blue-600 hover:underline">View</Link>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
