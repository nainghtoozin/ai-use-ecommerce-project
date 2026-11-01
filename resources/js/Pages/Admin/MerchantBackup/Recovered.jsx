import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { AlertTriangle, CheckCircle2, Database, ExternalLink, ShieldCheck } from 'lucide-react';

const COUNT_LABELS = {
    products: 'Products',
    product_variants: 'Variants',
    categories: 'Categories',
    brands: 'Brands',
    orders: 'Orders',
    order_items: 'Order items',
    customers: 'Customers',
    storefronts: 'Storefronts',
    storefront_media: 'Media records',
    website_faqs: 'FAQs',
    inventory_levels: 'Inventory levels',
    inventory_movements: 'Stock movements',
};

function Stat({ label, value }) {
    return (
        <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</p>
            <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{value}</p>
        </div>
    );
}

export default function MerchantBackupRecovered({ result = {} }) {
    const counts = result.counts || {};
    const rows = Object.keys(COUNT_LABELS)
        .filter((key) => Object.prototype.hasOwnProperty.call(counts, key))
        .map((key) => ({ key, label: COUNT_LABELS[key], value: counts[key] }));
    const warnings = result.warnings || [];

    return (
        <AdminLayout>
            <Head title="Recovery Complete" />

            <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-4xl mx-auto">
                <div className="flex items-center gap-3">
                    <div className="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/30">
                        <CheckCircle2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Recovery complete</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            A new inactive store was created from the verified archive. The source store was not modified.
                        </p>
                    </div>
                </div>

                <div className="rounded-xl border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-900/20 p-4 text-sm text-emerald-800 dark:text-emerald-200">
                    <p className="font-semibold flex items-center gap-2">
                        <ShieldCheck className="w-4 h-4" />
                        {result.tenant_name || 'Recovered store'}
                    </p>
                    <p className="mt-1 text-xs">
                        Store slug: <span className="font-mono">{result.tenant_slug}</span> &middot; Status: {result.status} &middot; Plan: {result.plan_name || 'unknown'}
                    </p>
                    <p className="text-xs">Archive: <span className="font-mono break-all">{result.archive_id}</span></p>
                    {result.owner_email && <p className="text-xs">Owner identity: <span className="font-mono">{result.owner_email}</span></p>}
                </div>

                <div className="rounded-xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-900/20 p-4 flex items-start gap-3">
                    <AlertTriangle className="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" />
                    <div className="text-xs text-amber-800 dark:text-amber-200 space-y-1">
                        <p className="font-semibold text-sm">The recovered store stays inactive</p>
                        {warnings.map((warning) => (
                            <p key={warning}>{warning}</p>
                        ))}
                        <p>Product, category and brand management and the public storefront become available only after the store is legitimately activated.</p>
                    </div>
                </div>

                <div>
                    <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">Verified record counts</h2>
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        {rows.map((row) => (
                            <Stat key={row.key} label={row.label} value={row.value} />
                        ))}
                    </div>
                </div>

                <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        Media binaries were not recovered. Review and activate the store deliberately once media and access are restored.
                    </p>
                    <div className="flex items-center gap-2 shrink-0 flex-wrap">
                        <a
                            href={adminUrl('/admin/backups')}
                            className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-800 transition-colors"
                        >
                            <Database className="w-3.5 h-3.5" />
                            Merchant Backup
                        </a>
                        <a
                            href={`/store/${encodeURIComponent(result.tenant_slug || '')}/admin/dashboard`}
                            className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-700 transition-colors"
                        >
                            <ExternalLink className="w-3.5 h-3.5" />
                            Open Admin Dashboard
                        </a>
                        <button
                            type="button"
                            disabled
                            title="The recovered store is pending activation and its public storefront is not available yet."
                            className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold border border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-800 cursor-not-allowed"
                        >
                            <ExternalLink className="w-3.5 h-3.5" />
                            View storefront (pending activation)
                        </button>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
