import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function CategoriesIndex({ categories, query = '' }) {
    const [search, setSearch] = useState(query);

    function handleSearch(e) {
        e.preventDefault();
        router.get('/admin/categories/search', { query: search }, { preserveState: true });
    }

    function handleDelete(id) {
        if (confirm('Delete this category?')) {
            router.delete(`/admin/categories/${id}`);
        }
    }

    return (
        <AdminLayout>
            <Head title="Categories" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <h1 className="text-2xl font-bold text-gray-900">Categories</h1>
                    <Link href="/admin/categories/create" className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                        Add Category
                    </Link>
                </div>

                <form onSubmit={handleSearch} className="flex gap-2 mb-6">
                    <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search categories..." className="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    <button type="submit" className="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </button>
                </form>

                <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {!categories?.data?.length ? (
                                <tr><td colSpan="4" className="px-6 py-12 text-center text-gray-500">No categories found.</td></tr>
                            ) : categories.data.map((category) => (
                                <tr key={category.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 text-sm text-gray-500">#{category.id}</td>
                                    <td className="px-6 py-4 text-sm font-medium text-gray-900">{category.name}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600">{category.description || '-'}</td>
                                    <td className="px-6 py-4 text-right text-sm">
                                        <div className="flex justify-end gap-2">
                                            <Link href={`/admin/categories/${category.id}/edit`} className="text-blue-600 hover:text-blue-800">Edit</Link>
                                            <button onClick={() => handleDelete(category.id)} className="text-red-600 hover:text-red-800">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {categories?.links && categories.links.length > 3 && (
                    <div className="mt-4 flex items-center justify-between">
                        <p className="text-sm text-gray-500">Showing {categories.from} to {categories.to} of {categories.total} results</p>
                        <div className="flex gap-1">
                            {categories.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'}
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
