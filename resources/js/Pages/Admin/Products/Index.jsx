import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import PerPageSelect from '@/Components/PerPageSelect';
import { assetUrl } from '@/Utils/helpers';

export default function AdminProductsIndex({ products, categories, filters = {}, showPagination = true, warning = null }) {
    const { url } = usePage();
    const params = new URLSearchParams(url.split('?')[1] || '');
    
    const [search, setSearch] = useState(filters.search || '');
    const [categoryId, setCategoryId] = useState(filters.category_id || '');
    const [status, setStatus] = useState(filters.status || '');
    const [stock, setStock] = useState(filters.stock || '');
    const [selectedIds, setSelectedIds] = useState([]);
    const [selectAll, setSelectAll] = useState(false);
    const [showConfirmModal, setShowConfirmModal] = useState(false);
    const [bulkAction, setBulkAction] = useState('');

    const hasFilters = search || categoryId || status || stock;
    const currentPageIds = products?.data?.map(p => p.id) || [];
    const allSelected = currentPageIds.length > 0 && currentPageIds.every(id => selectedIds.includes(id));

    function handleSelectAll() {
        if (allSelected) {
            setSelectedIds(selectedIds.filter(id => !currentPageIds.includes(id)));
        } else {
            setSelectedIds([...new Set([...selectedIds, ...currentPageIds])]);
        }
        setSelectAll(!selectAll);
    }

    function handleSelectOne(id) {
        if (selectedIds.includes(id)) {
            setSelectedIds(selectedIds.filter(i => i !== id));
        } else {
            setSelectedIds([...selectedIds, id]);
        }
    }

    function handleBulkAction(action) {
        if (selectedIds.length === 0) return;
        setBulkAction(action);
        setShowConfirmModal(true);
    }

    function confirmBulkAction() {
        const formData = { ids: selectedIds };
        
        if (bulkAction === 'delete') {
            if (!confirm(`Are you sure you want to delete ${selectedIds.length} product(s)?`)) return;
            router.post('/admin/products/bulk-delete', formData, {
                onSuccess: () => {
                    setSelectedIds([]);
                    setShowConfirmModal(false);
                }
            });
        } else if (bulkAction === 'activate') {
            router.post('/admin/products/bulk-activate', formData, {
                onSuccess: () => {
                    setSelectedIds([]);
                    setShowConfirmModal(false);
                }
            });
        } else if (bulkAction === 'deactivate') {
            router.post('/admin/products/bulk-deactivate', formData, {
                onSuccess: () => {
                    setSelectedIds([]);
                    setShowConfirmModal(false);
                }
            });
        }
    }

    function applyFilters() {
        const query = new URLSearchParams();
        if (search) query.set('search', search);
        if (categoryId) query.set('category_id', categoryId);
        if (status) query.set('status', status);
        if (stock) query.set('stock', stock);
        window.location.href = `/admin/products${query.toString() ? '?' + query.toString() : ''}`;
    }

    function resetFilters() {
        setSearch('');
        setCategoryId('');
        setStatus('');
        setStock('');
        window.location.href = '/admin/products';
    }

    function handleDelete(id) {
        if (confirm('Are you sure you want to delete this product?')) {
            router.delete(`/admin/products/${id}`);
        }
    }

    return (
        <AdminLayout>
            <Head title="Products" />

            <div className="p-4 lg:p-6 space-y-4 lg:space-y-6">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <h1 className="text-xl lg:text-2xl font-bold text-gray-900">Products</h1>
                    <Link
                        href="/admin/products/create"
                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2 text-sm"
                    >
                        <i className="bi bi-plus-lg"></i>
                        <span className="hidden sm:inline">Add Product</span>
                        <span className="sm:hidden">Add</span>
                    </Link>
                </div>

                {/* Filters */}
                <div className="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        {/* Search */}
                        <div className="lg:col-span-2">
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by name or ID..."
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                            />
                        </div>

                        {/* Category Filter */}
                        <select
                            value={categoryId}
                            onChange={(e) => setCategoryId(e.target.value)}
                            className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Categories</option>
                            {categories?.map((cat) => (
                                <option key={cat.id} value={cat.id}>{cat.name}</option>
                            ))}
                        </select>

                        {/* Status Filter */}
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>

                        {/* Stock Filter */}
                        <select
                            value={stock}
                            onChange={(e) => setStock(e.target.value)}
                            className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Stock</option>
                            <option value="out_of_stock">Out of Stock</option>
                            <option value="low_stock">Low Stock (&lt;10)</option>
                            <option value="in_stock">In Stock (10+)</option>
                        </select>
                    </div>

                    <div className="flex gap-2">
                        <button
                            onClick={applyFilters}
                            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium"
                        >
                            Apply Filters
                        </button>
                        {hasFilters && (
                            <button
                                onClick={resetFilters}
                                className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium"
                            >
                                Reset Filters
                            </button>
                        )}
                    </div>

                    {/* Bulk Actions */}
                    {selectedIds.length > 0 && (
                        <div className="flex items-center gap-4 pt-2 border-t border-gray-200">
                            <span className="text-sm text-gray-600">{selectedIds.length} selected</span>
                            <div className="flex gap-2">
                                <button
                                    onClick={() => handleBulkAction('activate')}
                                    className="px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium"
                                >
                                    <i className="bi bi-check-circle me-1"></i> Activate
                                </button>
                                <button
                                    onClick={() => handleBulkAction('deactivate')}
                                    className="px-3 py-1.5 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors text-sm font-medium"
                                >
                                    <i className="bi bi-x-circle me-1"></i> Deactivate
                                </button>
                                <button
                                    onClick={() => handleBulkAction('delete')}
                                    className="px-3 py-1.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium"
                                >
                                    <i className="bi bi-trash me-1"></i> Delete
                                </button>
                            </div>
                        </div>
                    )}

                    {hasFilters && (
                        <p className="text-sm text-gray-500">
                            Showing {products?.total || 0} result(s)
                        </p>
                    )}
                </div>

                {/* Per Page Selector */}
                <div className="flex justify-between items-center">
                    <PerPageSelect />
                    {warning && (
                        <p className="text-sm text-amber-600">{warning}</p>
                    )}
                    {hasFilters && !warning && (
                        <p className="text-sm text-gray-500">
                            {products?.total || 0} result(s)
                        </p>
                    )}
                </div>

                {/* Products Table */}
                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-2 lg:px-4 py-3 text-left">
                                        <input
                                            type="checkbox"
                                            checked={allSelected}
                                            onChange={handleSelectAll}
                                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                    </th>
                                    <th className="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Image</th>
                                    <th className="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th className="hidden md:table-cell px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                    <th className="hidden lg:table-cell px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                    <th className="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                                    <th className="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th className="px-4 lg:px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {!products?.data?.length ? (
                                    <tr>
                                        <td colSpan="8" className="px-4 lg:px-6 py-12 text-center text-gray-500">
                                            {hasFilters ? 'No products found for your filters.' : 'No products yet.'}
                                            {!hasFilters && (
                                                <Link href="/admin/products/create" className="block mt-2 text-blue-600 hover:underline">
                                                    Add your first product →
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                ) : (
                                    products.data.map((product) => (
                                        <tr key={product.id} className="hover:bg-gray-50">
                                            <td className="px-2 lg:px-4 py-3 lg:py-4">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedIds.includes(product.id)}
                                                    onChange={() => handleSelectOne(product.id)}
                                                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                />
                                            </td>
                                            <td className="px-4 lg:px-6 py-3 lg:py-4">
                                                {product.photo1_url ? (
                                                    <img
                                                        src={product.photo1_url}
                                                        alt={product.name}
                                                        className="w-10 h-10 lg:w-12 lg:h-12 rounded-lg object-cover border border-gray-200"
                                                    />
                                                ) : (
                                                    <div className="w-10 h-10 lg:w-12 lg:h-12 rounded-lg bg-gray-100 flex items-center justify-center">
                                                        <i className="bi bi-image text-gray-400"></i>
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 lg:px-6 py-3 lg:py-4">
                                                <div className="text-sm font-medium text-gray-900 max-w-[150px] lg:max-w-none truncate">{product.name}</div>
                                                <div className="text-xs text-gray-500 hidden sm:block truncate max-w-[200px]">{product.description}</div>
                                            </td>
                                            <td className="hidden md:table-cell px-4 lg:px-6 py-3 lg:py-4 text-sm text-gray-600">{product.category?.name || '—'}</td>
                                            <td className="hidden lg:table-cell px-4 lg:px-6 py-3 lg:py-4 text-sm font-medium text-gray-900">{Number(product.price).toLocaleString()} MMK</td>
                                            <td className="px-4 lg:px-6 py-3 lg:py-4">
                                                <span className={`inline-flex items-center px-2 lg:px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                    product.stock === 0 ? 'bg-red-100 text-red-800' :
                                                    product.stock < 10 ? 'bg-yellow-100 text-yellow-800' :
                                                    'bg-green-100 text-green-800'
                                                }`}>
                                                    {product.stock}
                                                </span>
                                            </td>
                                            <td className="px-4 lg:px-6 py-3 lg:py-4">
                                                <span className={`inline-flex items-center px-2 lg:px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                    product.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'
                                                }`}>
                                                    {product.status || 'active'}
                                                </span>
                                            </td>
                                            <td className="px-4 lg:px-6 py-3 lg:py-4 text-right text-sm">
                                                <div className="flex justify-end gap-2">
                                                    <Link href={`/admin/products/${product.id}/edit`} className="text-blue-600 hover:text-blue-800 font-medium text-xs lg:text-sm">Edit</Link>
                                                    <button onClick={() => handleDelete(product.id)} className="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {showPagination && products?.links && products.links.length > 3 && (
                        <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                            <p className="text-sm text-gray-500">
                                Showing {products.from} to {products.to} of {products.total} results
                            </p>
                            <div className="flex gap-1">
                                {products.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url || '#'}
                                        className={`px-3 py-1 text-sm rounded-md transition-colors ${
                                            link.active ? 'bg-blue-600 text-white' : link.url ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed'
                                        }`}
                                    >
                                        {link.label.replace('&laquo;', '«').replace('&raquo;', '»').replace('Previous', '←').replace('Next', '→')}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}