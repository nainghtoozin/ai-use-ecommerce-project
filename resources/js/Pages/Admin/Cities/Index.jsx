import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

export default function CitiesIndex({ cities, filters = {} }) {
    const { flash } = usePage().props;
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [selectedIds, setSelectedIds] = useState([]);

    const pageIds = (cities?.data || []).map(c => c.id);
    const allSelected = pageIds.length > 0 && pageIds.every(id => selectedIds.includes(id));

    function applyFilters(overrides = {}) {
        const params = { search, status, ...overrides };
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
        router.get(adminUrl('/admin/cities'), params, { preserveState: true });
    }

    function handleToggle(id) {
        router.post(adminUrl(`/admin/cities/${id}/toggle`));
    }

    function handleDelete(id) {
        if (confirm('Delete this city? This will also delete associated townships.')) {
            router.delete(adminUrl(`/admin/cities/${id}`));
        }
    }

    function handleSelectAll() {
        if (allSelected) {
            setSelectedIds(selectedIds.filter(id => !pageIds.includes(id)));
        } else {
            setSelectedIds([...new Set([...selectedIds, ...pageIds])]);
        }
    }

    function handleSelectOne(id) {
        if (selectedIds.includes(id)) {
            setSelectedIds(selectedIds.filter(i => i !== id));
        } else {
            setSelectedIds([...selectedIds, id]);
        }
    }

    function handleBulkStatus(isActive) {
        const action = isActive ? 'activate' : 'deactivate';
        const message = isActive
            ? `Activate ${selectedIds.length} selected cit${selectedIds.length !== 1 ? 'ies' : 'y'}?`
            : `Deactivate ${selectedIds.length} selected cit${selectedIds.length !== 1 ? 'ies' : 'y'}? Their townships will be hidden from checkout, but township statuses will not be changed.`;
        if (!confirm(message)) return;
        router.post(adminUrl('/admin/cities/bulk-status'), { ids: selectedIds, is_active: isActive }, {
            preserveScroll: true,
            onSuccess: () => setSelectedIds([]),
        });
    }

    function handleImportMyanmar() {
        if (confirm('Import real Myanmar cities and townships? Existing entries will be skipped.')) {
            router.post(adminUrl('/admin/locations/import-myanmar'));
        }
    }

    return (
        <AdminLayout>
            <Head title="Cities" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {flash?.success && (
                    <div className="mb-4 px-4 py-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-900/40 rounded-lg text-sm text-green-700 dark:text-green-400">
                        {flash.success}
                    </div>
                )}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Cities</h1>
                    <div className="flex gap-2">
                        <button onClick={handleImportMyanmar}
                            className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 flex items-center gap-2">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 3v12" /></svg>
                            Import Myanmar Locations
                        </button>
                        <Link href={adminUrl('/admin/cities/create')} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                            Add City
                        </Link>
                    </div>
                </div>

                <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-4 mb-4">
                    <div className="flex flex-col sm:flex-row gap-3">
                        <input
                            type="text"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && applyFilters()}
                            placeholder="Search cities..."
                            className="flex-1 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <select
                            value={status}
                            onChange={e => { setStatus(e.target.value); applyFilters({ status: e.target.value }); }}
                            className="border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <button onClick={() => applyFilters()} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">Search</button>
                        {(search || status) && (
                            <button onClick={() => { setSearch(''); setStatus(''); router.get(adminUrl('/admin/cities'), {}, { preserveState: true }); }} className="px-4 py-2 text-gray-600 hover:text-gray-800 dark:text-gray-200 text-sm">Clear</button>
                        )}
                    </div>
                </div>

                {selectedIds.length > 0 && (
                    <div className="mb-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900/40 rounded-lg px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-3 sm:justify-between">
                        <span className="text-sm font-medium text-blue-700 dark:text-blue-300">
                            {selectedIds.length} cit{selectedIds.length !== 1 ? 'ies' : 'y'} selected
                        </span>
                        <div className="flex flex-wrap items-center gap-2">
                            <button onClick={() => handleBulkStatus(true)}
                                className="px-3 py-1.5 text-sm font-medium text-emerald-700 bg-emerald-100 rounded-md hover:bg-emerald-200">
                                Activate
                            </button>
                            <button onClick={() => handleBulkStatus(false)}
                                className="px-3 py-1.5 text-sm font-medium text-amber-700 bg-amber-100 rounded-md hover:bg-amber-200">
                                Deactivate
                            </button>
                            <button onClick={() => setSelectedIds([])}
                                className="px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-800">
                                Clear selection
                            </button>
                        </div>
                    </div>
                )}

                <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead className="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th className="px-4 py-3 text-left">
                                    <input type="checkbox" checked={allSelected} onChange={handleSelectAll}
                                        aria-label="Select all cities on this page"
                                        className="rounded border-gray-300 dark:border-gray-700 text-blue-600 focus:ring-blue-500" />
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Townships</th>
                                <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Active</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                            {!cities?.data?.length ? (
                                <tr><td colSpan="5" className="px-6 py-12 text-center text-gray-500 dark:text-gray-400">No cities found.</td></tr>
                            ) : cities.data.map((city) => (
                                <tr key={city.id} className="hover:bg-gray-50 dark:bg-gray-950">
                                    <td className="px-4 py-4">
                                        <input type="checkbox" checked={selectedIds.includes(city.id)} onChange={() => handleSelectOne(city.id)}
                                            aria-label={`Select ${city.name}`}
                                            className="rounded border-gray-300 dark:border-gray-700 text-blue-600 focus:ring-blue-500" />
                                    </td>
                                    <td className="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{city.name}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{city.townships_count ?? 0}</td>
                                    <td className="px-6 py-4 text-center">
                                        <button onClick={() => handleToggle(city.id)}
                                            className={`px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer ${city.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                            {city.is_active ? 'Active' : 'Inactive'}
                                        </button>
                                    </td>
                                    <td className="px-6 py-4 text-right text-sm">
                                        <div className="flex justify-end gap-2">
                                            <Link href={adminUrl(`/admin/cities/${city.id}/edit`)} className="text-blue-600 hover:text-blue-800">Edit</Link>
                                            <button onClick={() => handleDelete(city.id)} className="text-red-600 hover:text-red-800">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {cities?.links && cities.links.length > 3 && (
                    <div className="mt-4 flex items-center justify-between">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Showing {cities.from} to {cities.to} of {cities.total} results</p>
                        <div className="flex gap-1">
                            {cities.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'}
                                    preserveState
                                    className={`px-3 py-1 text-sm rounded-md ${link.active ? 'bg-blue-600 text-white' : link.url ? 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:bg-gray-800' : 'text-gray-400 cursor-not-allowed'}`}>
                                    {link.label.replace('&laquo;', '«').replace('&raquo;', '»')}
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
