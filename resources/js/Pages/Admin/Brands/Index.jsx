import { useState, useRef } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { Search, Plus, Trash2, Image } from 'lucide-react';

export default function BrandsIndex({ brands, query = '' }) {
    const { auth } = usePage().props;
    const permissions = auth?.user?.permissions || [];
    const can = (perm) => permissions.includes(perm);
    const [search, setSearch] = useState(query);
    const [deleteModal, setDeleteModal] = useState(null);
    const searchTimeout = useRef(null);

    function handleSearch(e) {
        e.preventDefault();
        router.get(adminUrl('/admin/brands/search'), { query: search }, { preserveState: true });
    }

    function confirmDelete(brand) {
        setDeleteModal(brand);
    }

    function handleDelete() {
        if (deleteModal) {
            router.delete(adminUrl(`/admin/brands/${deleteModal.id}`), {
                onSuccess: () => setDeleteModal(null),
            });
        }
    }

    return (
        <AdminLayout>
            <Head title="Brands" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Brands</h1>
                        <p className="text-sm text-gray-500 mt-1">Manage product brands</p>
                    </div>
                    {can('brands.create') && (
                        <Link href={adminUrl('/admin/brands/create')} className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                            <Plus className="w-4 h-4" />
                            Add Brand
                        </Link>
                    )}
                </div>

                <form onSubmit={handleSearch} className="flex gap-2 mb-6">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search by name or slug..." className="w-full border border-gray-300 rounded-lg pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                    </div>
                    <button type="submit" className="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">Search</button>
                </form>

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Logo</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Slug</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {!brands?.data?.length ? (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-16 text-center">
                                            <Image className="w-12 h-12 mx-auto text-gray-300 mb-3" />
                                            <p className="text-gray-500 text-sm">No brands found.</p>
                                            {search && <p className="text-gray-400 text-xs mt-1">Try a different search term.</p>}
                                        </td>
                                    </tr>
                                ) : brands.data.map((brand, index) => (
                                    <tr key={brand.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-6 py-4">
                                            {brand.logo_url ? (
                                                <img src={brand.logo_url} alt={brand.name} className="w-10 h-10 rounded-lg object-cover border border-gray-200" />
                                            ) : (
                                                <div className="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center border border-gray-200">
                                                    <Image className="w-5 h-5 text-gray-400" />
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-500">{index + 1}</td>
                                        <td className="px-6 py-4">
                                            <p className="text-sm font-medium text-gray-900">{brand.name}</p>
                                            {brand.description && (
                                                <p className="text-xs text-gray-500 mt-0.5 line-clamp-1">{brand.description}</p>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-500 hidden sm:table-cell">{brand.slug}</td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                brand.is_active
                                                    ? 'bg-green-50 text-green-700 ring-1 ring-green-600/20'
                                                    : 'bg-gray-50 text-gray-500 ring-1 ring-gray-300'
                                            }`}>
                                                {brand.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right text-sm whitespace-nowrap">
                                            <div className="flex justify-end gap-3">
                                                {can('brands.update') && (
                                                    <Link href={adminUrl(`/admin/brands/${brand.id}/edit`)} className="text-blue-600 hover:text-blue-800 font-medium">Edit</Link>
                                                )}
                                                {can('brands.delete') && (
                                                    <button onClick={() => confirmDelete(brand)} className="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {brands?.links && brands.links.length > 3 && (
                    <div className="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <p className="text-sm text-gray-500">
                            Showing {brands.from} to {brands.to} of {brands.total} results
                        </p>
                        <div className="flex gap-1">
                            {brands.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'}
                                    className={`px-3 py-1.5 text-sm rounded-md transition-colors ${
                                        link.active
                                            ? 'bg-blue-600 text-white'
                                            : link.url
                                                ? 'text-gray-700 hover:bg-gray-100'
                                                : 'text-gray-400 cursor-not-allowed'
                                    }`}>
                                    {link.label.replace('&laquo;', '«').replace('&raquo;', '»')}
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {deleteModal && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" onClick={() => setDeleteModal(null)}>
                    <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                                <Trash2 className="w-5 h-5 text-red-600" />
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900">Delete Brand</h3>
                                <p className="text-sm text-gray-500">Are you sure you want to delete <strong>{deleteModal.name}</strong>? This action cannot be undone.</p>
                            </div>
                        </div>
                        <div className="flex justify-end gap-3 mt-6">
                            <button onClick={() => setDeleteModal(null)} className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                            <button onClick={handleDelete} className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">Delete</button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
