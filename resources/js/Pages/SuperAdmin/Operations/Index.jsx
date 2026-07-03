import { useState, useEffect, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    Search, X, Filter, Clock, Check, XCircle,
    Zap, Activity, Server, AlertTriangle,
    Eye, FileText, Download, Globe,
    Wifi, WifiOff, ChevronRight,
    Building2, RefreshCw, Loader2,
} from 'lucide-react';

const webhookStatusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'received', label: 'Received' },
    { value: 'processing', label: 'Processing' },
    { value: 'processed', label: 'Processed' },
    { value: 'failed', label: 'Failed' },
    { value: 'duplicate', label: 'Duplicate' },
    { value: 'unhandled', label: 'Unhandled' },
];

const statusConfig = {
    received: { label: 'Received', classes: 'bg-blue-100 text-blue-700' },
    processing: { label: 'Processing', classes: 'bg-amber-100 text-amber-700' },
    processed: { label: 'Processed', classes: 'bg-emerald-100 text-emerald-700' },
    failed: { label: 'Failed', classes: 'bg-red-100 text-red-700' },
    duplicate: { label: 'Duplicate', classes: 'bg-gray-100 text-gray-600' },
    unhandled: { label: 'Unhandled', classes: 'bg-purple-100 text-purple-700' },
};

function StatusBadge({ status }) {
    const cfg = statusConfig[status] || statusConfig.received;
    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${cfg.classes}`}>
            {cfg.label}
        </span>
    );
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function StatCard({ icon: Icon, label, value, color, subtitle }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5">
            <div className="flex items-center gap-4">
                <div className={`w-12 h-12 rounded-xl ${color.bg} flex items-center justify-center flex-shrink-0`}>
                    <Icon className={`w-6 h-6 ${color.text}`} />
                </div>
                <div className="min-w-0">
                    <p className="text-xl font-bold text-gray-900 truncate">{value}</p>
                    <p className="text-sm text-gray-500 truncate">{label}</p>
                    {subtitle && <p className="text-xs text-gray-400 truncate">{subtitle}</p>}
                </div>
            </div>
        </div>
    );
}

function GatewayCard({ gateway }) {
    const isAvailable = gateway.integrated && gateway.is_offline;
    return (
        <div className={`bg-white rounded-xl border p-5 ${isAvailable ? 'border-emerald-200' : 'border-gray-200 opacity-70'}`}>
            <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-3">
                    <div className={`w-10 h-10 rounded-xl ${isAvailable ? 'bg-emerald-100' : 'bg-gray-100'} flex items-center justify-center`}>
                        {isAvailable
                            ? <Wifi className="w-5 h-5 text-emerald-600" />
                            : <WifiOff className="w-5 h-5 text-gray-400" />}
                    </div>
                    <div>
                        <p className="text-sm font-semibold text-gray-900">{gateway.label}</p>
                        <p className="text-xs text-gray-400 capitalize">{gateway.value}</p>
                    </div>
                </div>
                <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${
                    isAvailable ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'
                }`}>
                    {isAvailable ? 'Active' : 'Coming Soon'}
                </span>
            </div>
            {!isAvailable && (
                <p className="text-xs text-gray-400">Integration not yet available.</p>
            )}
        </div>
    );
}

function WebhookDetailDrawer({ webhook, open, onClose }) {
    useEffect(() => {
        if (open) document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = ''; };
    }, [open]);

    const handleKeyDown = useCallback((e) => {
        if (e.key === 'Escape') onClose();
    }, [onClose]);

    useEffect(() => {
        if (open) window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [open, handleKeyDown]);

    if (!open || !webhook) return null;

    const timeline = [
        { event: 'Received', time: webhook.created_at, icon: Clock, color: 'text-blue-500 bg-blue-100' },
        ...(webhook.status === 'processing' || webhook.status === 'processed' ? [{ event: 'Processing', time: webhook.verified_at || webhook.created_at, icon: Loader2, color: 'text-amber-500 bg-amber-100' }] : []),
        ...(webhook.status === 'processed' ? [{ event: 'Processed', time: webhook.processed_at, icon: Check, color: 'text-emerald-500 bg-emerald-100' }] : []),
        ...(webhook.status === 'failed' ? [{ event: 'Failed', time: webhook.processed_at, icon: XCircle, color: 'text-red-500 bg-red-100', reason: webhook.failure_reason }] : []),
    ].filter(t => t.time);

    return (
        <div className="fixed inset-0 z-50 flex">
            <div className="fixed inset-0 bg-black/30" onClick={onClose} aria-hidden="true" />
            <div className="relative ml-auto w-full max-w-xl bg-white shadow-2xl overflow-y-auto" role="dialog" aria-modal="true" aria-label="Webhook details">
                <div className="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between z-10">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                            <Zap className="w-5 h-5 text-blue-600" />
                        </div>
                        <div>
                            <h2 className="text-base font-semibold text-gray-900">Webhook Event</h2>
                            <p className="text-xs text-gray-500 font-mono">{webhook.gateway_event_id || webhook.id}</p>
                        </div>
                    </div>
                    <button onClick={onClose} className="p-2 rounded-lg hover:bg-gray-100 transition-colors" aria-label="Close">
                        <X className="w-5 h-5 text-gray-500" />
                    </button>
                </div>

                <div className="p-6 space-y-6">
                    <div className="flex items-center justify-between">
                        <StatusBadge status={webhook.status} />
                        <span className="text-xs text-gray-400">{formatDateTime(webhook.created_at)}</span>
                    </div>

                    <div className="bg-gray-50 rounded-xl p-5 space-y-3">
                        <h3 className="text-sm font-semibold text-gray-900">Request Info</h3>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-500">Gateway</span>
                            <span className="font-semibold text-gray-900 capitalize">{webhook.gateway}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-500">Event Type</span>
                            <span className="font-mono text-xs text-gray-900">{webhook.event_type || '—'}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-500">Gateway Event ID</span>
                            <span className="font-mono text-xs text-gray-600">{webhook.gateway_event_id || '—'}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-500">Gateway Reference</span>
                            <span className="font-mono text-xs text-gray-600">{webhook.gateway_reference || '—'}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-500">Payload Size</span>
                            <span className="text-gray-900">{webhook.payload_size ? `${(webhook.payload_size / 1024).toFixed(1)} KB` : '—'}</span>
                        </div>
                    </div>

                    {webhook.failure_reason && (
                        <div className="bg-red-50 rounded-xl border border-red-200 p-4">
                            <div className="flex items-start gap-2.5">
                                <AlertTriangle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                                <div>
                                    <p className="text-sm font-semibold text-red-800">Failure Reason</p>
                                    <p className="text-xs text-red-600 mt-0.5">{webhook.failure_reason}</p>
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="px-5 py-3 border-b border-gray-100">
                            <h3 className="text-sm font-semibold text-gray-900">Timeline</h3>
                        </div>
                        <div className="p-5">
                            <div className="space-y-0">
                                {timeline.map((step, i) => {
                                    const Icon = step.icon;
                                    return (
                                        <div key={i} className="relative flex items-start gap-4 pb-6 last:pb-0">
                                            {i < timeline.length - 1 && (
                                                <div className="absolute left-4 top-8 bottom-0 w-px bg-gray-200" />
                                            )}
                                            <div className={`w-8 h-8 rounded-full ${step.color} flex items-center justify-center flex-shrink-0`}>
                                                <Icon className="w-4 h-4" />
                                            </div>
                                            <div className="pt-1">
                                                <p className="text-sm font-medium text-gray-900">{step.event}</p>
                                                <p className="text-xs text-gray-400">{formatDateTime(step.time)}</p>
                                                {step.reason && <p className="text-xs text-red-500 mt-0.5">{step.reason}</p>}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>

                    {webhook.request_headers_raw && (
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-3 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900">Headers</h3>
                            </div>
                            <div className="p-5">
                                <pre className="text-xs text-gray-600 bg-gray-50 rounded-lg p-4 overflow-x-auto max-h-48 leading-relaxed font-mono">
                                    {webhook.request_headers_raw}
                                </pre>
                            </div>
                        </div>
                    )}

                    {webhook.request_payload_raw && (
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-3 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900">Payload Preview</h3>
                            </div>
                            <div className="p-5">
                                <pre className="text-xs text-gray-600 bg-gray-50 rounded-lg p-4 overflow-x-auto max-h-72 leading-relaxed font-mono">
                                    {webhook.request_payload_raw}
                                </pre>
                            </div>
                        </div>
                    )}

                    <div className="flex justify-center pt-2">
                        <button onClick={onClose} className="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function Pagination({ links }) {
    if (!links || links.length <= 3) return null;
    return (
        <div className="flex items-center justify-between pt-6">
            <p className="text-sm text-gray-500">Page {links.find(l => l.active)?.label || '—'}</p>
            <div className="flex gap-1">
                {links.map((link, i) => {
                    if (!link.url) return (
                        <span key={i} className="px-3 py-1.5 text-sm text-gray-400 rounded-md cursor-not-allowed">
                            {link.label.replace('&laquo;', '«').replace('&raquo;', '»').replace('Previous', '←').replace('Next', '→')}
                        </span>
                    );
                    return (
                        <button key={i} onClick={() => router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                            className={`px-3 py-1.5 text-sm rounded-md transition-colors ${link.active ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    );
                })}
            </div>
        </div>
    );
}

export default function SuperAdminOperationsConsole({ webhooks, filters, stats, gateways }) {
    const [showFilters, setShowFilters] = useState(false);
    const [searchValue, setSearchValue] = useState(filters?.search || '');
    const [gatewayFilter, setGatewayFilter] = useState(filters?.gateway || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [eventTypeFilter, setEventTypeFilter] = useState(filters?.event_type || '');
    const [dateFrom, setDateFrom] = useState(filters?.date_from || '');
    const [dateTo, setDateTo] = useState(filters?.date_to || '');
    const [selectedWebhook, setSelectedWebhook] = useState(null);

    const hasActiveFilters = filters?.gateway || filters?.status || filters?.event_type || filters?.date_from || filters?.date_to || filters?.search;

    const applyFilters = () => {
        router.get('/superadmin/operations', {
            gateway: gatewayFilter || undefined,
            status: statusFilter || undefined,
            event_type: eventTypeFilter || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            search: searchValue || undefined,
        }, { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        setSearchValue(''); setGatewayFilter(''); setStatusFilter(''); setEventTypeFilter('');
        setDateFrom(''); setDateTo('');
        router.get('/superadmin/operations', {}, { preserveState: true, replace: true });
    };

    const handleSearchSubmit = (e) => { e.preventDefault(); applyFilters(); };

    const items = webhooks?.data || [];

    return (
        <AdminLayout>
            <Head title="Operations Console" />

            <div className="p-6 lg:p-8 space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Operations Console</h1>
                        <p className="text-sm text-gray-500 mt-1">Platform webhook monitor and operations dashboard</p>
                    </div>
                    <div className="flex gap-2">
                        <button className="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors flex items-center gap-2">
                            <Download className="w-4 h-4" /> Export CSV
                        </button>
                        <button className="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors flex items-center gap-2">
                            <Download className="w-4 h-4" /> Export JSON
                        </button>
                    </div>
                </div>

                {stats && (
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatCard icon={Zap} label="Webhook Queue" value={stats.total_webhooks} color={{ bg: 'bg-blue-100', text: 'text-blue-600' }} subtitle="All time" />
                        <StatCard icon={Activity} label="Success Rate" value={`${stats.success_rate}%`} color={{ bg: 'bg-emerald-100', text: 'text-emerald-600' }} subtitle={`${stats.success_count} processed`} />
                        <StatCard icon={AlertTriangle} label="Failure Rate" value={`${stats.failure_rate}%`} color={{ bg: 'bg-red-100', text: 'text-red-600' }} subtitle={`${stats.failed_count} failed`} />
                        <StatCard icon={Clock} label="Pending Queue" value={stats.pending_queue} color={{ bg: 'bg-amber-100', text: 'text-amber-600' }} subtitle="Awaiting processing" />
                        <StatCard icon={Server} label="Avg Processing" value={`${stats.avg_processing_seconds}s`} color={{ bg: 'bg-purple-100', text: 'text-purple-600' }} subtitle="Per webhook" />
                        <StatCard icon={Check} label="Processed Today" value={stats.processed_today} color={{ bg: 'bg-emerald-100', text: 'text-emerald-600' }} subtitle="Last 24h" />
                        <StatCard icon={XCircle} label="Failed Today" value={stats.failed_today} color={{ bg: 'bg-red-100', text: 'text-red-600' }} subtitle="Last 24h" />
                        <StatCard icon={RefreshCw} label="Last Sync" value={stats.last_successful_at ? formatDate(stats.last_successful_at) : '—'} color={{ bg: 'bg-gray-100', text: 'text-gray-600' }} subtitle="Last successful" />
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center gap-3">
                                <form onSubmit={handleSearchSubmit} className="flex-1 flex gap-2">
                                    <div className="relative flex-1">
                                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                        <input type="text" value={searchValue} onChange={(e) => setSearchValue(e.target.value)}
                                            placeholder="Search by event ID, reference, or type..."
                                            className="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                            aria-label="Search webhooks" />
                                    </div>
                                    <button type="submit" className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">Search</button>
                                </form>
                                <button onClick={() => setShowFilters(!showFilters)}
                                    className={`px-4 py-2 text-sm font-medium rounded-lg border transition-colors flex items-center gap-2 ${
                                        showFilters || hasActiveFilters ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                                    }`}>
                                    <Filter className="w-4 h-4" /> Filters
                                    {hasActiveFilters && <span className="w-2 h-2 rounded-full bg-blue-500" />}
                                </button>
                                {hasActiveFilters && (
                                    <button onClick={clearFilters} className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                                        <X className="w-3.5 h-3.5" /> Clear
                                    </button>
                                )}
                            </div>

                            {showFilters && (
                                <div className="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                        <div>
                                            <label className="block text-xs font-medium text-gray-600 mb-1">Gateway</label>
                                            <select value={gatewayFilter} onChange={(e) => setGatewayFilter(e.target.value)}
                                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                                                aria-label="Filter by gateway">
                                                <option value="">All Gateways</option>
                                                {gateways?.map(g => <option key={g.value} value={g.value}>{g.label}</option>)}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-600 mb-1">Status</label>
                                            <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}
                                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                                                aria-label="Filter by status">
                                                {webhookStatusOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-600 mb-1">Event Type</label>
                                            <input type="text" value={eventTypeFilter} onChange={(e) => setEventTypeFilter(e.target.value)}
                                                placeholder="e.g. payment_intent.succeeded"
                                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                                aria-label="Filter by event type" />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-600 mb-1">Date From</label>
                                            <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)}
                                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                                aria-label="Date from" />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-600 mb-1">Date To</label>
                                            <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)}
                                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                                aria-label="Date to" />
                                        </div>
                                        <div className="flex items-end">
                                            <button onClick={applyFilters} className="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">Apply Filters</button>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {items.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Gateway</th>
                                                <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Event</th>
                                                <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Reference</th>
                                                <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                                <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Received</th>
                                                <th className="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {items.map((wh) => (
                                                <tr key={wh.id} className="hover:bg-gray-50/50 transition-colors">
                                                    <td className="px-5 py-4 whitespace-nowrap">
                                                        <div className="flex items-center gap-2">
                                                            <div className="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center">
                                                                <Globe className="w-3.5 h-3.5 text-gray-500" />
                                                            </div>
                                                            <span className="text-sm font-medium text-gray-900 capitalize">{wh.gateway}</span>
                                                        </div>
                                                    </td>
                                                    <td className="px-5 py-4 whitespace-nowrap">
                                                        <span className="text-sm font-mono text-gray-600">{wh.event_type || '—'}</span>
                                                    </td>
                                                    <td className="px-5 py-4 whitespace-nowrap">
                                                        <span className="text-xs font-mono text-gray-500">{wh.gateway_event_id || wh.gateway_reference || '—'}</span>
                                                    </td>
                                                    <td className="px-5 py-4 whitespace-nowrap"><StatusBadge status={wh.status} /></td>
                                                    <td className="px-5 py-4 whitespace-nowrap text-sm text-gray-500">{formatDateTime(wh.created_at)}</td>
                                                    <td className="px-5 py-4 whitespace-nowrap text-right">
                                                        <button onClick={() => setSelectedWebhook(wh)}
                                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                                                            aria-label={`View webhook ${wh.id}`}>
                                                            <Eye className="w-3.5 h-3.5" /> View
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="p-12 text-center">
                                    <div className="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                                        <Zap className="w-8 h-8 text-gray-400" />
                                    </div>
                                    <h3 className="text-base font-semibold text-gray-900 mb-2">No Webhook Events</h3>
                                    <p className="text-sm text-gray-500 max-w-md mx-auto">
                                        {hasActiveFilters
                                            ? 'No webhook events match your current filters.'
                                            : 'No webhook events have been received yet. They will appear once payment gateways send callbacks.'}
                                    </p>
                                    {hasActiveFilters && (
                                        <button onClick={clearFilters} className="mt-6 px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                                            Clear Filters
                                        </button>
                                    )}
                                </div>
                            )}

                            {items.length > 0 && (
                                <div className="px-5 py-4 border-t border-gray-100">
                                    <Pagination links={webhooks?.links || []} />
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="space-y-6">
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-4 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                    <Globe className="w-4 h-4 text-gray-400" /> Gateway Registry
                                </h3>
                            </div>
                            <div className="p-5 space-y-3">
                                {gateways?.map((g) => (
                                    <GatewayCard key={g.value} gateway={g} />
                                ))}
                            </div>
                        </div>

                        <div className="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl border border-blue-200 p-5">
                            <div className="flex items-center gap-2 mb-3">
                                <Server className="w-5 h-5 text-blue-600" />
                                <h3 className="text-sm font-semibold text-blue-900">Platform Health</h3>
                            </div>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-blue-700">Webhook Endpoint</span>
                                    <span className="text-emerald-600 font-semibold flex items-center gap-1">
                                        <Check className="w-3.5 h-3.5" /> Active
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-blue-700">Queue Processing</span>
                                    <span className="text-emerald-600 font-semibold flex items-center gap-1">
                                        <Check className="w-3.5 h-3.5" /> Synchronous
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-blue-700">Retry Mechanism</span>
                                    <span className="text-gray-500">Not configured</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-blue-700">Signature Verification</span>
                                    <span className="text-amber-600 font-semibold">Stub</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <WebhookDetailDrawer
                webhook={selectedWebhook}
                open={!!selectedWebhook}
                onClose={() => setSelectedWebhook(null)}
            />
        </AdminLayout>
    );
}
