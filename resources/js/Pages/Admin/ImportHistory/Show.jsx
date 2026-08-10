import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import {
    ArrowLeft, CheckCircle, AlertTriangle, XCircle, Clock, Loader2,
    Download, FileSpreadsheet, Package, Layers, Tag, Ruler, Box,
    User, Calendar, Timer, FileText
} from 'lucide-react';

const STATUS_STYLES = {
    completed: { bg: 'bg-green-50 dark:bg-green-900/20', text: 'text-green-700 dark:text-green-300', border: 'border-green-200 dark:border-green-800', icon: CheckCircle },
    completed_with_warnings: { bg: 'bg-amber-50 dark:bg-amber-900/20', text: 'text-amber-700 dark:text-amber-300', border: 'border-amber-200 dark:border-amber-800', icon: AlertTriangle },
    failed: { bg: 'bg-red-50 dark:bg-red-900/20', text: 'text-red-700 dark:text-red-300', border: 'border-red-200 dark:border-red-800', icon: XCircle },
    importing: { bg: 'bg-blue-50 dark:bg-blue-900/20', text: 'text-blue-700 dark:text-blue-300', border: 'border-blue-200 dark:border-blue-800', icon: Loader2 },
    validating: { bg: 'bg-blue-50 dark:bg-blue-900/20', text: 'text-blue-700 dark:text-blue-300', border: 'border-blue-200 dark:border-blue-800', icon: Loader2 },
    pending: { bg: 'bg-gray-50 dark:bg-gray-800', text: 'text-gray-600 dark:text-gray-400', border: 'border-gray-200 dark:border-gray-700', icon: Clock },
    cancelled: { bg: 'bg-gray-50 dark:bg-gray-800', text: 'text-gray-600 dark:text-gray-400', border: 'border-gray-200 dark:border-gray-700', icon: XCircle },
};

const STATUS_LABELS = {
    completed: 'Completed',
    completed_with_warnings: 'Completed with Warnings',
    failed: 'Failed',
    importing: 'Importing',
    validating: 'Validating',
    pending: 'Pending',
    cancelled: 'Cancelled',
};

const MODE_LABELS = {
    create_new: 'Create New Only',
    create_update: 'Create + Update',
    update_only: 'Update Only',
};

function humanizeColumn(col) {
    if (!col) return '-';
    const map = {
        product_name: 'Product Name', product_type: 'Product Type',
        selling_price: 'Selling Price', cost_price: 'Cost Price',
        parent_sku: 'Parent SKU', variant_sku: 'Variant SKU',
    };
    return map[col] || col.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function suggestFix(e) {
    const msg = e.error || e.warning || '';
    if (msg.includes('required')) return 'Fill in this required field.';
    if (msg.includes('not found')) return `Create the ${humanizeColumn(e.column)} first, or use an existing one.`;
    if (msg.includes('Duplicate SKU')) return 'Use a unique SKU for each row.';
    if (msg.includes('must be a number')) return 'Enter a valid number.';
    if (msg.includes('already exists')) return 'Use "Create + Update" mode to update existing products.';
    return 'Check the template instructions and correct this value.';
}

export default function Show({ import: importRecord }) {
    const statusStyle = STATUS_STYLES[importRecord.status] || STATUS_STYLES.pending;
    const StatusIcon = statusStyle.icon;
    const allIssues = [...(importRecord.errors || []), ...(importRecord.warnings || [])];

    return (
        <AdminLayout>
            <Head title={`Import #${importRecord.id}`} />

            <div className="p-4 lg:p-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div className="flex items-center gap-3">
                        <Link
                            href={adminUrl('/admin/products/import/history/page')}
                            className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                        >
                            <ArrowLeft className="w-5 h-5" />
                        </Link>
                        <div>
                            <h1 className="text-xl lg:text-2xl font-bold text-gray-900 dark:text-gray-100">Import #{importRecord.id}</h1>
                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{importRecord.file_name}</p>
                        </div>
                    </div>
                    {importRecord.error_report_path && importRecord.error_count > 0 && (
                        <a
                            href={adminUrl(`/admin/products/import/history/${importRecord.id}/errors`)}
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors"
                        >
                            <Download className="w-4 h-4" />
                            Download Error Report
                        </a>
                    )}
                </div>

                {/* Status Banner */}
                <div className={`flex items-center gap-3 p-4 rounded-xl border ${statusStyle.border} ${statusStyle.bg}`}>
                    <StatusIcon className={`w-6 h-6 ${statusStyle.text} ${importRecord.status === 'importing' || importRecord.status === 'validating' ? 'animate-spin' : ''}`} />
                    <div>
                        <p className={`text-sm font-semibold ${statusStyle.text}`}>{STATUS_LABELS[importRecord.status] || importRecord.status}</p>
                        {importRecord.status === 'failed' && (
                            <p className="text-xs text-red-600 dark:text-red-400 mt-0.5">No changes were made to your product catalog.</p>
                        )}
                    </div>
                </div>

                {/* Info Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4">
                        <div className="flex items-center gap-2 text-gray-500 dark:text-gray-400 mb-2">
                            <FileText className="w-4 h-4" />
                            <span className="text-xs font-medium">File</span>
                        </div>
                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{importRecord.file_name}</p>
                        <p className="text-xs text-gray-500 mt-0.5 uppercase">{importRecord.file_type}</p>
                    </div>
                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4">
                        <div className="flex items-center gap-2 text-gray-500 dark:text-gray-400 mb-2">
                            <User className="w-4 h-4" />
                            <span className="text-xs font-medium">Imported By</span>
                        </div>
                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{importRecord.user?.name || 'Unknown'}</p>
                        <p className="text-xs text-gray-500 mt-0.5">{MODE_LABELS[importRecord.import_mode] || importRecord.import_mode || '-'}</p>
                    </div>
                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4">
                        <div className="flex items-center gap-2 text-gray-500 dark:text-gray-400 mb-2">
                            <Calendar className="w-4 h-4" />
                            <span className="text-xs font-medium">Date</span>
                        </div>
                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{new Date(importRecord.created_at).toLocaleDateString()}</p>
                        <p className="text-xs text-gray-500 mt-0.5">{new Date(importRecord.created_at).toLocaleTimeString()}</p>
                    </div>
                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4">
                        <div className="flex items-center gap-2 text-gray-500 dark:text-gray-400 mb-2">
                            <Timer className="w-4 h-4" />
                            <span className="text-xs font-medium">Duration</span>
                        </div>
                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {importRecord.duration_ms ? (importRecord.duration_ms < 1000 ? importRecord.duration_ms + 'ms' : (importRecord.duration_ms / 1000).toFixed(1) + 's') : '-'}
                        </p>
                    </div>
                </div>

                {/* Results */}
                <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    {[
                        { label: 'Products', value: importRecord.total_products || 0, icon: Package, color: 'text-gray-700 dark:text-gray-300', bg: 'bg-gray-50 dark:bg-gray-800' },
                        { label: 'Variants', value: importRecord.total_variants || 0, icon: Layers, color: 'text-gray-700 dark:text-gray-300', bg: 'bg-gray-50 dark:bg-gray-800' },
                        { label: 'Created', value: importRecord.products_created || 0, icon: CheckCircle, color: 'text-green-600', bg: 'bg-green-50 dark:bg-green-900/20' },
                        { label: 'Warnings', value: importRecord.warning_count || 0, icon: AlertTriangle, color: 'text-amber-600', bg: 'bg-amber-50 dark:bg-amber-900/20' },
                        { label: 'Errors', value: importRecord.error_count || 0, icon: XCircle, color: 'text-red-600', bg: 'bg-red-50 dark:bg-red-900/20' },
                    ].map(({ label, value, icon: Icon, color, bg }) => (
                        <div key={label} className={`text-center p-4 rounded-xl ${bg}`}>
                            <Icon className={`w-5 h-5 mx-auto mb-1 ${color}`} />
                            <p className={`text-2xl font-bold ${color}`}>{value}</p>
                            <p className="text-xs text-gray-500">{label}</p>
                        </div>
                    ))}
                </div>

                {/* Errors & Warnings Table */}
                {allIssues.length > 0 && (
                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                        <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Issues ({allIssues.length})
                            </h3>
                            {importRecord.error_report_path && importRecord.error_count > 0 && (
                                <a
                                    href={adminUrl(`/admin/products/import/history/${importRecord.id}/errors`)}
                                    className="inline-flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-700 font-medium"
                                >
                                    <Download className="w-3.5 h-3.5" />
                                    Download Report
                                </a>
                            )}
                        </div>
                        <div className="overflow-x-auto max-h-[400px] overflow-y-auto">
                            <table className="w-full text-xs">
                                <thead className="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Row</th>
                                        <th className="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Sheet</th>
                                        <th className="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Column</th>
                                        <th className="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Value</th>
                                        <th className="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Problem</th>
                                        <th className="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Suggested Fix</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {allIssues.map((e, i) => (
                                        <tr key={i} className={e.warning && !e.error ? 'bg-amber-50/50 dark:bg-amber-900/10' : 'bg-red-50/50 dark:bg-red-900/10'}>
                                            <td className="px-3 py-2 text-gray-700 dark:text-gray-300 font-medium">{e.row ?? '-'}</td>
                                            <td className="px-3 py-2 text-gray-600 dark:text-gray-400">{e.sheet ?? '-'}</td>
                                            <td className="px-3 py-2 text-gray-600 dark:text-gray-400">{humanizeColumn(e.column)}</td>
                                            <td className="px-3 py-2 text-gray-500 dark:text-gray-500 max-w-[100px] truncate" title={e.value}>{e.value || <em className="text-gray-400">empty</em>}</td>
                                            <td className="px-3 py-2 text-red-600 dark:text-red-400">{e.error || e.warning}</td>
                                            <td className="px-3 py-2 text-gray-500 dark:text-gray-400">{suggestFix(e)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
