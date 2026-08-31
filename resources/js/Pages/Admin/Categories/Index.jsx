import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';
import { Plus, Download, Search, Image, Trash2 } from 'lucide-react';

export default function CategoriesIndex({ categories }) {
    const { can } = usePermission();
    const [search, setSearch] = useState('');
    const [filterActive, setFilterActive] = useState('');
    const [filterFeatured, setFilterFeatured] = useState('');
    const [filterParent, setFilterParent] = useState('');
    const [deleteModal, setDeleteModal] = useState(null);

    function handleSearch(e) {
        e.preventDefault();
        const params = {};
        if (search) params.search = search;
        if (filterActive) params.filter_active = filterActive;
        if (filterFeatured) params.filter_featured = filterFeatured;
        if (filterParent) params.filter_parent = filterParent;
        router.get(adminUrl('/admin/categories'), params, { preserveState: true });
    }

    function handleFilterChange(key, value) {
        const params = {};
        if (search) params.search = search;
        if (key !== 'filter_active' && filterActive) params.filter_active = filterActive;
        if (key !== 'filter_featured' && filterFeatured) params.filter_featured = filterFeatured;
        if (key !== 'filter_parent' && filterParent) params.filter_parent = filterParent;
        if (value) params[key] = value;
        router.get(adminUrl('/admin/categories'), params, { preserveState: true });
    }

    function clearFilters() {
        setSearch('');
        setFilterActive('');
        setFilterFeatured('');
        setFilterParent('');
        router.get(adminUrl('/admin/categories'), {}, { preserveState: true });
    }

    function confirmDelete(category) {
        setDeleteModal(category);
    }

    function handleDelete() {
        if (deleteModal) {
            router.delete(adminUrl(`/admin/categories/${deleteModal.id}`), {
                onSuccess: () => setDeleteModal(null),
            });
        }
    }

    function handleImportDefaults() {
        if (confirm('Import default categories for this store?')) {
            router.post(adminUrl('/admin/categories/import-defaults'));
        }
    }

    const hasFilters = filterActive || filterFeatured || filterParent || search;

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

                <form onSubmit={handleSearch} className="flex flex-wrap gap-2 mb-4">
                    <div className="relative flex-1 min-w-[200px]">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search categories..."
                            className="w-full border border-gray-300 dark:border-gray-700 rounded-lg pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                    <select value={filterActive} onChange={(e) => { setFilterActive(e.target.value); handleFilterChange('filter_active', e.target.value); }}
                        className="border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-900">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <select value={filterFeatured} onChange={(e) => { setFilterFeatured(e.target.value); handleFilterChange('filter_featured', e.target.value); }}
                        className="border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-900">
                        <option value="">All Featured</option>
                        <option value="featured">Featured</option>
                        <option value="not_featured">Not Featured</option>
                    </select>
                    <select value={filterParent} onChange={(e) => { setFilterParent(e.target.value); handleFilterChange('filter_parent', e.target.value); }}
                        className="border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-900">
                        <option value="">All Levels</option>
                        <option value="root">Root Only</option>
                        <option value="child">Has Parent</option>
                    </select>
                    <button type="submit" className="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">Search</button>
                    {hasFilters && (
                        <button type="button" onClick={clearFilters} className="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400">Clear</button>
                    )}
                </form>

                <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead className="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Image</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Parent</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Sort</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Products</th>
                                    <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Featured</th>
                                    <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Active</th>
                                    <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                                {!categories?.data?.length ? (
                                    <tr>
                                        <td colSpan="9" className="px-4 py-12 text-center">
                                            <div className="flex flex-col items-center">
                                                <Image className="w-12 h-12 text-gray-300 mb-3" />
                                                <p className="text-gray-500 dark:text-gray-400 text-sm">No categories found.</p>
                                                {hasFilters && <p className="text-gray-400 dark:text-gray-500 text-xs mt-1">Try adjusting your filters.</p>}
                                            </div>
                                        </td>
                                    </tr>
                                ) : categories.data.map((category, index) => (
                                    <tr key={category.id} className="hover:bg-gray-50 dark:hover:bg-gray-950 transition-colors">
                                        <td className="px-4 py-3">
                                            {category.image_url ? (
                                                <img src={category.image_url} alt={category.name} className="w-10 h-10 rounded-lg object-cover border border-gray-200 dark:border-gray-800" />
                                            ) : (
                                                <div className="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center border border-gray-200 dark:border-gray-800">
                                                    <Image className="w-5 h-5 text-gray-400 dark:text-gray-500" />
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{index + 1}</td>
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{category.name}</p>
                                            {category.description && (
                                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">{category.description}</p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                            {category.parent_id ? (
                                                <span className="text-xs text-blue-600 dark:text-blue-400">Child</span>
                                            ) : (
                                                <span className="text-xs text-gray-400">Root</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{category.sort_order}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{category.products_count ?? 0}</td>
                                        <td className="px-4 py-3 text-center">
                                            {category.featured ? (
                                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 ring-1 ring-amber-600/20 dark:bg-amber-900/20 dark:text-amber-300">Yes</span>
                                            ) : (
                                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-50 text-gray-500 ring-1 ring-gray-300 dark:bg-gray-800 dark:text-gray-400">No</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {category.is_active ? (
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 ring-1 ring-green-600/20 dark:bg-green-900/20 dark:text-green-300">Active</span>
                                            ) : (
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-50 text-gray-500 ring-1 ring-gray-300 dark:bg-gray-800 dark:text-gray-400">Inactive</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right text-sm whitespace-nowrap">
                                            <div className="flex justify-end gap-3">
                                                {can('categories.update') && (
                                                    <Link href={adminUrl(`/admin/categories/${category.id}/edit`)} className="text-blue-600 hover:text-blue-800 font-medium">Edit</Link>
                                                )}
                                                {can('categories.delete') && (
                                                    <button onClick={() => confirmDelete(category)} className="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
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

            {deleteModal && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" onClick={() => setDeleteModal(null)}>
                    <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl max-w-md w-full p-6" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                                <Trash2 className="w-5 h-5 text-red-600" />
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Delete Category</h3>
                                <p className="text-sm text-gray-500 dark:text-gray-400">Are you sure you want to delete <strong>{deleteModal.name}</strong>?</p>
                            </div>
                        </div>
                        {deleteModal.products_count > 0 && (
                            <p className="text-sm text-amber-600 dark:text-amber-400 mb-4">This category has {deleteModal.products_count} product(s). Deleting it will remove the category reference from those products.</p>
                        )}
                        <div className="flex justify-end gap-3 mt-6">
                            <button onClick={() => setDeleteModal(null)} className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                            <button onClick={handleDelete} className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">Delete</button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
