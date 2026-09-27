import { useState } from 'react';
import axios from 'axios';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

const PAGE_SIZES = ['25', '50', '100', '1000', 'all'];

export default function TownshipsIndex({ townships, cities = [], filters = {}, other_location = {} }) {
    const { flash } = usePage().props;
    const [otherFee, setOtherFee] = useState(other_location?.delivery_fee ?? 5000);
    const [otherMinDays, setOtherMinDays] = useState(other_location?.min_days ?? 1);
    const [otherMaxDays, setOtherMaxDays] = useState(other_location?.max_days ?? 7);
    const [otherSettingsOpen, setOtherSettingsOpen] = useState(false);

    function openOtherSettings() {
        setOtherFee(other_location?.delivery_fee ?? 5000);
        setOtherMinDays(other_location?.min_days ?? 1);
        setOtherMaxDays(other_location?.max_days ?? 7);
        setOtherSettingsOpen(true);
    }

    function handleOtherSettings(e) {
        e.preventDefault();
        router.post(adminUrl('/admin/townships/other-settings'), {
            delivery_fee: otherFee,
            min_days: otherMinDays,
            max_days: otherMaxDays,
        }, { preserveScroll: true, onSuccess: () => setOtherSettingsOpen(false) });
    }
    const [search, setSearch] = useState(filters.search || '');
    const [cityFilter, setCityFilter] = useState(filters.city_id || '');
    const [status, setStatus] = useState(filters.status || '');
    const [perPage, setPerPage] = useState(filters.per_page || '25');
    const [selectedIds, setSelectedIds] = useState([]);
    const [selectAllMatching, setSelectAllMatching] = useState(false);
    const [matchingLoading, setMatchingLoading] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [editingFee, setEditingFee] = useState('');
    const [bulkFeeOpen, setBulkFeeOpen] = useState(false);
    const [bulkFee, setBulkFee] = useState('');

    const pageIds = (townships?.data || []).map(t => t.id);
    const totalMatching = townships?.total ?? 0;
    const allSelected = pageIds.length > 0 && pageIds.every(id => selectedIds.includes(id));

    function clearSelection() {
        setSelectedIds([]);
        setSelectAllMatching(false);
        setBulkFeeOpen(false);
    }

    function applyFilters(overrides = {}) {
        const params = { search, city_id: cityFilter, status, per_page: perPage, ...overrides };
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
        clearSelection();
        router.get(adminUrl('/admin/townships'), params, { preserveState: true });
    }

    function changePerPage(value) {
        setPerPage(value);
        const params = { search, city_id: cityFilter, status, per_page: value };
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
        clearSelection();
        router.get(adminUrl('/admin/townships'), params, { preserveState: true });
    }

    function clearAllFilters() {
        setSearch(''); setCityFilter(''); setStatus(''); setPerPage('25');
        clearSelection();
        router.get(adminUrl('/admin/townships'), {}, { preserveState: true });
    }

    async function handleSelectAllMatching() {
        setMatchingLoading(true);
        try {
            const params = { search, city_id: cityFilter, status };
            Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
            const res = await axios.get(adminUrl('/admin/townships/matching-ids'), { params });
            if (res.data?.truncated) {
                alert('Too many results to select at once. Please narrow the filters first.');
                return;
            }
            setSelectedIds(res.data?.ids || []);
            setSelectAllMatching(true);
            setBulkFeeOpen(false);
        } finally {
            setMatchingLoading(false);
        }
    }

    function handleToggle(id) {
        router.post(adminUrl(`/admin/townships/${id}/toggle`));
    }

    function handleDelete(id) {
        if (confirm('Delete this township?')) {
            router.delete(adminUrl(`/admin/townships/${id}`));
        }
    }

    function handleSelectAll() {
        setSelectAllMatching(false);
        if (allSelected) {
            setSelectedIds(selectedIds.filter(id => !pageIds.includes(id)));
        } else {
            setSelectedIds([...new Set([...selectedIds, ...pageIds])]);
        }
    }

    function handleSelectOne(id) {
        setSelectAllMatching(false);
        if (selectedIds.includes(id)) {
            setSelectedIds(selectedIds.filter(i => i !== id));
        } else {
            setSelectedIds([...selectedIds, id]);
        }
    }

    function handleBulkStatus(isActive) {
        router.post(adminUrl('/admin/townships/bulk-status'), { ids: selectedIds, is_active: isActive }, {
            preserveScroll: true,
            onSuccess: () => clearSelection(),
        });
    }

    function handleBulkDelete() {
        if (!confirm(`Delete ${selectedIds.length} selected township${selectedIds.length !== 1 ? 's' : ''}? Orders referencing them will keep their saved delivery details.`)) return;
        router.post(adminUrl('/admin/townships/bulk-destroy'), { ids: selectedIds }, {
            preserveScroll: true,
            onSuccess: () => clearSelection(),
        });
    }

    function submitFees(ids, fee, done) {
        router.post(adminUrl('/admin/townships/update-fees'), { ids, delivery_fee: fee }, {
            preserveScroll: true,
            onSuccess: () => { clearSelection(); done && done(); },
        });
    }

    function startFeeEdit(township) {
        setEditingId(township.id);
        setEditingFee(township.delivery_fee ?? 0);
    }

    function cancelFeeEdit() {
        setEditingId(null);
        setEditingFee('');
    }

    function saveFeeEdit(id) {
        const fee = Number(editingFee);
        if (editingFee === '' || isNaN(fee) || fee < 0) return;
        submitFees([id], fee, cancelFeeEdit);
    }

    function applyBulkFee() {
        const fee = Number(bulkFee);
        if (bulkFee === '' || isNaN(fee) || fee < 0 || selectedIds.length === 0) return;
        if (!confirm(`Set delivery fee to ${fee} for ${selectedIds.length} selected township${selectedIds.length !== 1 ? 's' : ''}?`)) return;
        submitFees(selectedIds, fee, () => { setBulkFeeOpen(false); setBulkFee(''); });
    }

    return (
        <AdminLayout>
            <Head title="Townships" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {flash?.success && (
                    <div className="mb-4 px-4 py-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-900/40 rounded-lg text-sm text-green-700 dark:text-green-400">
                        {flash.success}
                    </div>
                )}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Townships</h1>
                    <Link href={adminUrl('/admin/townships/create')} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                        Add Township
                    </Link>
                </div>

                <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 px-4 py-3 mb-4 flex flex-col sm:flex-row sm:items-center gap-2 sm:justify-between">
                    <div className="min-w-0">
                        <div className="flex items-center gap-3">
                            <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">Other Location</p>
                            <button onClick={openOtherSettings}
                                className="text-xs font-medium text-blue-600 hover:text-blue-800">
                                Edit
                            </button>
                        </div>
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Fallback delivery for locations not in your list</p>
                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100 mt-1">
                            {other_location?.delivery_fee ?? 5000} · {other_location?.min_days ?? 1}–{other_location?.max_days ?? 7} days
                        </p>
                    </div>
                </div>

                {otherSettingsOpen && (
                    <div className="fixed inset-0 z-30 flex items-center justify-center p-4">
                        <div className="absolute inset-0 bg-black/40" onClick={() => setOtherSettingsOpen(false)} />
                        <div role="dialog" aria-modal="true" aria-label="Other location settings"
                            className="relative bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xl max-w-sm w-full p-6">
                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Other Location Settings</h2>
                            <form onSubmit={handleOtherSettings} className="space-y-4">
                                <div>
                                    <label htmlFor="other-fee" className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Delivery Fee</label>
                                    <input id="other-fee" type="number" step="0.01" min="0" value={otherFee}
                                        onChange={e => setOtherFee(e.target.value)}
                                        className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label htmlFor="other-min-days" className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Min Delivery Days</label>
                                        <input id="other-min-days" type="number" min="0" value={otherMinDays}
                                            onChange={e => setOtherMinDays(e.target.value)}
                                            className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>
                                    <div>
                                        <label htmlFor="other-max-days" className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Max Delivery Days</label>
                                        <input id="other-max-days" type="number" min="0" value={otherMaxDays}
                                            onChange={e => setOtherMaxDays(e.target.value)}
                                            className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>
                                </div>
                                <div className="flex justify-end gap-2">
                                    <button type="button" onClick={() => setOtherSettingsOpen(false)}
                                        className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 dark:text-gray-200">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                                        Save
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-4 mb-4">
                    <div className="flex flex-col sm:flex-row gap-3">
                        <input
                            type="text"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && applyFilters()}
                            placeholder="Search townships..."
                            className="flex-1 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <select
                            value={cityFilter}
                            onChange={e => { setCityFilter(e.target.value); applyFilters({ city_id: e.target.value }); }}
                            className="w-full sm:w-48 border border-gray-300 dark:border-gray-700 rounded-lg pl-3 pr-8 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Cities</option>
                            {cities.map((city) => (
                                <option key={city.id} value={city.id}>{city.name}</option>
                            ))}
                        </select>
                        <select
                            value={status}
                            onChange={e => { setStatus(e.target.value); applyFilters({ status: e.target.value }); }}
                            className="w-full sm:w-48 border border-gray-300 dark:border-gray-700 rounded-lg pl-3 pr-8 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <select
                            value={perPage}
                            onChange={e => changePerPage(e.target.value)}
                            aria-label="Rows per page"
                            className="w-full sm:w-auto border border-gray-300 dark:border-gray-700 rounded-lg pl-3 pr-8 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            {PAGE_SIZES.map(size => (
                                <option key={size} value={size}>{size === 'all' ? 'All' : `${size} / page`}</option>
                            ))}
                        </select>
                        <button onClick={() => applyFilters()} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">Search</button>
                        {(search || cityFilter || status) && (
                            <button onClick={clearAllFilters} className="px-4 py-2 text-gray-600 hover:text-gray-800 dark:text-gray-200 text-sm">Clear</button>
                        )}
                    </div>
                </div>

                {(selectedIds.length > 0 || selectAllMatching) && (
                    <div className="mb-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900/40 rounded-lg px-4 py-3 flex flex-col gap-3">
                        <div className="flex flex-col sm:flex-row sm:items-center gap-3 sm:justify-between">
                            <span className="text-sm font-medium text-blue-700 dark:text-blue-300">
                                {selectAllMatching ? (
                                    <>All {totalMatching} matching township{totalMatching !== 1 ? 's' : ''} selected. </>
                                ) : (
                                    <>{selectedIds.length} selected on this page (of {totalMatching} matching). </>
                                )}
                                {!selectAllMatching && totalMatching > selectedIds.length && (
                                    <button onClick={handleSelectAllMatching} disabled={matchingLoading}
                                        className="underline underline-offset-2 hover:text-blue-900 disabled:opacity-50">
                                        {matchingLoading ? 'Loading…' : `Select all ${totalMatching} matching`}
                                    </button>
                                )}
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
                                <button onClick={() => { setBulkFeeOpen(v => !v); setBulkFee(''); }}
                                    className="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                                    Set Delivery Fee
                                </button>
                                <button onClick={handleBulkDelete}
                                    className="px-3 py-1.5 text-sm font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200">
                                    Delete
                                </button>
                                <button onClick={clearSelection}
                                    className="px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-800">
                                    Clear selection
                                </button>
                            </div>
                        </div>
                        {bulkFeeOpen && (
                            <div className="flex flex-col sm:flex-row gap-2 sm:items-center bg-white dark:bg-gray-900 rounded-lg border border-blue-200 dark:border-blue-900/40 p-3">
                                <label htmlFor="bulk-fee" className="text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                    Fee for {selectedIds.length} township{selectedIds.length !== 1 ? 's' : ''}:
                                </label>
                                <input id="bulk-fee" type="number" step="0.01" min="0" value={bulkFee}
                                    onChange={e => setBulkFee(e.target.value)}
                                    onKeyDown={e => { if (e.key === 'Enter') applyBulkFee(); if (e.key === 'Escape') setBulkFeeOpen(false); }}
                                    placeholder="0.00" autoFocus
                                    className="w-full sm:w-40 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                <div className="flex gap-2">
                                    <button onClick={applyBulkFee}
                                        className="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                                        Apply
                                    </button>
                                    <button onClick={() => setBulkFeeOpen(false)}
                                        className="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800 dark:text-gray-200">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead className="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th className="px-4 py-3 text-left">
                                    <input type="checkbox" checked={allSelected} onChange={handleSelectAll}
                                        aria-label="Select all townships on this page"
                                        className="rounded border-gray-300 dark:border-gray-700 text-blue-600 focus:ring-blue-500" />
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">City</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Postal Code</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Delivery Fee</th>
                                <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Active</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                            {!townships?.data?.length ? (
                                <tr><td colSpan="7" className="px-6 py-12 text-center text-gray-500 dark:text-gray-400">No townships found.</td></tr>
                            ) : townships.data.map((township) => (
                                <tr key={township.id} className="hover:bg-gray-50 dark:bg-gray-950">
                                    <td className="px-4 py-4">
                                        <input type="checkbox" checked={selectedIds.includes(township.id)} onChange={() => handleSelectOne(township.id)}
                                            aria-label={`Select ${township.name}`}
                                            className="rounded border-gray-300 dark:border-gray-700 text-blue-600 focus:ring-blue-500" />
                                    </td>
                                    <td className="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{township.name}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{township.city?.name || '-'}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{township.postal_code || '-'}</td>
                                    <td className="px-6 py-4 text-sm">
                                        {editingId === township.id ? (
                                            <span className="inline-flex items-center gap-1">
                                                <input type="number" step="0.01" min="0" value={editingFee}
                                                    onChange={e => setEditingFee(e.target.value)}
                                                    onKeyDown={e => { if (e.key === 'Enter') saveFeeEdit(township.id); if (e.key === 'Escape') cancelFeeEdit(); }}
                                                    autoFocus
                                                    aria-label={`Delivery fee for ${township.name}`}
                                                    className="w-28 border border-blue-400 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                                <button onClick={() => saveFeeEdit(township.id)} aria-label="Save fee"
                                                    className="px-2 py-1 text-sm font-medium text-emerald-700 bg-emerald-100 rounded-md hover:bg-emerald-200">✓</button>
                                                <button onClick={cancelFeeEdit} aria-label="Cancel editing"
                                                    className="px-2 py-1 text-sm text-gray-600 hover:text-gray-800 dark:text-gray-200">✕</button>
                                            </span>
                                        ) : (
                                            <button onClick={() => startFeeEdit(township)} title="Click to edit"
                                                className="text-gray-600 dark:text-gray-400 hover:text-blue-600 hover:underline underline-offset-2 decoration-dotted">
                                                {township.delivery_fee ?? 0}
                                            </button>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-center">
                                        <button onClick={() => handleToggle(township.id)}
                                            className={`px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer ${township.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                            {township.is_active ? 'Active' : 'Inactive'}
                                        </button>
                                    </td>
                                    <td className="px-6 py-4 text-right text-sm">
                                        <div className="flex justify-end gap-2">
                                            <Link href={adminUrl(`/admin/townships/${township.id}/edit`)} className="text-blue-600 hover:text-blue-800">Edit</Link>
                                            <button onClick={() => handleDelete(township.id)} className="text-red-600 hover:text-red-800">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {townships?.links && townships.links.length > 3 && (
                    <div className="mt-4 flex items-center justify-between">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Showing {townships.from} to {townships.to} of {townships.total} results</p>
                        <div className="flex gap-1">
                            {townships.links.map((link, i) => (
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
