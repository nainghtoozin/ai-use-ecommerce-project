import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';
import { Plus, Download, Search } from 'lucide-react';

export default function UnitsIndex({ units, query = '' }) {
    const { can } = usePermission();
    const [search, setSearch] = useState(query);

    function handleSearch(e) {
        e.preventDefault();
        router.get(adminUrl('/admin/units/search'), { query: search }, { preserveState: true });
    }

    function handleDelete(id) {
        if (confirm('Delete this unit?')) {
            router.delete(adminUrl(`/admin/units/${id}`));
        }
    }

    function handleImportDefaults() {
        if (confirm('Import default units for this store?')) {
            router.post(adminUrl('/admin/units/import-defaults'));
        }
    }

    return (
        <AdminLayout>
            <Head title="Units" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Units</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage product measurement units</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {can('units.create') && (
                            <button
                                onClick={handleImportDefaults}
                                className="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-300 dark:border-emerald-700 rounded-lg hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition-colors"
                            >
                                <Download className="w-4 h-4" />
                                Import Default Units
                            </button>
                        )}
                        {can('units.create') && (
                            <Link href={adminUrl('/admin/units/create')} className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                                <Plus className="w-4 h-4" />
                                Create
                            </Link>
                        )}
                    </div>
                </div>

                <form onSubmit={handleSearch} className="flex gap-2 mb-6">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search units..."
                            className="w-full border border-gray-300 dark:border-gray-700 rounded-lg pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                    <button type="submit" className="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">Search</button>
                </form>

                <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead className="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Short Name</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                            {!units?.data?.length ? (
                                <tr><td colSpan="5" className="px-6 py-12 text-center text-gray-500 dark:text-gray-400">No units found.</td></tr>
                            ) : units.data.map((unit, index) => (
                                <tr key={unit.id} className="hover:bg-gray-50 dark:hover:bg-gray-950">
                                    <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{index + 1}</td>
                                    <td className="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{unit.name}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{unit.short_name}</td>
                                    <td className="px-6 py-4">
                                        <span className={`px-2 py-1 text-xs font-medium rounded-full ${unit.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400'}`}>
                                            {unit.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-right text-sm">
                                        <div className="flex justify-end gap-2">
                                            {can('units.update') && (
                                                <Link href={adminUrl(`/admin/units/${unit.id}/edit`)} className="text-blue-600 hover:text-blue-800">Edit</Link>
                                            )}
                                            {can('units.delete') && (
                                                <button onClick={() => handleDelete(unit.id)} className="text-red-600 hover:text-red-800">Delete</button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {units?.links && units.links.length > 3 && (
                    <div className="mt-4 flex items-center justify-between">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Showing {units.from} to {units.to} of {units.total} results</p>
                        <div className="flex gap-1">
                            {units.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'}
                                    className={`px-3 py-1 text-sm rounded-md ${link.active ? 'bg-blue-600 text-white' : link.url ? 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' : 'text-gray-400 cursor-not-allowed'}`}>
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
