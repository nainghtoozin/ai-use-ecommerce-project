import { useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    Sparkles,
    CreditCard,
    Truck,
    Package,
    Receipt,
    CheckCircle2,
    AlertTriangle,
    Loader2,
    ShieldAlert,
    X,
    Search,
} from 'lucide-react';

const CATEGORY_ICONS = {
    payment_methods: CreditCard,
    delivery_services: Truck,
    packaging_options: Package,
    cod_rules: Receipt,
};

const STATUS_BADGES = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    trialing: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    suspended: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};

function progressLabel(categoryStatus) {
    if (!categoryStatus) return '—';

    return `${categoryStatus.demo_present}/${categoryStatus.demo_total}`;
}

export default function DemoConfigurationIndex({ tenants, eligibleCount, categories, filters }) {
    const { props } = usePage();
    const bulkSummary = props.flash?.demo_bulk_summary;
    const categoryKeys = useMemo(() => Object.keys(categories || {}), [categories]);

    const [search, setSearch] = useState(filters?.search || '');
    const [selectedTenantIds, setSelectedTenantIds] = useState([]);
    const [selectedCategories, setSelectedCategories] = useState(categoryKeys);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const pageTenants = tenants?.data || [];
    const allOnPageSelected = pageTenants.length > 0
        && pageTenants.every((tenant) => selectedTenantIds.includes(tenant.id));

    function toggleTenant(id) {
        setSelectedTenantIds((current) => current.includes(id)
            ? current.filter((value) => value !== id)
            : [...current, id]);
    }

    function toggleAllOnPage() {
        if (allOnPageSelected) {
            const pageIds = pageTenants.map((tenant) => tenant.id);
            setSelectedTenantIds((current) => current.filter((id) => !pageIds.includes(id)));
            return;
        }

        setSelectedTenantIds((current) => {
            const merged = new Set([...current, ...pageTenants.map((tenant) => tenant.id)]);
            return Array.from(merged);
        });
    }

    function toggleCategory(key) {
        setSelectedCategories((current) => current.includes(key)
            ? current.filter((value) => value !== key)
            : [...current, key]);
    }

    function submitSearch(event) {
        event.preventDefault();
        router.get('/superadmin/demo-configuration', { search }, { preserveState: true, replace: true });
    }

    function confirmImport() {
        if (selectedTenantIds.length === 0 || selectedCategories.length === 0) return;

        setProcessing(true);
        router.post('/superadmin/demo-configuration/import', {
            tenant_ids: selectedTenantIds,
            categories: selectedCategories,
        }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setConfirmOpen(false);
            },
        });
    }

    const selectedCategoryLabels = selectedCategories.map((key) => categories[key] || key);

    return (
        <AdminLayout>
            <Head title="Demo Configuration" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <Sparkles className="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        Tenant Demo Configuration
                    </h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Initialize demo payment methods, delivery services, packaging options and COD rules for existing stores.
                        {eligibleCount !== undefined && (
                            <span className="ml-1 font-medium text-gray-700 dark:text-gray-300">{eligibleCount} tenant(s) available.</span>
                        )}
                    </p>
                </div>

                <div className="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                    <div className="flex items-start gap-2">
                        <ShieldAlert className="w-5 h-5 shrink-0 mt-0.5" />
                        <div>
                            <p className="font-semibold">SuperAdmin bulk initialization — demo data only.</p>
                            <p className="mt-1 text-xs">
                                Each tenant is processed independently and only missing demo records are created. Existing merchant
                                customization, credentials and active/inactive choices are never overwritten. No real payment gateway
                                or delivery integration is activated.
                            </p>
                        </div>
                    </div>
                </div>

                {bulkSummary && (
                    <div className="mb-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                        <div className="flex items-center justify-between mb-3">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                <CheckCircle2 className="w-4 h-4 text-green-600 dark:text-green-400" />
                                Bulk import result
                            </h2>
                            <div className="flex flex-wrap gap-2 text-xs">
                                <span className="px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                    {bulkSummary.totals?.created || 0} created
                                </span>
                                <span className="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    {bulkSummary.totals?.skipped || 0} skipped
                                </span>
                                <span className={`px-2 py-0.5 rounded-full ${(bulkSummary.totals?.failed || 0) > 0 ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'}`}>
                                    {bulkSummary.totals?.failed || 0} failed
                                </span>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full text-xs">
                                <thead>
                                    <tr className="text-left text-gray-500 dark:text-gray-400">
                                        <th className="py-1.5 pr-4 font-medium">Tenant</th>
                                        <th className="py-1.5 pr-4 font-medium">Created</th>
                                        <th className="py-1.5 pr-4 font-medium">Skipped</th>
                                        <th className="py-1.5 pr-4 font-medium">Failed</th>
                                        <th className="py-1.5 pr-4 font-medium">Result</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {(bulkSummary.results || []).map((result) => (
                                        <tr key={result.tenant_id}>
                                            <td className="py-1.5 pr-4 text-gray-700 dark:text-gray-300">
                                                {result.tenant_name} <span className="text-gray-400">({result.tenant_slug})</span>
                                            </td>
                                            <td className="py-1.5 pr-4 text-green-700 dark:text-green-400">{result.totals?.created || 0}</td>
                                            <td className="py-1.5 pr-4 text-gray-600 dark:text-gray-400">{result.totals?.skipped || 0}</td>
                                            <td className={`py-1.5 pr-4 ${(result.totals?.failed || 0) > 0 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-600 dark:text-gray-400'}`}>
                                                {result.totals?.failed || 0}
                                            </td>
                                            <td className="py-1.5 pr-4">
                                                {result.error ? (
                                                    <span className="text-red-600 dark:text-red-400">{result.error}</span>
                                                ) : (result.totals?.failed || 0) > 0 ? (
                                                    <span className="text-amber-600 dark:text-amber-400">Partial — see failed records</span>
                                                ) : (
                                                    <span className="text-green-600 dark:text-green-400">Completed</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {(bulkSummary.totals?.failed || 0) > 0 && (
                            <div className="mt-3 rounded-md bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/60 p-3">
                                <p className="text-xs font-semibold text-red-700 dark:text-red-300 mb-1">Failed records</p>
                                <ul className="text-xs text-red-700 dark:text-red-300 space-y-0.5">
                                    {(bulkSummary.results || []).flatMap((result) =>
                                        Object.values(result.categories || {}).flatMap((category) =>
                                            (category.records || [])
                                                .filter((record) => record.status === 'failed')
                                                .map((record) => (
                                                    <li key={`${result.tenant_id}-${category.label}-${record.identifier}`}>
                                                        {result.tenant_name}: {category.label} — {record.name} ({record.error || 'Unknown error'})
                                                    </li>
                                                ))
                                        )
                                    )}
                                </ul>
                            </div>
                        )}
                    </div>
                )}

                <div className="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                    <div className="p-4 border-b border-gray-200 dark:border-gray-800 flex flex-col lg:flex-row gap-4 lg:items-center lg:justify-between">
                        <form onSubmit={submitSearch} className="flex gap-2">
                            <div className="relative">
                                <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search tenants by name, slug or email..."
                                    className="pl-9 pr-3 py-2 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950 text-sm w-72"
                                />
                            </div>
                            <button type="submit" className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                                Search
                            </button>
                        </form>

                        <div className="flex flex-wrap items-center gap-2">
                            {categoryKeys.map((key) => {
                                const Icon = CATEGORY_ICONS[key] || Sparkles;
                                const active = selectedCategories.includes(key);

                                return (
                                    <button
                                        key={key}
                                        type="button"
                                        onClick={() => toggleCategory(key)}
                                        className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border ${active
                                            ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300'
                                            : 'border-gray-300 text-gray-500 dark:border-gray-700 dark:text-gray-400'}`}
                                    >
                                        <Icon className="w-3.5 h-3.5" />
                                        {categories[key]}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-800 flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-300">
                            <label className="inline-flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={allOnPageSelected}
                                    onChange={toggleAllOnPage}
                                    className="rounded border-gray-300 dark:border-gray-700"
                                />
                                Select page
                            </label>
                            <span className="text-xs text-gray-500 dark:text-gray-400">
                                {selectedTenantIds.length} selected · {selectedCategoryLabels.length} category(ies)
                            </span>
                            {selectedTenantIds.length > 0 && (
                                <button
                                    type="button"
                                    onClick={() => setSelectedTenantIds([])}
                                    className="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                                >
                                    Clear selection
                                </button>
                            )}
                        </div>

                        <button
                            type="button"
                            onClick={() => setConfirmOpen(true)}
                            disabled={selectedTenantIds.length === 0 || selectedCategories.length === 0 || processing}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {processing ? <Loader2 className="w-4 h-4 animate-spin" /> : <Sparkles className="w-4 h-4" />}
                            Initialize selected tenants
                        </button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead className="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th className="px-4 py-3 w-10" />
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tenant</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                    {categoryKeys.map((key) => (
                                        <th key={key} className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                            {categories[key]}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                                {pageTenants.length === 0 ? (
                                    <tr>
                                        <td colSpan={3 + categoryKeys.length} className="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                            No tenants found.
                                        </td>
                                    </tr>
                                ) : pageTenants.map((tenant) => (
                                    <tr key={tenant.id} className="hover:bg-gray-50 dark:hover:bg-gray-950/60">
                                        <td className="px-4 py-3">
                                            <input
                                                type="checkbox"
                                                checked={selectedTenantIds.includes(tenant.id)}
                                                onChange={() => toggleTenant(tenant.id)}
                                                className="rounded border-gray-300 dark:border-gray-700"
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{tenant.name}</p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">{tenant.slug}</p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`px-2 py-0.5 rounded-full text-[11px] font-medium ${STATUS_BADGES[tenant.status] || 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'}`}>
                                                {tenant.status}
                                            </span>
                                        </td>
                                        {categoryKeys.map((key) => {
                                            const categoryStatus = tenant.demo_status?.[key];
                                            const complete = categoryStatus && categoryStatus.demo_present >= categoryStatus.demo_total;

                                            return (
                                                <td key={key} className="px-4 py-3">
                                                    <span className={`inline-flex items-center gap-1 text-xs ${complete ? 'text-green-700 dark:text-green-400' : 'text-gray-600 dark:text-gray-400'}`}>
                                                        {complete && <CheckCircle2 className="w-3.5 h-3.5" />}
                                                        {progressLabel(categoryStatus)}
                                                    </span>
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {tenants?.links && tenants.links.length > 3 && (
                        <div className="px-4 py-3 border-t border-gray-200 dark:border-gray-800 flex flex-wrap gap-1">
                            {tenants.links.map((link, index) => (
                                <Link
                                    key={index}
                                    href={link.url || '#'}
                                    preserveScroll
                                    className={`px-3 py-1 text-xs rounded-md border ${link.active
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300'} ${!link.url ? 'opacity-50 pointer-events-none' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {confirmOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-xl bg-white dark:bg-gray-900 shadow-xl border border-gray-200 dark:border-gray-800">
                        <div className="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-800">
                            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Confirm bulk demo import</h3>
                            <button type="button" onClick={() => setConfirmOpen(false)} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                        <div className="px-5 py-4 text-sm text-gray-600 dark:text-gray-300 space-y-3">
                            <div className="flex items-start gap-2 rounded-md bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/60 p-3 text-xs text-amber-800 dark:text-amber-200">
                                <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
                                <p>
                                    This will initialize demo configuration for <strong>{selectedTenantIds.length}</strong> tenant(s).
                                    Each tenant is processed independently; a failure in one tenant does not affect the others.
                                </p>
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Categories</p>
                                <p className="text-xs text-gray-600 dark:text-gray-400">{selectedCategoryLabels.join(', ') || 'None selected'}</p>
                            </div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Only missing demo records are created. Existing merchant data is preserved.
                            </p>
                        </div>
                        <div className="flex justify-end gap-2 px-5 py-4 border-t border-gray-200 dark:border-gray-800">
                            <button
                                type="button"
                                onClick={() => setConfirmOpen(false)}
                                className="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={confirmImport}
                                disabled={processing || selectedCategories.length === 0}
                                className="inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-60"
                            >
                                {processing && <Loader2 className="w-4 h-4 animate-spin" />}
                                Import for {selectedTenantIds.length} tenant(s)
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
