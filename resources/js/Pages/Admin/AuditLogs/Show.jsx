import { Link, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { Shield, Globe, Monitor, Smartphone, Clock } from 'lucide-react';

const severityColors = {
    info: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    success: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    warning: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    error: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    critical: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
};

export default function AuditLogsShow({ log }) {
    const props = log.properties || {};
    const hasDeviceInfo = props.ip || props.browser || props.platform || props.device;

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Audit Log Details</h2>}>
            <Head title="Audit Log Details" />

            <div className="py-6">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {/* Header */}
                            <div className="flex items-center gap-3 mb-6 pb-4 border-b border-gray-200 dark:border-gray-700">
                                <div className="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                    <Shield className="w-5 h-5 text-gray-600 dark:text-gray-400" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{log.description}</h3>
                                    <div className="flex items-center gap-2 mt-1">
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${severityColors[log.severity] || severityColors.info}`}>
                                            {log.severity}
                                        </span>
                                        <span className="text-xs text-gray-500 dark:text-gray-400">{log.event}</span>
                                    </div>
                                </div>
                            </div>

                            <dl className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt className="text-gray-500 dark:text-gray-400 font-medium">ID</dt>
                                    <dd className="mt-1 text-gray-900 dark:text-gray-100">#{log.id}</dd>
                                </div>
                                <div>
                                    <dt className="text-gray-500 dark:text-gray-400 font-medium">Category</dt>
                                    <dd className="mt-1 text-gray-900 dark:text-gray-100">{log.category || 'N/A'}</dd>
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
                                    <dt className="text-gray-500 dark:text-gray-400 font-medium">Subject</dt>
                                    <dd className="mt-1 text-gray-900 dark:text-gray-100">
                                        {log.subject_type ? log.subject_type.split('\\').pop() : 'N/A'}
                                        {log.subject_id ? ` #${log.subject_id}` : ''}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-gray-500 dark:text-gray-400 font-medium">Timestamp</dt>
                                    <dd className="mt-1 text-gray-900 dark:text-gray-100 flex items-center gap-1">
                                        <Clock className="w-3.5 h-3.5 text-gray-400" />
                                        {new Date(log.created_at).toLocaleString()}
                                    </dd>
                                </div>
                            </dl>

                            {/* Device Info */}
                            {hasDeviceInfo && (
                                <div className="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <h4 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Session Details</h4>
                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        {props.ip && (
                                            <div className="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                                                <div className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 mb-1">
                                                    <Globe className="w-3 h-3" /> IP Address
                                                </div>
                                                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{props.ip}</p>
                                            </div>
                                        )}
                                        {props.browser && (
                                            <div className="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                                                <div className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 mb-1">
                                                    <Monitor className="w-3 h-3" /> Browser
                                                </div>
                                                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{props.browser}</p>
                                            </div>
                                        )}
                                        {props.platform && (
                                            <div className="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                                                <div className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 mb-1">
                                                    <Monitor className="w-3 h-3" /> Platform
                                                </div>
                                                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{props.platform}</p>
                                            </div>
                                        )}
                                        {props.device && (
                                            <div className="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                                                <div className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 mb-1">
                                                    <Smartphone className="w-3 h-3" /> Device
                                                </div>
                                                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{props.device}</p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Raw Properties */}
                            {log.properties && Object.keys(log.properties).length > 0 && (
                                <div className="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <dt className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Raw Data</dt>
                                    <pre className="bg-gray-50 dark:bg-gray-950 p-4 rounded-lg text-xs text-gray-700 dark:text-gray-300 overflow-x-auto">
                                        {JSON.stringify(log.properties, null, 2)}
                                    </pre>
                                </div>
                            )}

                            <div className="mt-6">
                                <Link href={adminUrl('/admin/audit-logs')} className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50">
                                    Back to Audit Logs
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
