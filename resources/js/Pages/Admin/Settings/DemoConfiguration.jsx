import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';
import {
    CreditCard,
    Truck,
    Package,
    Receipt,
    Sparkles,
    CheckCircle2,
    AlertTriangle,
    Loader2,
    ShieldAlert,
    X,
} from 'lucide-react';

const CATEGORY_META = {
    payment_methods: { icon: CreditCard, accent: 'blue' },
    delivery_services: { icon: Truck, accent: 'emerald' },
    packaging_options: { icon: Package, accent: 'amber' },
    cod_rules: { icon: Receipt, accent: 'purple' },
};

const ACCENTS = {
    blue: {
        card: 'border-blue-200 dark:border-blue-900/60',
        iconWrap: 'bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400',
        badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    },
    emerald: {
        card: 'border-emerald-200 dark:border-emerald-900/60',
        iconWrap: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400',
        badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    },
    amber: {
        card: 'border-amber-200 dark:border-amber-900/60',
        iconWrap: 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400',
        badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    },
    purple: {
        card: 'border-purple-200 dark:border-purple-900/60',
        iconWrap: 'bg-purple-50 text-purple-600 dark:bg-purple-950/40 dark:text-purple-400',
        badge: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    },
};

export default function DemoConfiguration({ status, categories, definitions }) {
    const { props } = usePage();
    const summary = props.flash?.demo_import_summary;
    const currencyConfig = getPlatformCurrencyConfig(props.platform_setting);
    const [pending, setPending] = useState(null);
    const [processing, setProcessing] = useState(false);

    const categoryKeys = useMemo(
        () => Object.keys(categories || {}),
        [categories]
    );

    const allMissing = useMemo(() => {
        return categoryKeys.reduce(
            (total, key) => total + (status?.categories?.[key]?.demo_missing || 0),
            0
        );
    }, [categoryKeys, status]);

    function askImport(keys, label) {
        setPending({ keys, label });
    }

    function confirmImport() {
        if (!pending) return;

        setProcessing(true);
        router.post(
            adminUrl('/admin/settings/demo-configuration/import'),
            { categories: pending.keys },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setPending(null);
                },
            }
        );
    }

    function demoValue(category, definition) {
        if (category === 'delivery_services') {
            return `${formatCurrency(definition.base_fee, currencyConfig)} Â· ${definition.min_days}-${definition.max_days} days`;
        }

        if (category === 'packaging_options') {
            return formatCurrency(definition.fee, currencyConfig);
        }

        if (category === 'cod_rules') {
            return definition.min_order_amount > 0
                ? `Minimum ${formatCurrency(definition.min_order_amount, currencyConfig)}`
                : 'No minimum order amount';
        }

        return definition.is_active ? 'Active demo method' : 'Inactive demo method';
    }

    return (
        <AdminLayout>
            <Head title="Demo Configuration" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <Sparkles className="w-6 h-6 text-blue-600 dark:text-blue-400" />
                            Demo Configuration
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Import ready-made demo payment methods, delivery services, packaging options and COD rules into this store.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => askImport(categoryKeys, 'all demo configurations')}
                        disabled={processing}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        {processing ? <Loader2 className="w-4 h-4 animate-spin" /> : <Sparkles className="w-4 h-4" />}
                        Import All Demo Configurations
                    </button>
                </div>

                <div className="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                    <div className="flex items-start gap-2">
                        <ShieldAlert className="w-5 h-5 shrink-0 mt-0.5" />
                        <div>
                            <p className="font-semibold">Demo data only â€” no real payment or delivery integration is activated.</p>
                            <p className="mt-1 text-xs">
                                Demo payment methods use placeholder account details and no gateway is connected. Orders are not marked as paid automatically.
                                Replace demo details with your real account information before accepting payments, and only enable card payments after a verified gateway is integrated.
                            </p>
                            <p className="mt-1 text-xs">
                                Existing records are never overwritten. Only missing demo records are created.
                            </p>
                        </div>
                    </div>
                </div>

                {summary && (
                    <div className="mb-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                        <div className="flex items-center justify-between mb-3">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                <CheckCircle2 className="w-4 h-4 text-green-600 dark:text-green-400" />
                                Last import result
                            </h2>
                            <div className="flex flex-wrap gap-2 text-xs">
                                <span className="px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                    {summary.totals?.created || 0} created
                                </span>
                                <span className="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    {summary.totals?.skipped || 0} skipped
                                </span>
                                <span className={`px-2 py-0.5 rounded-full ${(summary.totals?.failed || 0) > 0 ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'}`}>
                                    {summary.totals?.failed || 0} failed
                                </span>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full text-xs">
                                <thead>
                                    <tr className="text-left text-gray-500 dark:text-gray-400">
                                        <th className="py-1 pr-4 font-medium">Category</th>
                                        <th className="py-1 pr-4 font-medium">Created</th>
                                        <th className="py-1 pr-4 font-medium">Skipped</th>
                                        <th className="py-1 pr-4 font-medium">Failed</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {Object.entries(summary.categories || {}).map(([key, result]) => (
                                        <tr key={key}>
                                            <td className="py-1.5 pr-4 text-gray-700 dark:text-gray-300">{result.label || categories[key] || key}</td>
                                            <td className="py-1.5 pr-4 text-green-700 dark:text-green-400">{result.created}</td>
                                            <td className="py-1.5 pr-4 text-gray-600 dark:text-gray-400">{result.skipped}</td>
                                            <td className={`py-1.5 pr-4 ${result.failed > 0 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-600 dark:text-gray-400'}`}>{result.failed}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {Object.entries(summary.categories || {}).some(([, result]) => (result.records || []).some((record) => record.status === 'failed')) && (
                            <div className="mt-3 rounded-md bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/60 p-3">
                                <p className="text-xs font-semibold text-red-700 dark:text-red-300 mb-1">Failed records</p>
                                <ul className="text-xs text-red-700 dark:text-red-300 space-y-0.5">
                                    {Object.values(summary.categories).flatMap((result) =>
                                        (result.records || [])
                                            .filter((record) => record.status === 'failed')
                                            .map((record) => (
                                                <li key={`${result.label}-${record.identifier}`}>
                                                    {result.label}: {record.name} â€” {record.error || 'Unknown error'}
                                                </li>
                                            ))
                                    )}
                                </ul>
                            </div>
                        )}
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    {categoryKeys.map((key) => {
                        const meta = CATEGORY_META[key] || { icon: Sparkles, accent: 'blue' };
                        const accent = ACCENTS[meta.accent];
                        const Icon = meta.icon;
                        const categoryStatus = status?.categories?.[key] || {};
                        const missing = categoryStatus.demo_missing || 0;
                        const complete = missing === 0 && (categoryStatus.demo_total || 0) > 0;

                        return (
                            <div key={key} className={`rounded-lg border ${accent.card} bg-white dark:bg-gray-900 p-5`}>
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex items-center gap-3">
                                        <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${accent.iconWrap}`}>
                                            <Icon className="w-5 h-5" />
                                        </div>
                                        <div>
                                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                {categories[key] || key}
                                            </h2>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {categoryStatus.demo_present || 0} of {categoryStatus.demo_total || 0} demo records present Â· {categoryStatus.existing_count || 0} existing record(s)
                                            </p>
                                        </div>
                                    </div>
                                    <span className={`px-2 py-0.5 rounded-full text-[11px] font-medium ${complete ? accent.badge : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'}`}>
                                        {complete ? 'Imported' : missing > 0 ? `${missing} missing` : 'Not imported'}
                                    </span>
                                </div>

                                <ul className="mt-4 space-y-1.5">
                                    {(categoryStatus.records || []).map((record) => {
                                        const definition = (definitions?.[key] || []).find(
                                            (item) => (item.slug || item.code) === record.identifier
                                        );

                                        return (
                                            <li key={record.identifier} className="flex items-center justify-between gap-3 text-xs">
                                                <span className="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                                                    {definition?.logo_path ? (
                                                        <img src={definition.logo_path} alt="" className="w-4 h-4 rounded" />
                                                    ) : record.present ? (
                                                        <CheckCircle2 className="w-3.5 h-3.5 text-green-600 dark:text-green-400" />
                                                    ) : (
                                                        <AlertTriangle className="w-3.5 h-3.5 text-gray-400" />
                                                    )}
                                                    {record.name}
                                                </span>
                                                <span className="text-gray-400 dark:text-gray-500">
                                                    {record.present ? 'Present' : 'Will be created'}
                                                </span>
                                            </li>
                                        );
                                    })}
                                </ul>

                                <div className="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                                    <p className="text-[11px] text-gray-500 dark:text-gray-400">
                                        {(definitions?.[key] || []).slice(0, 3).map((definition) => demoValue(key, definition)).join(' Â· ')}
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => askImport([key], categories[key] || key)}
                                        disabled={processing}
                                        className="shrink-0 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-60"
                                    >
                                        Import
                                    </button>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {allMissing > 0 && (
                    <p className="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        {allMissing} demo record(s) are still missing across all categories.
                    </p>
                )}
            </div>

            {pending && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white dark:bg-gray-900 shadow-xl border border-gray-200 dark:border-gray-800">
                        <div className="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-800">
                            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Confirm demo import</h3>
                            <button type="button" onClick={() => setPending(null)} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                        <div className="px-5 py-4 text-sm text-gray-600 dark:text-gray-300 space-y-2">
                            <p>
                                Import <strong>{pending.label}</strong> into this store?
                            </p>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Only missing demo records are created. Existing records, credentials, prices and active/inactive choices are preserved.
                            </p>
                        </div>
                        <div className="flex justify-end gap-2 px-5 py-4 border-t border-gray-200 dark:border-gray-800">
                            <button
                                type="button"
                                onClick={() => setPending(null)}
                                className="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={confirmImport}
                                disabled={processing}
                                className="inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-60"
                            >
                                {processing && <Loader2 className="w-4 h-4 animate-spin" />}
                                Import now
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
