import { useState } from 'react';
import { Link, usePage, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import PerPageSelect from '@/Components/PerPageSelect';
import { adminUrl } from '@/Utils/adminUrl';
import {
    Package, Archive, Tag, Users, Globe, Settings,
    CreditCard, Bell, Server, Clock, Filter, Search, ChevronDown,
    ChevronRight, X, Info, CheckCircle, AlertTriangle, XCircle,
    AlertCircle, Eye, Calendar, Monitor, Smartphone, Laptop,
    ShoppingCart, FileText, MessageSquare, Send, User
} from 'lucide-react';

const CATEGORY_ICONS = {
    orders: ShoppingCart,
    inventory: Archive,
    products: Tag,
    users: Users,
    website: Globe,
    settings: Settings,
    billing: CreditCard,
    notifications: Bell,
    system: Server,
};

const CATEGORY_COLORS = {
    orders: 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400',
    inventory: 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400',
    products: 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400',
    users: 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400',
    website: 'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-600 dark:text-cyan-400',
    settings: 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400',
    billing: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400',
    notifications: 'bg-pink-100 dark:bg-pink-900/30 text-pink-600 dark:text-pink-400',
    system: 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400',
};

const SEVERITY_CONFIG = {
    info: { color: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300', icon: Info, label: 'Info' },
    success: { color: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300', icon: CheckCircle, label: 'Success' },
    warning: { color: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300', icon: AlertTriangle, label: 'Warning' },
    error: { color: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300', icon: XCircle, label: 'Error' },
    critical: { color: 'bg-red-200 dark:bg-red-900/50 text-red-800 dark:text-red-200', icon: AlertCircle, label: 'Critical' },
};

function DetailModal({ log, onClose }) {
    if (!log) return null;

    const severity = SEVERITY_CONFIG[log.severity] || SEVERITY_CONFIG.info;
    const SeverityIcon = severity.icon;
    const CategoryIcon = CATEGORY_ICONS[log.category] || Server;
    const categoryColor = CATEGORY_COLORS[log.category] || CATEGORY_COLORS.system;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="relative bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                    <div className="flex items-center gap-3">
                        <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${categoryColor}`}>
                            <CategoryIcon className="w-5 h-5" />
                        </div>
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Audit Trail Details</h3>
                            <p className="text-sm text-gray-500 dark:text-gray-400">#{log.id}</p>
                        </div>
                    </div>
                    <button onClick={onClose} className="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        <X className="w-5 h-5 text-gray-400" />
                    </button>
                </div>

                <div className="overflow-y-auto p-6 space-y-6">
                    {/* Event & Severity */}
                    <div className="flex items-center gap-3 flex-wrap">
                        <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium ${severity.color}`}>
                            <SeverityIcon className="w-4 h-4" />
                            {severity.label}
                        </span>
                        <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium ${categoryColor}`}>
                            <CategoryIcon className="w-4 h-4" />
                            {log.category || 'System'}
                        </span>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            Event: <span className="font-medium text-gray-900 dark:text-white">{log.event}</span>
                        </span>
                    </div>

                    {/* Description */}
                    <div className="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-4">
                        <p className="text-sm text-gray-900 dark:text-white">{log.description}</p>
                    </div>

                    {/* Details Grid */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <p className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                <Clock className="w-3 h-3" /> Timestamp
                            </p>
                            <p className="text-sm font-medium text-gray-900 dark:text-white">{new Date(log.created_at).toLocaleString()}</p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                <User className="w-3 h-3" /> Performed By
                            </p>
                            <p className="text-sm font-medium text-gray-900 dark:text-white">
                                {log.impersonator ? log.impersonator.name : log.causer ? log.causer.name : 'System'}
                            </p>
                        </div>
                        {log.properties?.ip && (
                            <div className="space-y-1">
                                <p className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    <Globe className="w-3 h-3" /> IP Address
                                </p>
                                <p className="text-sm font-medium text-gray-900 dark:text-white font-mono">{log.properties.ip}</p>
                            </div>
                        )}
                        {log.properties?.browser && (
                            <div className="space-y-1">
                                <p className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    <Monitor className="w-3 h-3" /> Browser
                                </p>
                                <p className="text-sm font-medium text-gray-900 dark:text-white">{log.properties.browser}</p>
                            </div>
                        )}
                        {log.properties?.platform && (
                            <div className="space-y-1">
                                <p className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    <Laptop className="w-3 h-3" /> Platform
                                </p>
                                <p className="text-sm font-medium text-gray-900 dark:text-white">{log.properties.platform}</p>
                            </div>
                        )}
                        {log.properties?.device && (
                            <div className="space-y-1">
                                <p className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    <Smartphone className="w-3 h-3" /> Device
                                </p>
                                <p className="text-sm font-medium text-gray-900 dark:text-white">{log.properties.device}</p>
                            </div>
                        )}
                        {log.subject_type && (
                            <div className="space-y-1">
                                <p className="text-xs text-gray-500 dark:text-gray-400">Related Model</p>
                                <p className="text-sm font-medium text-gray-900 dark:text-white">{log.subject_type.split('\\').pop()}</p>
                            </div>
                        )}
                        {log.subject_id && (
                            <div className="space-y-1">
                                <p className="text-xs text-gray-500 dark:text-gray-400">Subject ID</p>
                                <p className="text-sm font-medium text-gray-900 dark:text-white">#{log.subject_id}</p>
                            </div>
                        )}
                    </div>

                    {/* Properties JSON */}
                    {log.properties && Object.keys(log.properties).length > 0 && (
                        <div>
                            <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Properties</p>
                            <pre className="bg-gray-900 dark:bg-gray-950 p-4 rounded-xl text-xs text-gray-300 overflow-x-auto">
                                {JSON.stringify(log.properties, null, 2)}
                            </pre>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function TimelineItem({ log, onClick }) {
    const CategoryIcon = CATEGORY_ICONS[log.category] || Server;
    const categoryColor = CATEGORY_COLORS[log.category] || CATEGORY_COLORS.system;
    const severity = SEVERITY_CONFIG[log.severity] || SEVERITY_CONFIG.info;

    return (
        <div className="flex gap-4 group cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 rounded-xl p-3 -mx-3 transition-colors" onClick={() => onClick(log)}>
            {/* Timeline line */}
            <div className="flex flex-col items-center">
                <div className={`w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 ${categoryColor}`}>
                    <CategoryIcon className="w-5 h-5" />
                </div>
                <div className="w-px flex-1 bg-gray-200 dark:bg-gray-800 mt-2" />
            </div>

            {/* Content */}
            <div className="flex-1 pb-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                            <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium ${severity.color}`}>
                                {severity.label}
                            </span>
                            <span className="text-xs text-gray-500 dark:text-gray-400">{log.category || 'System'}</span>
                        </div>
                        <p className="text-sm font-medium text-gray-900 dark:text-white truncate">{log.description}</p>
                        <div className="flex items-center gap-3 mt-1.5">
                            <span className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                <User className="w-3 h-3" />
                                {log.impersonator ? log.impersonator.name : log.causer ? log.causer.name : 'System'}
                            </span>
                            {log.properties?.ip && (
                                <span className="text-xs text-gray-500 dark:text-gray-400 font-mono">{log.properties.ip}</span>
                            )}
                            {log.properties?.browser && (
                                <span className="text-xs text-gray-500 dark:text-gray-400">{log.properties.browser}</span>
                            )}
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">
                            {new Date(log.created_at).toLocaleString()}
                        </span>
                        <ChevronRight className="w-4 h-4 text-gray-400 dark:text-gray-600 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function ActivityLogsIndex({ logs, filters, showPagination = true, categories = {}, severities = {} }) {
    const [selectedLog, setSelectedLog] = useState(null);
    const [showFilters, setShowFilters] = useState(false);
    const [localFilters, setLocalFilters] = useState({
        log_name: filters?.log_name || '',
        event: filters?.event || '',
        category: filters?.category || '',
        severity: filters?.severity || '',
        search: filters?.search || '',
        date_from: filters?.date_from || '',
        date_to: filters?.date_to || '',
    });

    function handleFilterChange(key, value) {
        const newFilters = { ...localFilters, [key]: value };
        setLocalFilters(newFilters);
        router.get(adminUrl('/admin/activity-logs'), newFilters, { preserveState: true, replace: true });
    }

    function clearFilters() {
        const emptyFilters = { category: '', severity: '', event: '', search: '', date_from: '', date_to: '' };
        setLocalFilters(emptyFilters);
        router.get(adminUrl('/admin/activity-logs'), {}, { preserveState: true, replace: true });
    }

    const hasActiveFilters = Object.values(localFilters).some(v => v !== '');

    return (
        <AdminLayout>
            <Head title="Activity Log" />

            <div className="w-full max-w-[1400px] mx-auto px-4 lg:px-6 py-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900 dark:text-white">Activity Log</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Business and operations activities</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => setShowFilters(!showFilters)}
                            className={`inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl border transition-colors ${
                                showFilters || hasActiveFilters
                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
                                    : 'border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
                            }`}
                        >
                            <Filter className="w-4 h-4" />
                            Filters
                            {hasActiveFilters && (
                                <span className="w-5 h-5 bg-blue-600 text-white rounded-full text-xs flex items-center justify-center">
                                    {Object.values(localFilters).filter(v => v !== '').length}
                                </span>
                            )}
                        </button>
                        <PerPageSelect showTotal={true} total={logs.total} />
                    </div>
                </div>

                {/* Filters */}
                {showFilters && (
                    <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-5">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-sm font-semibold text-gray-900 dark:text-white">Filters</h3>
                            {hasActiveFilters && (
                                <button onClick={clearFilters} className="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                                    Clear all
                                </button>
                            )}
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Search</label>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                    <input
                                        type="text"
                                        value={localFilters.search}
                                        onChange={(e) => handleFilterChange('search', e.target.value)}
                                        placeholder="Search descriptions..."
                                        className="w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Category</label>
                                <select
                                    value={localFilters.category}
                                    onChange={(e) => handleFilterChange('category', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">All Categories</option>
                                    {Object.entries(categories).map(([key, label]) => (
                                        <option key={key} value={key}>{label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Severity</label>
                                <select
                                    value={localFilters.severity}
                                    onChange={(e) => handleFilterChange('severity', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">All Severities</option>
                                    {Object.entries(severities).map(([key, label]) => (
                                        <option key={key} value={key}>{label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Date Range</label>
                                <div className="flex gap-2">
                                    <input
                                        type="date"
                                        value={localFilters.date_from}
                                        onChange={(e) => handleFilterChange('date_from', e.target.value)}
                                        className="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                    />
                                    <input
                                        type="date"
                                        value={localFilters.date_to}
                                        onChange={(e) => handleFilterChange('date_to', e.target.value)}
                                        className="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Timeline */}
                <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <div className="p-5">
                        {logs.data.length > 0 ? (
                            <div className="space-y-1">
                                {logs.data.map((log) => (
                                    <TimelineItem key={log.id} log={log} onClick={setSelectedLog} />
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-16">
                                <Server className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                                <p className="text-sm font-medium text-gray-900 dark:text-white">No activity logs found</p>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {hasActiveFilters ? 'Try adjusting your filters' : 'Activity will appear here as events occur'}
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Pagination */}
                    {showPagination && logs.links && (
                        <div className="px-5 py-4 border-t border-gray-200 dark:border-gray-800">
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Showing {logs.from} to {logs.to} of {logs.total} entries
                                </p>
                                <div className="flex gap-1">
                                    {logs.links.map((link, i) => (
                                        <button
                                            key={i}
                                            onClick={() => router.get(link.url, {}, { preserveState: true })}
                                            disabled={!link.url}
                                            className={`px-3 py-1.5 text-sm rounded-lg transition-colors ${
                                                link.active
                                                    ? 'bg-blue-600 text-white'
                                                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'
                                            } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Detail Modal */}
            {selectedLog && <DetailModal log={selectedLog} onClose={() => setSelectedLog(null)} />}
        </AdminLayout>
    );
}
