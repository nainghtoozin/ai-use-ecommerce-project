import { useState } from 'react';
import { Link, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Store } from 'lucide-react';

export default function StoreCategoriesIndex({ categories, filters }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');

    function handleSearch(e) {
        e.preventDefault();
        router.get('/superadmin/store-categories', {
            search,
            status: statusFilter,
        }, { preserveState: true, replace: true });
    }

    function handleFilter(value) {
        setStatusFilter(value);
        router.get('/superadmin/store-categories', {
            search,
            status: value,
        }, { preserveState: true, replace: true });
    }

    function handleToggle(id) {
        router.post(`/superadmin/store-categories/${id}/toggle`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function handleDelete(id, assigned) {
        if (assigned) {
            window.alert('Cannot delete a category that is assigned to stores. Reassign those stores first.');
            return;
        }
        if (window.confirm('Delete this store category?')) {
            router.delete(`/superadmin/store-categories/${id}`, {
                preserveState: true,
                preserveScroll: true,
            });
        }
    }

    const items = categories?.data || [];

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Store Categories</h2>}>
            <Head title="Store Categories" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                                <form onSubmit={handleSearch} className="flex-1 flex gap-2">
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Search by name, slug, or description..."
                                        className="flex-1 rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                    />
                                    <button
                                        type="submit"
                                        className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                                    >
                                        Search
                                    </button>
                                </form>
                                <Link
                                    href="/superadmin/store-categories/create"
                                    className="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors flex items-center gap-1"
                                >
                                    <Store className="w-4 h-4" />
                                    Create Category
                                </Link>
                            </div>

                            <div className="flex gap-4 mb-6">
                                <select
                                    value={statusFilter}
                                    onChange={(e) => handleFilter(e.target.value)}
                                    className="rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                >
                                    <option value="">All Categories</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                    <thead className="bg-gray-50 dark:bg-gray-950">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Slug</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Stores</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                        {items.map((cat) => {
                                            const assigned = (cat.tenants_count || 0) > 0;
                                            return (
                                                <tr key={cat.id} className="hover:bg-gray-50 dark:bg-gray-950">
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="text-sm font-medium text-gray-900 dark:text-gray-100">{cat.name}</div>
                                                        {cat.description && (
                                                            <div className="text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">{cat.description}</div>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 font-mono">
                                                        {cat.slug}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500 dark:text-gray-400">
                                                        {cat.tenants_count || 0}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-center">
                                                        <button
                                                            onClick={() => handleToggle(cat.id)}
                                                            title={cat.is_active && assigned ? 'Assigned to stores — reassign before deactivating' : 'Toggle status'}
                                                            className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer transition-colors ${
                                                                cat.is_active
                                                                    ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                                                    : 'bg-red-100 text-red-700 hover:bg-red-200'
                                                            }`}
                                                        >
                                                            {cat.is_active ? 'Active' : 'Inactive'}
                                                        </button>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <div className="flex items-center justify-end gap-2">
                                                            <Link
                                                                href={`/superadmin/store-categories/${cat.id}/edit`}
                                                                className="text-indigo-600 hover:text-indigo-900"
                                                            >
                                                                Edit
                                                            </Link>
                                                            <button
                                                                onClick={() => handleDelete(cat.id, assigned)}
                                                                className="text-red-600 hover:text-red-900"
                                                            >
                                                                Delete
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                        {items.length === 0 && (
                                            <tr>
                                                <td colSpan="5" className="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                                    No store categories found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {categories?.links && (
                                <div className="mt-6">
                                    {categories.links.map((link, i) => (
                                        <button
                                            key={i}
                                            onClick={() => router.get(link.url, {}, { preserveState: true })}
                                            disabled={!link.url}
                                            className={`px-3 py-1 mx-0.5 text-sm rounded ${link.active ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border hover:bg-gray-50'} ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
