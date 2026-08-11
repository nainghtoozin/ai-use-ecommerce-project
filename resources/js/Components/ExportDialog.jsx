import { useState } from 'react';
import { adminUrl } from '@/Utils/adminUrl';
import {
    X, Download, FileSpreadsheet, FileText, Globe,
    CheckCircle, Loader2, AlertCircle, Package, Layers
} from 'lucide-react';

export default function ExportDialog({ isOpen, onClose, type = 'products', filters = {}, selectedIds = [] }) {
    const [format, setFormat] = useState('xlsx');
    const [scope, setScope] = useState('all');
    const [exportType, setExportType] = useState('products');
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState(null);
    const [error, setError] = useState(null);

    const reset = () => {
        setFormat('xlsx');
        setScope('all');
        setExportType('products');
        setLoading(false);
        setResult(null);
        setError(null);
    };

    const handleClose = () => {
        reset();
        onClose();
    };

    const handleExport = () => {
        setLoading(true);
        setError(null);

        const params = { format, scope };

        if (scope === 'filtered') {
            Object.entries(filters).forEach(([key, value]) => {
                if (value) params[key] = value;
            });
        }

        if (scope === 'selected' && selectedIds.length > 0) {
            params.ids = selectedIds;
        }

        if (format === 'google_sheets') {
            fetch(adminUrl(`/admin/${type}/export/google-sheets`), {
                method: 'POST',
                body: JSON.stringify(params),
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    setError(data.error);
                } else {
                    setResult({ url: data.url, type: 'google_sheets' });
                }
            })
            .catch(() => setError('Export failed.'))
            .finally(() => setLoading(false));
            return;
        }

        const endpoint = exportType === 'variants'
            ? `/admin/variants/export?format=${format}`
            : exportType === 'variable_products'
                ? `/admin/products/export/variable?${new URLSearchParams(params).toString()}`
                : `/admin/${type}/export?${new URLSearchParams(params).toString()}`;

        window.location.href = adminUrl(endpoint);

        setTimeout(() => {
            setLoading(false);
            setResult({ type: 'file' });
        }, 1500);
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div className="fixed inset-0 bg-black/50 transition-opacity" onClick={handleClose} />

                <div className="relative transform overflow-hidden rounded-xl bg-white dark:bg-gray-900 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md">
                    <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Export</h3>
                        <button onClick={handleClose} className="rounded-lg p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <div className="px-6 py-5 space-y-5">
                        {error && (
                            <div className="flex items-start gap-2 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                <AlertCircle className="w-4 h-4 text-red-500 mt-0.5 flex-shrink-0" />
                                <p className="text-sm text-red-700 dark:text-red-300">{error}</p>
                            </div>
                        )}

                        {result ? (
                            <div className="text-center py-6">
                                <CheckCircle className="w-12 h-12 text-green-500 mx-auto mb-3" />
                                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {result.type === 'google_sheets' ? 'Exported to Google Sheets!' : 'Your export is ready.'}
                                </p>
                                <p className="text-xs text-gray-500 mt-1">
                                    {result.type === 'google_sheets' ? 'Your data has been exported.' : 'Your file should download automatically.'}
                                </p>
                                {result.url && (
                                    <a href={result.url} target="_blank" rel="noopener noreferrer" className="text-sm text-blue-600 hover:underline mt-2 inline-block">
                                        Open in Google Sheets
                                    </a>
                                )}
                            </div>
                        ) : (
                            <>
                                {/* What to export */}
                                <div>
                                    <label className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 block">Export</label>
                                    <div className="grid grid-cols-3 gap-2">
                                        {[
                                            { value: 'products', icon: Package, label: 'Products', desc: 'Single products only' },
                                            { value: 'variable_products', icon: Layers, label: 'Variable Products', desc: 'Parents + variants' },
                                            { value: 'variants', icon: Layers, label: 'Variants', desc: 'Variant details' },
                                        ].map(({ value, icon: Icon, label, desc }) => (
                                            <button
                                                key={value}
                                                onClick={() => setExportType(value)}
                                                className={`flex flex-col items-center gap-1.5 p-3 rounded-lg border transition-colors ${exportType === value ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'}`}
                                            >
                                                <Icon className="w-5 h-5 text-gray-600 dark:text-gray-400" />
                                                <span className="text-xs font-medium text-gray-700 dark:text-gray-300">{label}</span>
                                                <span className="text-[10px] text-gray-400">{desc}</span>
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                {/* Format */}
                                <div>
                                    <label className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 block">Format</label>
                                    <div className="grid grid-cols-3 gap-2">
                                        {[
                                            { value: 'xlsx', icon: FileSpreadsheet, label: 'Excel', desc: 'Round-trip ready' },
                                            { value: 'csv', icon: FileText, label: 'CSV' },
                                            { value: 'google_sheets', icon: Globe, label: 'Google Sheets' },
                                        ].map(({ value, icon: Icon, label, desc }) => (
                                            <button
                                                key={value}
                                                onClick={() => setFormat(value)}
                                                className={`flex flex-col items-center gap-1.5 p-3 rounded-lg border transition-colors ${format === value ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'}`}
                                            >
                                                <Icon className="w-5 h-5 text-gray-600 dark:text-gray-400" />
                                                <span className="text-xs font-medium text-gray-700 dark:text-gray-300">{label}</span>
                                                {desc && <span className="text-[10px] text-gray-400">{desc}</span>}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                {/* Scope */}
                                {(exportType === 'products' || exportType === 'variable_products') && (
                                    <div>
                                        <label className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 block">Scope</label>
                                        <div className="space-y-2">
                                            {[
                                                { value: 'all', label: exportType === 'variable_products' ? 'All Variable Products' : 'All Single Products', desc: exportType === 'variable_products' ? 'Export all variable products with variants' : 'Export all single products' },
                                                { value: 'filtered', label: 'Filtered Products', desc: 'Export filtered products' },
                                                ...(selectedIds.length > 0 ? [{ value: 'selected', label: `Selected (${selectedIds.length})`, desc: 'Export selected products' }] : []),
                                            ].map(({ value, label, desc }) => (
                                                <label key={value} className={`flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${scope === value ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'}`}>
                                                    <input type="radio" name="scope" value={value} checked={scope === value} onChange={(e) => setScope(e.target.value)} className="mt-0.5" />
                                                    <div>
                                                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{label}</p>
                                                        <p className="text-xs text-gray-500">{desc}</p>
                                                    </div>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </>
                        )}
                    </div>

                    <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/30">
                        <button onClick={handleClose} className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                            {result ? 'Done' : 'Cancel'}
                        </button>
                        {!result && (
                            <button
                                onClick={handleExport}
                                disabled={loading || (scope === 'selected' && selectedIds.length === 0)}
                                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
                            >
                                {loading ? <Loader2 className="w-4 h-4 animate-spin" /> : <Download className="w-4 h-4" />}
                                {loading ? 'Preparing...' : 'Export'}
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
