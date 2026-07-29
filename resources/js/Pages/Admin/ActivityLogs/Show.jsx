import { Link, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

export default function ActivityLogsShow({ log }) {
    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Activity Log Details</h2>}>
            <Head title="Activity Log Details" />

            <div className="py-6">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <dl className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt className="text-gray-500 dark:text-gray-400 font-medium">ID</dt>
                                    <dd className="mt-1 text-gray-900 dark:text-gray-100">#{log.id}</dd>
                                </div>
                                <div>
                                    <dt className="text-gray-500 dark:text-gray-400 font-medium">Event</dt>
                                    <dd className="mt-1 text-gray-900 dark:text-gray-100">{log.event}</dd>
                                </div>
                                <div className="md:col-span-2">
                                    <dt className="text-gray-500 dark:text-gray-400 font-medium">Description</dt>
                                    <dd className="mt-1 text-gray-900 dark:text-gray-100">{log.description}</dd>
                                </div>
                                <div>
                                    <dt className="text-gray-500 dark:text-gray-400 font-medium">Log Name</dt>
                                    <dd className="mt-1 text-gray-900 dark:text-gray-100">{log.log_name}</dd>
                                </div>
                                <div>
                                    <dt className="text-gray-500 dark:text-gray-400 font-medium">Performed By</dt>
                                    <dd className="mt-1 text-gray-900 dark:text-gray-100">
                                        {log.impersonator
                                            ? `${log.impersonator.name} (${log.impersonator.email})`
                                            : log.causer
                                                ? `${log.causer.name} (${log.causer.email})`
                                                : 'System'}
                                    </dd>
                                </div>
                                {log.impersonated_user && (
                                    <div>
                                        <dt className="text-gray-500 dark:text-gray-400 font-medium">Acting As</dt>
                                        <dd className="mt-1 text-gray-900 dark:text-gray-100">{log.impersonated_user.name} ({log.impersonated_user.email})</dd>
                                    </div>
                                )}
                                <div>
                                    <dt className="text-gray-500 dark:text-gray-400 font-medium">Subject Type</dt>
                                    <dd className="mt-1 text-gray-900 dark:text-gray-100">{log.subject_type ? log.subject_type.split('\\').pop() : 'N/A'}</dd>
                                </div>
                                <div>
                                    <dt className="text-gray-500 dark:text-gray-400 font-medium">Subject ID</dt>
                                    <dd className="mt-1 text-gray-900 dark:text-gray-100">{log.subject_id || 'N/A'}</dd>
                                </div>
                                <div className="md:col-span-2">
                                    <dt className="text-gray-500 dark:text-gray-400 font-medium">Date/Time</dt>
                                    <dd className="mt-1 text-gray-900 dark:text-gray-100">{new Date(log.created_at).toLocaleString()}</dd>
                                </div>
                                {log.properties && (
                                    <div className="md:col-span-2">
                                        <dt className="text-gray-500 dark:text-gray-400 font-medium">Properties</dt>
                                        <dd className="mt-1">
                                            <pre className="bg-gray-50 dark:bg-gray-950 p-4 rounded-lg text-xs text-gray-700 dark:text-gray-300 overflow-x-auto">
                                                {JSON.stringify(log.properties, null, 2)}
                                            </pre>
                                        </dd>
                                    </div>
                                )}
                            </dl>

                            <div className="mt-6">
                                <Link href={adminUrl('/admin/activity-logs')} className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50">
                                    <i className="bi bi-arrow-left mr-1"></i> Back to Logs
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
