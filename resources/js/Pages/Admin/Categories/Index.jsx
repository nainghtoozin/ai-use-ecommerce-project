import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';
import { Plus, Download, Search } from 'lucide-react';

export default function CategoriesIndex({ categories, query = '' }) {
    const { can } = usePermission();
    const [search, setSearch] = useState(query);

    function handleSearch(e) {
        e.preventDefault();
        router.get(adminUrl('/admin/categories/search'), { query: search }, { preserveState: true });
    }

    function handleDelete(id) {
        if (confirm('Delete this category?')) {
            router.delete(adminUrl(`/admin/categories/${id}`));
        }
    }

    function handleImportDefaults() {
        if (confirm('Import default categories for this store?')) {
            router.post(adminUrl('/admin/categories/import-defaults'));
        }
    }

    return (
        <AdminLayout>
            <Head title="Categories" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Categories</h1>
                    <div className="flex flex-wrap items-center gap-2">
                        {can('categories.create') && (
                            <button
                                onClick={handleImportDefaults}
                                className="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-300 dark:border-emerald-700 rounded-lg hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition-colors"
                            >
                                <Download className="w-4 h-4" />
                                Import Default Categories
                            </button>
                        )}
                        {can('categories.create') && (
                            <Link href={adminUrl('/admin/categories/create')} className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
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
                            placeholder="Search categories..."
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
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                            {!categories?.data?.length ? (
                                <tr><td colSpan="4" className="px-6 py-12 text-center text-gray-500 dark:text-gray-400">No categories found.</td></tr>
                            ) : categories.data.map((category, index) => (
                                <tr key={category.id} className="hover:bg-gray-50 dark:hover:bg-gray-950">
                                    <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{index + 1}</td>
                                    <td className="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{category.name}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{category.description || '-'}</td>
                                    <td className="px-6 py-4 text-right text-sm">
                                        <div className="flex justify-end gap-2">
                                            {can('categories.update') && (
                                                <Link href={adminUrl(`/admin/categories/${category.id}/edit`)} className="text-blue-600 hover:text-blue-800">Edit</Link>
                                            )}
                                            {can('categories.delete') && (
                                                <button onClick={() => handleDelete(category.id)} className="text-red-600 hover:text-red-800">Delete</button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {categories?.links && categories.links.length > 3 && (
                    <div className="mt-4 flex items-center justify-between">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Showing {categories.from} to {categories.to} of {categories.total} results</p>
                        <div className="flex gap-1">
                            {categories.links.map((link, i) => (
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
