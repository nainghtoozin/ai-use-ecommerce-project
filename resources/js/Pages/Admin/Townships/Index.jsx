import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

export default function TownshipsIndex({ townships, cities = [], filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [cityFilter, setCityFilter] = useState(filters.city_id || '');
    const [status, setStatus] = useState(filters.status || '');

    function applyFilters(overrides = {}) {
        const params = { search, city_id: cityFilter, status, ...overrides };
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
        router.get(adminUrl('/admin/townships'), params, { preserveState: true });
    }

    function handleToggle(id) {
        router.post(adminUrl(`/admin/townships/${id}/toggle`));
    }

    function handleDelete(id) {
        if (confirm('Delete this township?')) {
            router.delete(adminUrl(`/admin/townships/${id}`));
        }
    }

    return (
        <AdminLayout>
            <Head title="Townships" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <h1 className="text-2xl font-bold text-gray-900">Townships</h1>
                    <Link href={adminUrl('/admin/townships/create')} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                        Add Township
                    </Link>
                </div>

                <div className="bg-white rounded-lg border border-gray-200 p-4 mb-4">
                    <div className="flex flex-col sm:flex-row gap-3">
                        <input
                            type="text"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && applyFilters()}
                            placeholder="Search townships..."
                            className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <select
                            value={cityFilter}
                            onChange={e => { setCityFilter(e.target.value); applyFilters({ city_id: e.target.value }); }}
                            className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Cities</option>
                            {cities.map((city) => (
                                <option key={city.id} value={city.id}>{city.name}</option>
                            ))}
                        </select>
                        <select
                            value={status}
                            onChange={e => { setStatus(e.target.value); applyFilters({ status: e.target.value }); }}
                            className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <button onClick={() => applyFilters()} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">Search</button>
                        {(search || cityFilter || status) && (
                            <button onClick={() => { setSearch(''); setCityFilter(''); setStatus(''); router.get(adminUrl('/admin/townships'), {}, { preserveState: true }); }} className="px-4 py-2 text-gray-600 hover:text-gray-800 text-sm">Clear</button>
                        )}
                    </div>
                </div>

                <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">City</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Postal Code</th>
                                <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Active</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {!townships?.data?.length ? (
                                <tr><td colSpan="5" className="px-6 py-12 text-center text-gray-500">No townships found.</td></tr>
                            ) : townships.data.map((township) => (
                                <tr key={township.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 text-sm font-medium text-gray-900">{township.name}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600">{township.city?.name || '-'}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600">{township.postal_code || '-'}</td>
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
                        <p className="text-sm text-gray-500">Showing {townships.from} to {townships.to} of {townships.total} results</p>
                        <div className="flex gap-1">
                            {townships.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'}
                                    preserveState
                                    className={`px-3 py-1 text-sm rounded-md ${link.active ? 'bg-blue-600 text-white' : link.url ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed'}`}>
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
