import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { Database, Download, FileSpreadsheet, FileJson, FileText, Package, ShieldCheck } from 'lucide-react';

const FORMAT_META = {
    csv: { label: 'CSV', icon: FileText },
    xlsx: { label: 'Excel', icon: FileSpreadsheet },
    json: { label: 'JSON', icon: FileJson },
};

export default function DataExportIndex({ categoryGroups = [], formats = ['csv', 'xlsx', 'json'] }) {
    return (
        <AdminLayout>
            <Head title="Export My Data" />

            <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-6xl mx-auto">
                <div>
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-lg bg-blue-50 dark:bg-blue-900/30">
                            <Database className="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Export My Data</h1>
                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                Download a copy of your store data. Every export is limited to your own store.
                            </p>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 sm:p-6">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div className="flex items-start gap-3">
                            <div className="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/30">
                                <Package className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div>
                                <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Full data archive</h2>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                    Everything below bundled into a single ZIP with CSV files, a verified asset manifest and a versioned archive manifest.
                                </p>
                            </div>
                        </div>
                        <a
                            href={adminUrl('/admin/data-export/all')}
                            className="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shrink-0"
                        >
                            <Download className="w-4 h-4" />
                            Download ZIP
                        </a>
                    </div>
                </div>

                <div className="rounded-xl border border-indigo-200 dark:border-indigo-900/60 bg-indigo-50 dark:bg-indigo-900/20 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div className="flex items-start gap-3">
                        <ShieldCheck className="w-5 h-5 text-indigo-600 dark:text-indigo-400 mt-0.5 shrink-0" />
                        <div className="text-sm text-indigo-900 dark:text-indigo-100">
                            <p className="font-semibold">Need a signed backup instead?</p>
                            <p className="text-xs mt-1 text-indigo-800/80 dark:text-indigo-200/80">
                                Merchant Backup bundles the same datasets with a versioned, HMAC-signed manifest and an explicit exclusion list. It is not encrypted and never includes media files.
                            </p>
                        </div>
                    </div>
                    <a
                        href={adminUrl('/admin/backups')}
                        className="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shrink-0"
                    >
                        Merchant Backup
                    </a>
                </div>

                {categoryGroups.map((group) => (
                    <div key={group.name}>
                        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">{group.name}</h2>
                        <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                            {group.categories.map((category) => (
                                <div key={category.key} className="p-4 sm:px-5 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{category.label}</p>
                                        {category.description && (
                                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{category.description}</p>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap gap-2 shrink-0">
                                        {(category.formats || formats).map((format) => {
                                            const meta = FORMAT_META[format] || { label: format.toUpperCase(), icon: FileText };
                                            const Icon = meta.icon;
                                            return (
                                                <a
                                                    key={format}
                                                    href={adminUrl(`/admin/data-export/category/${category.key}?format=${format}`)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                                                >
                                                    <Icon className="w-3.5 h-3.5" />
                                                    {meta.label}
                                                </a>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}

                <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 p-4 flex items-start gap-3">
                    <ShieldCheck className="w-4 h-4 text-gray-400 mt-0.5 shrink-0" />
                    <div className="text-xs text-gray-500 dark:text-gray-400 space-y-1">
                        <p>Exports include only your store's data and are generated on demand. They are never shared publicly.</p>
                        <p>Image and media binaries are not embedded because some assets are stored remotely; <span className="font-mono">assets.json</span> verifies local files with size and SHA-256 and flags missing or external assets.</p>
                        <p>Spreadsheet exports neutralise formula characters (<span className="font-mono">= + - @</span>) to protect against CSV injection.</p>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
