import { useState } from 'react';
import { Link, usePage, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import PerPageSelect from '@/Components/PerPageSelect';

export default function ActivityLogsIndex({ logs, filters, showPagination = true }) {
    const [logFilter, setLogFilter] = useState(filters?.log_name || '');
    const [eventFilter, setEventFilter] = useState(filters?.event || '');

    function handleFilterChange(type, value) {
        const params = { log_name: logFilter, event: eventFilter, [type]: value };
        if (type === 'log_name') setLogFilter(value);
        if (type === 'event') setEventFilter(value);
        router.get('/admin/activity-logs', params, { preserveState: true, replace: true });
    }

    const eventBadge = (event) => {
        const colors = {
            login: 'bg-green-100 text-green-800',
            logout: 'bg-gray-100 text-gray-800',
            created: 'bg-blue-100 text-blue-800',
            updated: 'bg-indigo-100 text-indigo-800',
            deleted: 'bg-red-100 text-red-800',
            order_created: 'bg-purple-100 text-purple-800',
            order_cancelled: 'bg-orange-100 text-orange-800',
            order_status_changed: 'bg-teal-100 text-teal-800',
            payment_verified: 'bg-emerald-100 text-emerald-800',
            payment_rejected: 'bg-pink-100 text-pink-800',
            payment_proof_uploaded: 'bg-cyan-100 text-cyan-800',
            suspended: 'bg-yellow-100 text-yellow-800',
            banned: 'bg-red-100 text-red-800',
            activated: 'bg-green-100 text-green-800',
        };
        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colors[event] || 'bg-gray-100 text-gray-800'}`}>
                {event?.replace(/_/g, ' ') || 'N/A'}
            </span>
        );
    };

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Activity Logs</h2>}>
            <Head title="Activity Logs" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="flex gap-4 mb-6">
                                <select
                                    value={logFilter}
                                    onChange={(e) => handleFilterChange('log_name', e.target.value)}
                                    className="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                >
                                    <option value="">All Logs</option>
                                    <option value="auth">Auth</option>
                                    <option value="user">User</option>
                                    <option value="order">Order</option>
                                </select>
                                <select
                                    value={eventFilter}
                                    onChange={(e) => handleFilterChange('event', e.target.value)}
                                    className="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                >
                                    <option value="">All Events</option>
                                    <option value="login">Login</option>
                                    <option value="logout">Logout</option>
                                    <option value="created">Created</option>
                                    <option value="updated">Updated</option>
                                    <option value="deleted">Deleted</option>
                                    <option value="order_created">Order Created</option>
                                    <option value="order_cancelled">Order Cancelled</option>
                                    <option value="order_status_changed">Order Status Changed</option>
                                    <option value="payment_verified">Payment Verified</option>
                                    <option value="payment_rejected">Payment Rejected</option>
                                    <option value="payment_proof_uploaded">Payment Proof Uploaded</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="banned">Banned</option>
                                    <option value="activated">Activated</option>
                                </select>
                            </div>

                            {/* Per Page Selector */}
                            <div className="flex justify-between items-center mt-4">
                                <PerPageSelect showTotal={true} total={logs.total} />
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Causer</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Log</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {logs.data.map((log) => (
                                            <tr key={log.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {new Date(log.created_at).toLocaleString()}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">{eventBadge(log.event)}</td>
                                                <td className="px-6 py-4 text-sm text-gray-900 max-w-md truncate">{log.description}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {log.causer ? log.causer.name : 'System'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{log.log_name}</td>
                                            </tr>
                                        ))}
                                        {logs.data.length === 0 && (
                                            <tr>
                                                <td colSpan="5" className="px-6 py-12 text-center text-gray-500">
                                                    No activity logs found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {showPagination && logs.links && (
                                <div className="mt-6">
                                    {logs.links.map((link, i) => (
                                        <button
                                            key={i}
                                            onClick={() => router.get(link.url, {}, { preserveState: true })}
                                            disabled={!link.url}
                                            className={`px-3 py-1 mx-0.5 text-sm rounded ${link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border hover:bg-gray-50'} ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
