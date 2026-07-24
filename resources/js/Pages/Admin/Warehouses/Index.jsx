import { Head, Link, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Building2, Plus, Search, Pencil, Trash2, Check, X, AlertTriangle } from 'lucide-react';
import { usePermission } from '@/Hooks/usePermission';
import { adminUrl } from '@/Utils/adminUrl';

export default function WarehousesIndex({ warehouses, query = '' }) {
    const { can } = usePermission();
    const [search, setSearch] = useState(query || '');
    const [deleteModal, setDeleteModal] = useState(null);

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(adminUrl('/admin/warehouses/search'), { query: search }, { preserveState: true });
    };

    const handleDelete = (id) => {
        router.delete(adminUrl(`/admin/warehouses/${id}`), {
            preserveScroll: true,
            onSuccess: () => setDeleteModal(null),
        });
    };

    return (
        <AdminLayout>
            <Head title="Warehouses" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <div className="flex items-center gap-3">
                            <Building2 className="w-8 h-8 text-gray-500" />
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900">Warehouses</h1>
                                <p className="text-sm text-gray-500">Manage your storage locations.</p>
                            </div>
                        </div>
                        {can('warehouses.create') && (
                            <Link
                                href={adminUrl('/admin/warehouses/create')}
                                className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
                            >
                                <Plus className="w-4 h-4" />
                                Add Warehouse
                            </Link>
                        )}
                    </div>

                    <div className="bg-white rounded-lg border border-gray-200">
                        <div className="p-4 border-b border-gray-200">
                            <form onSubmit={handleSearch} className="flex gap-3">
                                <div className="relative flex-1">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                    <input
                                        type="text"
                                        placeholder="Search warehouses..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                            </form>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Default</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {warehouses.data?.length === 0 && (
                                        <tr>
                                            <td colSpan="6" className="px-6 py-12 text-center text-gray-500">
                                                <Building2 className="w-12 h-12 mx-auto mb-2 text-gray-300" />
                                                <p>No warehouses found.</p>
                                                {can('warehouses.create') && (
                                                    <Link href={adminUrl('/admin/warehouses/create')} className="text-blue-600 hover:underline text-sm mt-2 inline-block">
                                                        Create your first warehouse
                                                    </Link>
                                                )}
                                            </td>
                                        </tr>
                                    )}
                                    {warehouses.data?.map((warehouse) => (
                                        <tr key={warehouse.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {warehouse.name}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {warehouse.code || '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                {warehouse.is_default ? (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        <Check className="w-3 h-3" /> Default
                                                    </span>
                                                ) : (
                                                    <span className="text-gray-400">-</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                {warehouse.is_active ? (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        Active
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                        Inactive
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                                {warehouse.description || '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                <div className="flex items-center justify-end gap-2">
                                                    {can('warehouses.update') && (
                                                        <Link
                                                            href={adminUrl(`/admin/warehouses/${warehouse.id}/edit`)}
                                                            className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                        >
                                                            <Pencil className="w-4 h-4" />
                                                        </Link>
                                                    )}
                                                    {can('warehouses.delete') && (
                                                        <button
                                                            onClick={() => setDeleteModal(warehouse)}
                                                            className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                        >
                                                            <Trash2 className="w-4 h-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {warehouses?.links && warehouses.links.length > 3 && (
                            <div className="px-6 py-4 border-t border-gray-200">
                                <div className="flex items-center justify-between">
                                    <div className="text-sm text-gray-500">
                                        Showing {warehouses.from ?? 0} to {warehouses.to ?? 0} of {warehouses.total ?? 0}
                                    </div>
                                    <nav className="flex items-center gap-1">
                                        {warehouses.links.map((link, i) => {
                                            if (!link.url) {
                                                return <span key={i} className="px-3 py-1 text-sm text-gray-400 cursor-not-allowed" dangerouslySetInnerHTML={{ __html: link.label }} />;
                                            }
                                            return (
                                                <Link
                                                    key={i}
                                                    href={link.url}
                                                    preserveState
                                                    preserveScroll
                                                    className={`px-3 py-1 text-sm rounded-lg ${link.active ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'}`}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            );
                                        })}
                                    </nav>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {deleteModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setDeleteModal(null)}>
                    <div className="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center gap-3 mb-4">
                            <div className="p-2 bg-red-100 rounded-full">
                                <AlertTriangle className="w-5 h-5 text-red-600" />
                            </div>
                            <h3 className="text-lg font-semibold text-gray-900">Delete Warehouse</h3>
                        </div>
                        <p className="text-sm text-gray-600 mb-2">
                            Are you sure you want to delete <strong>{deleteModal.name}</strong>?
                        </p>
                        {deleteModal.is_default && (
                            <p className="text-sm text-amber-600 mb-4">
                                This is the default warehouse. Set another warehouse as default first.
                            </p>
                        )}
                        <div className="flex justify-end gap-3 mt-6">
                            <button
                                onClick={() => setDeleteModal(null)}
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            {!deleteModal.is_default && (
                                <button
                                    onClick={() => handleDelete(deleteModal.id)}
                                    className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700"
                                >
                                    Delete
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
