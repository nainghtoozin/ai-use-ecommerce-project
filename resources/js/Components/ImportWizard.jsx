import { useState, useRef, useCallback, useMemo } from 'react';
import { adminUrl } from '@/Utils/adminUrl';
import {
    Upload, FileSpreadsheet, X, CheckCircle,
    AlertCircle, AlertTriangle, ChevronRight, ChevronLeft,
    Download, Loader2, Package, Layers,
    XCircle, History
} from 'lucide-react';

const STEPS = ['upload', 'validate', 'preview', 'confirm', 'result'];

const STEP_LABELS = {
    upload: 'Upload',
    validate: 'Validate',
    preview: 'Preview',
    confirm: 'Import',
    result: 'Complete',
};

function StepIndicator({ currentStep }) {
    const currentIdx = STEPS.indexOf(currentStep);
    return (
        <div className="flex items-center justify-center gap-1 sm:gap-2 py-3">
            {STEPS.map((s, i) => {
                const isActive = i === currentIdx;
                const isComplete = i < currentIdx;
                return (
                    <div key={s} className="flex items-center gap-1 sm:gap-2">
                        <div className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold transition-colors ${
                            isComplete ? 'bg-green-600 text-white' :
                            isActive ? 'bg-blue-600 text-white' :
                            'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400'
                        }`}>
                            {isComplete ? <CheckCircle className="w-4 h-4" /> : i + 1}
                        </div>
                        <span className={`text-xs font-medium hidden sm:block ${
                            isActive ? 'text-blue-600 dark:text-blue-400' :
                            isComplete ? 'text-green-600 dark:text-green-400' :
                            'text-gray-400'
                        }`}>{STEP_LABELS[s]}</span>
                        {i < STEPS.length - 1 && (
                            <div className={`w-6 sm:w-10 h-0.5 rounded ${
                                i < currentIdx ? 'bg-green-400' : 'bg-gray-200 dark:bg-gray-700'
                            }`} />
                        )}
                    </div>
                );
            })}
        </div>
    );
}

function ErrorTable({ errors, filter }) {
    const filtered = useMemo(() => {
        if (!errors) return [];
        if (filter === 'all') return errors;
        return errors.filter(e => {
            if (filter === 'errors') return e.error && !e.warning;
            if (filter === 'warnings') return e.warning;
            return true;
        });
    }, [errors, filter]);

    if (filtered.length === 0) {
        return (
            <div className="text-center py-6 text-sm text-gray-400">
                No {filter === 'all' ? 'issues' : filter} found.
            </div>
        );
    }

    return (
        <div className="overflow-x-auto max-h-[240px] overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table className="w-full text-xs">
                <thead className="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
                    <tr>
                        <th className="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Row</th>
                        <th className="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Column</th>
                        <th className="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Value</th>
                        <th className="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Problem</th>
                        <th className="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Suggested Fix</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                    {filtered.slice(0, 50).map((e, i) => (
                        <tr key={i} className={e.warning && !e.error ? 'bg-amber-50/50 dark:bg-amber-900/10' : 'bg-red-50/50 dark:bg-red-900/10'}>
                            <td className="px-3 py-2 text-gray-700 dark:text-gray-300 font-medium">{e.row ?? '-'}</td>
                            <td className="px-3 py-2 text-gray-600 dark:text-gray-400">{humanizeColumn(e.column)}</td>
                            <td className="px-3 py-2 text-gray-500 dark:text-gray-500 max-w-[100px] truncate" title={e.value}>{e.value || <em className="text-gray-400">empty</em>}</td>
                            <td className="px-3 py-2 text-red-600 dark:text-red-400">{e.error || e.warning}</td>
                            <td className="px-3 py-2 text-gray-500 dark:text-gray-400">{suggestFix(e)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {filtered.length > 50 && (
                <div className="text-center py-2 text-xs text-gray-400 bg-gray-50 dark:bg-gray-800">
                    Showing 50 of {filtered.length} issues. Download the error report for the full list.
                </div>
            )}
        </div>
    );
}

function humanizeColumn(col) {
    if (!col) return '-';
    const map = {
        product_name: 'Product Name', product_type: 'Product Type',
        selling_price: 'Selling Price', cost_price: 'Cost Price',
        parent_sku: 'Parent SKU', variant_sku: 'Variant SKU',
        option_1_name: 'Option 1 Name', option_1_value: 'Option 1 Value',
        option_2_name: 'Option 2 Name', option_2_value: 'Option 2 Value',
        option_3_name: 'Option 3 Name', option_3_value: 'Option 3 Value',
    };
    return map[col] || col.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function suggestFix(e) {
    const msg = e.error || e.warning || '';
    if (msg.includes('required')) return 'Fill in this required field.';
    if (msg.includes('not found')) return `Create the ${humanizeColumn(e.column)} first, or use an existing one.`;
    if (msg.includes('Duplicate SKU')) return 'Use a unique SKU for each row.';
    if (msg.includes('must be a number')) return 'Enter a valid number (e.g. 19.99).';
    if (msg.includes('already exists')) return 'This SKU exists. Use "Create + Update" mode to update it.';
    if (msg.includes('Import the parent')) return 'Import the parent product first using the Product Import template.';
    return 'Check the template instructions and correct this value.';
}

export default function ImportWizard({ isOpen, onClose, onComplete, type = 'products' }) {
    const [step, setStep] = useState('upload');
    const [file, setFile] = useState(null);
    const [importMode, setImportMode] = useState('create_new');
    const [validation, setValidation] = useState(null);
    const [result, setResult] = useState(null);
    const [historyId, setHistoryId] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [errorFilter, setErrorFilter] = useState('all');
    const fileInputRef = useRef(null);

    const isVariants = type === 'variants';
    const isVariable = type === 'variable';
    const title = isVariable ? 'Import Variable Products' : isVariants ? 'Import Variants' : 'Import Products';
    const templateDesc = isVariable
        ? 'Create parent variable products and their variants together using the Products + Variants sheets.'
        : isVariants
            ? 'Add variants to existing variable products.'
            : 'Import single products, or variable products with their variants.';

    const validateEndpoint = '/admin/products/import/validate-sheet';
    const importEndpoint = '/admin/products/import/execute';
    const templateEndpoint = '/admin/products/import/template';

    const reset = useCallback(() => {
        setStep('upload');
        setFile(null);
        setImportMode('create_new');
        setValidation(null);
        setResult(null);
        setHistoryId(null);
        setLoading(false);
        setError(null);
        setErrorFilter('all');
    }, []);

    const handleClose = useCallback(() => {
        reset();
        onClose();
    }, [reset, onClose]);

    const handleFileSelect = (e) => {
        const f = e.target.files?.[0];
        if (!f) return;

        if (!f.name.endsWith('.xlsx')) {
            setError('Please upload an XLSX file. Download the template first if you don\'t have one.');
            return;
        }

        if (f.size > 10 * 1024 * 1024) {
            setError('File is too large. Maximum size is 10MB.');
            return;
        }

        setFile(f);
        setError(null);
    };

    const handleRemoveFile = () => {
        setFile(null);
        setError(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const handleValidate = () => {
        if (!file || loading) return;

        setLoading(true);
        setError(null);
        setStep('validate');

        const formData = new FormData();
        formData.append('file', file);

        fetch(adminUrl(validateEndpoint), {
            method: 'POST',
            body: formData,
            credentials: 'include',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                setError(data.error);
                setStep('upload');
            } else {
                setValidation(data);
                setStep('preview');
            }
        })
        .catch(() => {
            setError('Could not validate your file. Please check it and try again.');
            setStep('upload');
        })
        .finally(() => setLoading(false));
    };

    const handleImport = () => {
        if (loading) return;

        setLoading(true);
        setError(null);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('import_mode', importMode);

        fetch(adminUrl(importEndpoint), {
            method: 'POST',
            body: formData,
            credentials: 'include',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
        })
        .then(r => r.json().then(data => ({ ok: r.ok, status: r.status, data })))
        .then(({ ok, status, data }) => {
            if (ok) {
                setResult(data.result);
                setHistoryId(data.history_id);
                setStep('result');
                if (onComplete) onComplete(data.result);
            } else if (status === 422 && data.validation) {
                setValidation(data.validation);
                setHistoryId(data.history_id);
                setError('Validation found errors. Please fix them and try again.');
                setStep('preview');
            } else {
                setError(data.error || 'Something went wrong. Please check your file and try again.');
            }
        })
        .catch(() => {
            setError('Could not connect to the server. Please try again.');
        })
        .finally(() => setLoading(false));
    };

    const handleDownloadTemplate = () => {
        window.location.href = adminUrl(templateEndpoint);
    };

    const handleDownloadErrorReport = () => {
        if (!historyId) return;
        window.location.href = adminUrl(`/admin/products/import/history/${historyId}/errors`);
    };

    const summary = validation?.summary || {};
    const totalErrors = (summary.error_products || 0) + (summary.error_variants || 0);
    const totalWarnings = summary.warning_count || 0;
    const totalValid = (summary.valid_products || 0) + (summary.valid_variants || 0);
    const totalRows = (summary.total_products || 0) + (summary.total_variants || 0);
    const allIssues = [...(validation?.errors || []), ...(validation?.warnings || [])];
    const hasErrorReport = historyId && (totalErrors > 0 || totalWarnings > 0);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div className="fixed inset-0 bg-black/50 transition-opacity" onClick={handleClose} />

                <div className="relative transform overflow-hidden rounded-xl bg-white dark:bg-gray-900 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl max-h-[90vh] flex flex-col">
                    {/* Header */}
                    <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800 flex-shrink-0">
                        <div className="flex items-center gap-3">
                            {isVariable ? <Package className="w-5 h-5 text-purple-600" /> : isVariants ? <Layers className="w-5 h-5 text-blue-600" /> : <Package className="w-5 h-5 text-blue-600" />}
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{title}</h3>
                                <p className="text-xs text-gray-500 mt-0.5">{templateDesc}</p>
                            </div>
                        </div>
                        <button onClick={handleClose} className="rounded-lg p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    {/* Step Indicator */}
                    <div className="px-6 border-b border-gray-100 dark:border-gray-800 flex-shrink-0">
                        <StepIndicator currentStep={step} />
                    </div>

                    {/* Body */}
                    <div className="px-6 py-5 overflow-y-auto flex-1 min-h-0">
                        {error && step !== 'validate' && (
                            <div className="flex items-start gap-2 p-3 mb-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                <AlertCircle className="w-4 h-4 text-red-500 mt-0.5 flex-shrink-0" />
                                <p className="text-sm text-red-700 dark:text-red-300">{error}</p>
                            </div>
                        )}

                        {/* Step 1: Upload */}
                        {step === 'upload' && (
                            <div className="space-y-4">
                                {file ? (
                                    <div className="flex items-center gap-4 p-4 bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800 rounded-xl">
                                        <FileSpreadsheet className="w-8 h-8 text-green-600 dark:text-green-400 flex-shrink-0" />
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{file.name}</p>
                                            <p className="text-xs text-gray-500">{(file.size / 1024).toFixed(1)} KB</p>
                                        </div>
                                        <button onClick={handleRemoveFile} className="p-1.5 text-gray-400 hover:text-red-500 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                            <X className="w-4 h-4" />
                                        </button>
                                    </div>
                                ) : (
                                    <div
                                        onClick={() => fileInputRef.current?.click()}
                                        className="flex flex-col items-center justify-center p-8 border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-xl cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition-colors"
                                    >
                                        <input ref={fileInputRef} type="file" accept=".xlsx" onChange={handleFileSelect} className="hidden" />
                                        <Upload className="w-8 h-8 text-gray-400 mb-2" />
                                        <p className="text-sm font-medium text-gray-700 dark:text-gray-300">Drop your {isVariable ? 'variable products' : isVariants ? 'variants' : 'products'} template here, or <span className="text-blue-600">browse</span></p>
                                        <p className="text-xs text-gray-400 mt-1">XLSX files up to 10MB</p>
                                    </div>
                                )}

                                <div className="flex items-center justify-center">
                                    <button onClick={handleDownloadTemplate} className="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-700 font-medium">
                                        <Download className="w-4 h-4" />
                                        Download {isVariable ? 'Variable Product' : isVariants ? 'Variant' : 'Product'} Template
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* Step: Validate (loading) */}
                        {step === 'validate' && (
                            <div className="flex flex-col items-center justify-center py-12">
                                <Loader2 className="w-10 h-10 text-blue-600 animate-spin mb-4" />
                                <p className="text-sm font-medium text-gray-700 dark:text-gray-300">Validating your file...</p>
                                <p className="text-xs text-gray-400 mt-1">This may take a moment for large files.</p>
                            </div>
                        )}

                        {/* Step: Confirm (loading) */}
                        {step === 'confirm' && (
                            <div className="flex flex-col items-center justify-center py-12">
                                <Loader2 className="w-10 h-10 text-blue-600 animate-spin mb-4" />
                                <p className="text-sm font-medium text-gray-700 dark:text-gray-300">Importing {isVariable ? 'variable products' : isVariants ? 'variants' : 'products'}...</p>
                                <p className="text-xs text-gray-400 mt-1">Do not close this window.</p>
                            </div>
                        )}

                        {/* Step 2: Preview */}
                        {step === 'preview' && validation && (
                            <div className="space-y-4">
                                {/* Summary Cards */}
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    {[
                                        { label: 'Products', value: summary.total_products || 0, color: 'text-gray-700 dark:text-gray-300', bg: 'bg-gray-50 dark:bg-gray-800' },
                                        { label: 'Variants', value: summary.total_variants || 0, color: 'text-blue-600', bg: 'bg-blue-50 dark:bg-blue-900/20' },
                                        { label: 'Warnings', value: totalWarnings, color: 'text-amber-600', bg: 'bg-amber-50 dark:bg-amber-900/20' },
                                        { label: 'Errors', value: totalErrors, color: 'text-red-600', bg: 'bg-red-50 dark:bg-red-900/20' },
                                    ].map(({ label, value, color, bg }) => (
                                        <div key={label} className={`text-center p-3 rounded-lg ${bg}`}>
                                            <p className={`text-xl font-bold ${color}`}>{value}</p>
                                            <p className="text-xs text-gray-500">{label}</p>
                                        </div>
                                    ))}
                                </div>

                                {/* Import Mode */}
                                <div>
                                    <label className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 block">Import Mode</label>
                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                        {[
                                            { value: 'create_new', label: 'Create New Only', desc: 'Skip existing SKUs' },
                                            { value: 'create_update', label: 'Create + Update', desc: 'Create new, update existing' },
                                            { value: 'update_only', label: 'Update Only', desc: 'Only update matching SKUs' },
                                        ].map(({ value, label, desc }) => (
                                            <label key={value} className={`flex items-start gap-2 p-3 rounded-lg border cursor-pointer transition-colors text-left ${importMode === value ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'}`}>
                                                <input type="radio" name="importMode" value={value} checked={importMode === value} onChange={(e) => setImportMode(e.target.value)} className="mt-0.5" />
                                                <div>
                                                    <p className="text-xs font-medium text-gray-900 dark:text-gray-100">{label}</p>
                                                    <p className="text-[11px] text-gray-500">{desc}</p>
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                </div>

                                {/* Error/Warning Filter Tabs */}
                                {allIssues.length > 0 && (
                                    <div>
                                        <div className="flex items-center gap-1 mb-2">
                                            {[
                                                { key: 'all', label: 'All', count: allIssues.length },
                                                { key: 'errors', label: 'Errors', count: validation.errors?.length || 0 },
                                                { key: 'warnings', label: 'Warnings', count: validation.warnings?.length || 0 },
                                            ].map(({ key, label, count }) => (
                                                count > 0 && (
                                                    <button
                                                        key={key}
                                                        onClick={() => setErrorFilter(key)}
                                                        className={`px-3 py-1.5 text-xs font-medium rounded-lg transition-colors ${
                                                            errorFilter === key
                                                                ? key === 'errors' ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300'
                                                                : key === 'warnings' ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300'
                                                                : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300'
                                                                : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
                                                        }`}
                                                    >
                                                        {label} ({count})
                                                    </button>
                                                )
                                            ))}
                                        </div>
                                        <ErrorTable errors={allIssues} filter={errorFilter} />
                                    </div>
                                )}

                                {/* Error report download */}
                                {hasErrorReport && (
                                    <button onClick={handleDownloadErrorReport} className="inline-flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-700 font-medium">
                                        <Download className="w-3.5 h-3.5" />
                                        Download Error Report (Excel)
                                    </button>
                                )}
                            </div>
                        )}

                        {/* Step 3: Result */}
                        {step === 'result' && result && (
                            <div className="space-y-4">
                                <div className="text-center py-3">
                                    {totalErrors > 0 ? (
                                        <>
                                            <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-2" />
                                            <p className="text-lg font-semibold text-gray-900 dark:text-gray-100">Import Completed with Warnings</p>
                                        </>
                                    ) : (
                                        <>
                                            <CheckCircle className="w-12 h-12 text-green-500 mx-auto mb-2" />
                                            <p className="text-lg font-semibold text-gray-900 dark:text-gray-100">Import Completed</p>
                                        </>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    {isVariable ? [
                                        { label: 'Products Created', value: result.products_created || 0, icon: CheckCircle, color: 'text-green-600' },
                                        { label: 'Products Skipped', value: result.products_skipped || 0, icon: AlertTriangle, color: 'text-amber-600' },
                                        { label: 'Variants Created', value: result.variants_created || 0, icon: Layers, color: 'text-blue-600' },
                                        { label: 'Errors', value: result.errors?.length || 0, icon: XCircle, color: 'text-red-600' },
                                    ].map(({ label, value, icon: Icon, color }) => (
                                        <div key={label} className="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                                            <Icon className={`w-5 h-5 mx-auto mb-1 ${color}`} />
                                            <p className={`text-xl font-bold ${color}`}>{value}</p>
                                            <p className="text-xs text-gray-500">{label}</p>
                                        </div>
                                    )) : isVariants ? [
                                        { label: 'Variants Created', value: result.variants_created || 0, icon: CheckCircle, color: 'text-green-600' },
                                        { label: 'Variants Skipped', value: result.variants_skipped || 0, icon: AlertTriangle, color: 'text-amber-600' },
                                        { label: 'Errors', value: result.errors?.length || 0, icon: XCircle, color: 'text-red-600' },
                                    ].map(({ label, value, icon: Icon, color }) => (
                                        <div key={label} className="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                                            <Icon className={`w-5 h-5 mx-auto mb-1 ${color}`} />
                                            <p className={`text-xl font-bold ${color}`}>{value}</p>
                                            <p className="text-xs text-gray-500">{label}</p>
                                        </div>
                                    )) : [
                                        { label: 'Products Created', value: result.products_created || 0, icon: CheckCircle, color: 'text-green-600' },
                                        { label: 'Products Skipped', value: result.products_skipped || 0, icon: AlertTriangle, color: 'text-amber-600' },
                                        { label: 'Variants Created', value: result.variants_created || 0, icon: Layers, color: 'text-blue-600' },
                                        { label: 'Errors', value: result.errors?.length || 0, icon: XCircle, color: 'text-red-600' },
                                    ].map(({ label, value, icon: Icon, color }) => (
                                        <div key={label} className="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                                            <Icon className={`w-5 h-5 mx-auto mb-1 ${color}`} />
                                            <p className={`text-xl font-bold ${color}`}>{value}</p>
                                            <p className="text-xs text-gray-500">{label}</p>
                                        </div>
                                    ))}
                                </div>

                                {!isVariants && (result.categories_matched > 0 || result.brands_matched > 0 || result.units_matched > 0) && (
                                    <div className="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                        <p className="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Master Data Matched:</p>
                                        <p className="text-xs text-gray-500">{result.categories_matched} categories, {result.brands_matched} brands, {result.units_matched} units</p>
                                    </div>
                                )}

                                {result.errors?.length > 0 && (
                                    <div className="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                        <p className="text-xs font-medium text-red-700 dark:text-red-300 mb-1">Errors:</p>
                                        <ul className="text-xs text-red-600 dark:text-red-400 space-y-0.5 max-h-[100px] overflow-y-auto">
                                            {result.errors.map((e, i) => (
                                                <li key={i}>[{e.sheet}] Row {e.row} &mdash; {e.column}: {e.error}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                                {hasErrorReport && (
                                    <button onClick={handleDownloadErrorReport} className="inline-flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-700 font-medium">
                                        <Download className="w-3.5 h-3.5" />
                                        Download Error Report
                                    </button>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    <div className="flex items-center justify-between px-6 py-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/30 flex-shrink-0">
                        <div>
                            {step === 'preview' && (
                                <button
                                    onClick={() => { setStep('upload'); setValidation(null); setError(null); }}
                                    className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                                >
                                    <ChevronLeft className="w-4 h-4 inline mr-1" />
                                    Back
                                </button>
                            )}
                        </div>
                        <div className="flex gap-2">
                            <button onClick={handleClose} className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                                {step === 'result' ? 'Done' : 'Cancel'}
                            </button>
                            {step === 'upload' && (
                                <button onClick={handleValidate} disabled={!file || loading} className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                    Validate
                                    <ChevronRight className="w-4 h-4 inline ml-1" />
                                </button>
                            )}
                            {step === 'preview' && (
                                <button onClick={handleImport} disabled={loading || totalErrors > 0} className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                    Start Import
                                </button>
                            )}
                            {step === 'result' && (
                                <div className="flex gap-2">
                                    <button onClick={() => { reset(); }} className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                                        Import Another
                                    </button>
                                    <a href={adminUrl('/admin/products/import/history/page')} className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors inline-flex items-center gap-1.5">
                                        <History className="w-4 h-4" />
                                        View History
                                    </a>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
