import { Head, Link, usePage, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    Package, AlertTriangle, Search,
    ExternalLink, ArrowUpDown, Eye, Edit, LayoutDashboard, Plus,
} from 'lucide-react';
import { useState } from 'react';
import { adminUrl } from '@/Utils/adminUrl';
import Pagination from '@/Components/Pagination';

const stockBadge = (status) => {
    switch (status) {
        case 'in_stock':
            return { label: 'Healthy Stock', class: 'bg-green-50 text-green-700 border-green-200' };
        case 'low_stock':
            return { label: 'Low Stock', class: 'bg-amber-50 text-amber-700 border-amber-200' };
        case 'out_of_stock':
            return { label: 'Out of Stock', class: 'bg-red-50 text-red-700 border-red-200' };
        default:
            return { label: 'Unknown', class: 'bg-gray-50 text-gray-600 border-gray-200' };
    }
};

export default function Index({ products = { data: [], meta: {} }, filters: rawFilters, stats = {} }) {
    const filters = rawFilters && typeof rawFilters === 'object' && !Array.isArray(rawFilters) ? rawFilters : {};
    const { featureStatus, auth } = usePage().props;
    const enabled = featureStatus?.inventory_management?.enabled !== false;
    const userPermissions = auth?.user?.permissions || [];
    const isSuperAdmin = auth?.user?.is_superadmin;
    const isOwner = auth?.user?.is_owner;
    const can = (perm) => isSuperAdmin || isOwner || userPermissions?.includes(perm);

    const [search, setSearch] = useState(filters.search ?? '');
    const [stockFilter, setStockFilter] = useState(filters.stock_status ?? '');
    const [sortField, setSortField] = useState(filters.sort ?? 'name');
    const [sortDir, setSortDir] = useState(filters.direction ?? 'asc');

    const applyFilter = (key, value) => {
        router.get(adminUrl('/admin/inventory'), { ...filters, [key]: value || undefined }, { preserveState: true, preserveScroll: true });
    };

    const toggleSort = (field) => {
        const dir = sortField === field && sortDir === 'asc' ? 'desc' : 'asc';
        setSortField(field);
        setSortDir(dir);
        applyFilter('sort', field);
        applyFilter('direction', dir);
    };

    const SortIcon = ({ field }) => {
        if (sortField !== field) return <ArrowUpDown className="w-3 h-3 text-gray-300" />;
        return <ArrowUpDown className={`w-3 h-3 ${sortDir === 'asc' ? 'text-blue-500' : 'text-blue-500 rotate-180'}`} />;
    };

    return (
        <AdminLayout>
            <Head title="Products Inventory" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <div className="flex items-center gap-3">
                            <Package className="w-8 h-8 text-gray-500" />
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900">Products Inventory</h1>
                                <p className="text-sm text-gray-500">Stock levels and inventory status across all products.</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Link href={adminUrl('/admin/inventory/dashboard')} className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                <LayoutDashboard className="w-4 h-4" />
                                Dashboard
                            </Link>
                            <Link href={adminUrl('/admin/inventory/movements')} className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                <ExternalLink className="w-4 h-4" />
                                Stock Movements
                            </Link>
                            {can('products.create') && (
                                <Link href={adminUrl('/admin/products/create')} className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                                    <Plus className="w-4 h-4" />
                                    Add Product
                                </Link>
                            )}
                        </div>
                    </div>

                    {!enabled && (
                        <div className="mb-6 flex items-center gap-2 text-sm text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                            <AlertTriangle className="w-4 h-4" />
                            <span>Inventory management is not available on your current plan. Upgrade to enable.</span>
                        </div>
                    )}

                    <div className="bg-white rounded-lg border border-gray-200">
                        <div className="p-4 border-b border-gray-200">
                            <div className="flex flex-wrap gap-3">
                                <div className="relative flex-1 min-w-[200px]">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                    <input
                                        type="text"
                                        placeholder="Search by name or SKU..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && applyFilter('search', search)}
                                        className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                                <select
                                    value={stockFilter}
                                    onChange={(e) => applyFilter('stock_status', e.target.value)}
                                    className="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">All Stock</option>
                                    <option value="in_stock">In Stock</option>
                                    <option value="low_stock">Low Stock</option>
                                    <option value="out_of_stock">Out of Stock</option>
                                </select>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer select-none" onClick={() => toggleSort('stock')}>
                                            <span className="inline-flex items-center gap-1">Stock <SortIcon field="stock" /></span>
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer select-none" onClick={() => toggleSort('updated_at')}>
                                            <span className="inline-flex items-center gap-1">Last Updated <SortIcon field="updated_at" /></span>
                                        </th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {products.data?.length === 0 && (
                                        <tr>
                                            <td colSpan="8" className="px-6 py-16 text-center text-gray-500">
                                                <Package className="w-12 h-12 mx-auto mb-3 text-gray-300" />
                                                <p className="text-sm font-medium text-gray-900 mb-1">No inventory data available.</p>
                                                <p className="text-xs text-gray-400">Products will appear here once you add them to your catalog.</p>
                                            </td>
                                        </tr>
                                    )}
                                    {products.data?.map((p) => {
                                        const badge = stockBadge(p.stock_status);
                                        return (
                                            <tr key={p.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => router.get(adminUrl(`/admin/inventory/product/${p.id}`))}>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-medium text-gray-900">{p.name}</div>
                                                    {p.variant_count > 0 && (
                                                        <div className="text-xs text-gray-400">{p.variant_count} variant{p.variant_count !== 1 ? 's' : ''}</div>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">{p.sku || '-'}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{p.category || '-'}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right">
                                                    <span className="text-lg font-semibold text-gray-900">{p.stock}</span>
                                                    <span className="text-xs text-gray-400 ml-1">{p.unit}</span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{p.unit || '-'}</td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${badge.class}`}>
                                                        {badge.label}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{p.updated_at ? new Date(p.updated_at).toLocaleDateString() : '-'}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right">
                                                    <div className="flex items-center justify-end gap-1" onClick={(e) => e.stopPropagation()}>
                                                        <Link
                                                            href={adminUrl(`/admin/inventory/product/${p.id}`)}
                                                            className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                                                            title="View Inventory"
                                                        >
                                                            <Eye className="w-4 h-4" />
                                                        </Link>
                                                        {can('products.edit') && (
                                                            <Link
                                                                href={adminUrl(`/admin/products/${p.id}/edit`)}
                                                                className="p-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors"
                                                                title="Edit Product"
                                                            >
                                                                <Edit className="w-4 h-4" />
                                                            </Link>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {products?.meta && <Pagination meta={products.meta} />}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
