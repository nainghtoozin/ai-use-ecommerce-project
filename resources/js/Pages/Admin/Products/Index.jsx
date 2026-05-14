import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { assetUrl } from '@/Utils/helpers';

export default function AdminProductsIndex({ products, query = '' }) {
    const [search, setSearch] = useState(query);

    function handleSearch(e) {
        e.preventDefault();
        router.get('/admin/products/search', { query: search }, { preserveState: true });
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

                <form onSubmit={handleSearch} className="flex gap-2">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search products..."
                        className="flex-1 border border-gray-300 rounded-lg px-3 lg:px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <button type="submit" className="px-3 lg:px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        <i className="bi bi-search"></i>
                    </button>
                </form>

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Image</th>
                                    <th className="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th className="hidden md:table-cell px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                    <th className="hidden lg:table-cell px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                    <th className="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                                    <th className="px-4 lg:px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {!products?.data?.length ? (
                                    <tr>
                                        <td colSpan="6" className="px-4 lg:px-6 py-12 text-center text-gray-500">
                                            {search ? 'No products found for your search.' : 'No products yet.'}
                                            {!search && (
                                                <Link href="/admin/products/create" className="block mt-2 text-blue-600 hover:underline">
                                                    Add your first product →
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                ) : (
products.data.map((product) => (
                                        <tr key={product.id} className="hover:bg-gray-50">
                                            <td className="px-4 lg:px-6 py-3 lg:py-4">
                                                {product.photo1 ? (
                                                    <img
                                                        src={assetUrl(product.photo1)}
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
                    {products?.links && products.links.length > 3 && (
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
