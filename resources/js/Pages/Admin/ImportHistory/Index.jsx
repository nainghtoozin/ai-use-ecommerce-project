import { useState, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import {
    History, Search, Filter, X, ChevronLeft, ChevronRight,
    CheckCircle, AlertTriangle, XCircle, Clock, Loader2,
    Download, Eye, FileSpreadsheet, FileText, Package
} from 'lucide-react';

const STATUS_STYLES = {
    completed: { bg: 'bg-green-50 dark:bg-green-900/20', text: 'text-green-700 dark:text-green-300', icon: CheckCircle },
    completed_with_warnings: { bg: 'bg-amber-50 dark:bg-amber-900/20', text: 'text-amber-700 dark:text-amber-300', icon: AlertTriangle },
    failed: { bg: 'bg-red-50 dark:bg-red-900/20', text: 'text-red-700 dark:text-red-300', icon: XCircle },
    importing: { bg: 'bg-blue-50 dark:bg-blue-900/20', text: 'text-blue-700 dark:text-blue-300', icon: Loader2 },
    validating: { bg: 'bg-blue-50 dark:bg-blue-900/20', text: 'text-blue-700 dark:text-blue-300', icon: Loader2 },
    pending: { bg: 'bg-gray-50 dark:bg-gray-800', text: 'text-gray-600 dark:text-gray-400', icon: Clock },
    cancelled: { bg: 'bg-gray-50 dark:bg-gray-800', text: 'text-gray-600 dark:text-gray-400', icon: XCircle },
};

const STATUS_LABELS = {
    completed: 'Completed',
    completed_with_warnings: 'With Warnings',
    failed: 'Failed',
    importing: 'Importing',
    validating: 'Validating',
    pending: 'Pending',
    cancelled: 'Cancelled',
};

export default function Index({ imports, filters: initialFilters }) {
    const [search, setSearch] = useState(initialFilters.search || '');
    const [status, setStatus] = useState(initialFilters.status || '');
    const [dateFrom, setDateFrom] = useState(initialFilters.date_from || '');
    const [dateTo, setDateTo] = useState(initialFilters.date_to || '');
    const [showFilters, setShowFilters] = useState(false);
    const [loadingId, setLoadingId] = useState(null);

    const hasFilters = search || status || dateFrom || dateTo;

    function applyFilters() {
        const params = {};
        if (search) params.search = search;
        if (status) params.status = status;
        if (dateFrom) params.date_from = dateFrom;
        if (dateTo) params.date_to = dateTo;
        router.get(adminUrl('/admin/products/import/history'), params, { preserveState: true, preserveScroll: true });
    }

    function clearFilters() {
        setSearch('');
        setStatus('');
        setDateFrom('');
        setDateTo('');
        router.get(adminUrl('/admin/products/import/history'), {}, { preserveState: true, preserveScroll: true });
    }

    function handleDownloadErrors(id) {
        setLoadingId(id);
        window.location.href = adminUrl(`/admin/products/import/history/${id}/errors`);
        setTimeout(() => setLoadingId(null), 2000);
    }

    const data = imports?.data || [];

    return (
        <AdminLayout>
            <Head title="Import History" />

            <div className="p-4 lg:p-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-xl lg:text-2xl font-bold text-gray-900 dark:text-gray-100">Import History</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Track all product imports for your store</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link
                            href={adminUrl('/admin/products')}
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <Package className="w-4 h-4" />
                            Products
                        </Link>
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <button
                        type="button"
                        onClick={() => setShowFilters(!showFilters)}
                        className="w-full flex items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                    >
                        <span className="flex items-center gap-2">
                            <Filter className="w-4 h-4" />
                            Filters
                            {hasFilters && <span className="px-1.5 py-0.5 text-[10px] bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full">Active</span>}
                        </span>
                        <ChevronRight className={`w-4 h-4 transition-transform ${showFilters ? 'rotate-90' : ''}`} />
                    </button>

                    {showFilters && (
                        <div className="px-4 pb-4 border-t border-gray-100 dark:border-gray-800 pt-3">
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                <div>
                                    <label className="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">Search</label>
                                    <div className="relative">
                                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                        <input
                                            type="text"
                                            value={search}
                                            onChange={(e) => setSearch(e.target.value)}
                                            onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                            placeholder="File name..."
                                            className="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg dark:bg-gray-800 dark:text-gray-100"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">Status</label>
                                    <select
                                        value={status}
                                        onChange={(e) => setStatus(e.target.value)}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg dark:bg-gray-800 dark:text-gray-100"
                                    >
                                        <option value="">All Statuses</option>
                                        <option value="completed">Completed</option>
                                        <option value="completed_with_warnings">With Warnings</option>
                                        <option value="failed">Failed</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">From</label>
                                    <input
                                        type="date"
                                        value={dateFrom}
                                        onChange={(e) => setDateFrom(e.target.value)}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg dark:bg-gray-800 dark:text-gray-100"
                                    />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">To</label>
                                    <input
                                        type="date"
                                        value={dateTo}
                                        onChange={(e) => setDateTo(e.target.value)}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg dark:bg-gray-800 dark:text-gray-100"
                                    />
                                </div>
                            </div>
                            <div className="flex items-center gap-2 mt-3">
                                <button onClick={applyFilters} className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                                    Apply Filters
                                </button>
                                {hasFilters && (
                                    <button onClick={clearFilters} className="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">
                                        Clear
                                    </button>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                {/* Table */}
                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                    {data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16">
                            <History className="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" />
                            <p className="text-sm font-medium text-gray-500 dark:text-gray-400">No imports yet</p>
                            <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">Your product import history will appear here.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-gray-200 dark:border-gray-800">
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">File</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Status</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Mode</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Products</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Variants</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Errors</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Duration</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Date</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">User</th>
                                        <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {data.map((item) => {
                                        const statusStyle = STATUS_STYLES[item.status] || STATUS_STYLES.pending;
                                        const StatusIcon = statusStyle.icon;
                                        return (
                                            <tr key={item.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <FileSpreadsheet className="w-4 h-4 text-gray-400 flex-shrink-0" />
                                                        <span className="font-medium text-gray-900 dark:text-gray-100 truncate max-w-[180px]" title={item.file_name}>{item.file_name}</span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className={`inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full ${statusStyle.bg} ${statusStyle.text}`}>
                                                        <StatusIcon className={`w-3 h-3 ${item.status === 'importing' || item.status === 'validating' ? 'animate-spin' : ''}`} />
                                                        {STATUS_LABELS[item.status] || item.status}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-xs text-gray-600 dark:text-gray-400 capitalize">
                                                    {(item.import_mode || '').replace(/_/g, ' ')}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="text-green-600 font-medium">{item.products_created}</span>
                                                    {item.products_skipped > 0 && <span className="text-amber-500 text-xs ml-1">({item.products_skipped} skipped)</span>}
                                                </td>
                                                <td className="px-4 py-3 text-blue-600 font-medium">{item.variants_created}</td>
                                                <td className="px-4 py-3">
                                                    {item.error_count > 0 ? (
                                                        <span className="text-red-600 font-medium">{item.error_count}</span>
                                                    ) : item.warning_count > 0 ? (
                                                        <span className="text-amber-600 font-medium">{item.warning_count}w</span>
                                                    ) : (
                                                        <span className="text-gray-400">0</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-xs text-gray-500">{item.duration_ms ? (item.duration_ms < 1000 ? item.duration_ms + 'ms' : (item.duration_ms / 1000).toFixed(1) + 's') : '-'}</td>
                                                <td className="px-4 py-3 text-xs text-gray-500">
                                                    {new Date(item.created_at).toLocaleDateString()} {new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                                </td>
                                                <td className="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">{item.user?.name || '-'}</td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex items-center justify-end gap-1">
                        <Link
                            href={adminUrl(`/admin/products/import/history/${item.id}/page`)}
                            className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-md transition-colors"
                            title="View Details"
                        >
                                                            <Eye className="w-4 h-4" />
                                                        </Link>
                                                        {item.error_report_path && item.error_count > 0 && (
                                                            <button
                                                                onClick={() => handleDownloadErrors(item.id)}
                                                                disabled={loadingId === item.id}
                                                                className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-colors"
                                                                title="Download Error Report"
                                                            >
                                                                {loadingId === item.id ? <Loader2 className="w-4 h-4 animate-spin" /> : <Download className="w-4 h-4" />}
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    {imports?.links && imports.links.length > 3 && (
                        <div className="flex items-center justify-between px-4 py-3 border-t border-gray-200 dark:border-gray-800">
                            <p className="text-xs text-gray-500">
                                Showing {imports.from || 0} to {imports.to || 0} of {imports.total || 0} imports
                            </p>
                            <div className="flex items-center gap-1">
                                {imports.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url || '#'}
                                        preserveState
                                        preserveScroll
                                        className={`px-3 py-1.5 text-xs rounded-md transition-colors ${
                                            link.active ? 'bg-blue-600 text-white' :
                                            link.url ? 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' :
                                            'text-gray-300 dark:text-gray-600 pointer-events-none'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
