import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { ArrowLeft, ArrowUpRight, ArrowDownRight, Search, ArrowUpDown, Package, RefreshCw, ShoppingCart, RotateCcw, Settings, Truck } from 'lucide-react';
import { useState } from 'react';
import { adminUrl } from '@/Utils/adminUrl';
import Pagination from '@/Components/Pagination';

const typeConfig = {
    opening_stock: { label: 'Opening Stock', icon: Package, class: 'bg-blue-50 text-blue-700 border-blue-200' },
    purchase: { label: 'Purchase', icon: ShoppingCart, class: 'bg-green-50 text-green-700 border-green-200' },
    sale: { label: 'Sale', icon: ArrowUpRight, class: 'bg-red-50 text-red-700 border-red-200' },
    return: { label: 'Return', icon: RotateCcw, class: 'bg-purple-50 text-purple-700 border-purple-200' },
    adjustment: { label: 'Adjustment', icon: Settings, class: 'bg-amber-50 text-amber-700 border-amber-200' },
    transfer: { label: 'Transfer', icon: Truck, class: 'bg-gray-50 text-gray-700 border-gray-200' },
};

export default function Movements({ movements = { data: [], meta: {} }, filters = {}, products = [], types = [] }) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilter = (key, value) => {
        router.get(adminUrl('/admin/inventory/movements'), { ...filters, [key]: value || undefined }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AdminLayout>
            <Head title="Stock Movements" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-3 mb-6">
                        <Link href={adminUrl('/admin/inventory/dashboard')} className="text-gray-400 hover:text-gray-600">
                            <ArrowLeft className="w-5 h-5" />
                        </Link>
                        <div className="flex-1">
                            <h1 className="text-2xl font-semibold text-gray-900">Stock Movements</h1>
                            <p className="text-sm text-gray-500">History of all inventory changes.</p>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg border border-gray-200">
                        <div className="p-4 border-b border-gray-200 space-y-3">
                            <div className="flex flex-wrap gap-3">
                                <div className="relative flex-1 min-w-[200px]">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                    <input
                                        type="text"
                                        placeholder="Search product or description..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && applyFilter('search', search)}
                                        className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                                <select
                                    value={filters.product_id ?? ''}
                                    onChange={(e) => applyFilter('product_id', e.target.value)}
                                    className="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 min-w-[180px]"
                                >
                                    <option value="">All Products</option>
                                    {products?.map((p) => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                                <select
                                    value={filters.type ?? ''}
                                    onChange={(e) => applyFilter('type', e.target.value)}
                                    className="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">All Types</option>
                                    {types?.map((t) => (
                                        <option key={t} value={t}>{typeConfig[t]?.label ?? t}</option>
                                    ))}
                                </select>
                                <input
                                    type="date"
                                    value={filters.date_from ?? ''}
                                    onChange={(e) => applyFilter('date_from', e.target.value)}
                                    className="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="From"
                                />
                                <input
                                    type="date"
                                    value={filters.date_to ?? ''}
                                    onChange={(e) => applyFilter('date_to', e.target.value)}
                                    className="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="To"
                                />
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Movement Type</th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {movements.data?.length === 0 && (
                                        <tr>
                                            <td colSpan="6" className="px-6 py-16 text-center text-gray-500">
                                                <Package className="w-12 h-12 mx-auto mb-3 text-gray-300" />
                                                <p className="text-sm font-medium text-gray-900 mb-1">No stock movement yet.</p>
                                                <p className="text-xs text-gray-400">Movements will appear here when stock changes occur.</p>
                                            </td>
                                        </tr>
                                    )}
                                    {movements.data?.map((m) => {
                                        const config = typeConfig[m.type] ?? { label: m.type, icon: Package, class: 'bg-gray-50 text-gray-700 border-gray-200' };
                                        const TypeIcon = config.icon;
                                        return (
                                            <tr key={m.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{new Date(m.created_at).toLocaleString()}</td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <Link href={adminUrl(`/admin/inventory/product/${m.product?.id}`)} className="text-sm font-medium text-gray-900 hover:text-blue-600">
                                                        {m.product?.name ?? 'Deleted Product'}
                                                    </Link>
                                                    {m.product?.sku && <div className="text-xs text-gray-400">{m.product.sku}</div>}
                                                    {m.variant && <div className="text-xs text-gray-400">Variant: {m.variant.sku}</div>}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium border ${config.class}`}>
                                                        <TypeIcon className="w-3 h-3" />
                                                        {config.label}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right">
                                                    <span className={`inline-flex items-center gap-1 text-sm font-semibold font-mono ${m.quantity > 0 ? 'text-green-600' : 'text-red-600'}`}>
                                                        {m.quantity > 0 ? <ArrowUpRight className="w-3.5 h-3.5" /> : <ArrowDownRight className="w-3.5 h-3.5" />}
                                                        {m.quantity > 0 ? '+' : ''}{m.quantity}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {m.reference_type ? (
                                                        <span className="text-xs font-medium text-gray-400 capitalize">
                                                            {m.reference_type.replace('_', ' ')} #{m.reference_id}
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs text-gray-300">-</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-500 max-w-[200px]">
                                                    {m.description ? (
                                                        <span className="truncate block" title={m.description}>{m.description}</span>
                                                    ) : (
                                                        <span className="text-gray-300">-</span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {movements?.meta && <Pagination meta={movements.meta} />}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
