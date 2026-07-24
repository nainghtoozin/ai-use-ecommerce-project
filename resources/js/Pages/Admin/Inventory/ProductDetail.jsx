import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    ArrowLeft, ArrowUpRight, ArrowDownRight, Package,
    CheckCircle, AlertTriangle, XCircle, Edit,
    ShoppingCart, RefreshCw, Settings, Truck,
} from 'lucide-react';
import { adminUrl } from '@/Utils/adminUrl';
import Pagination from '@/Components/Pagination';

const stockBadge = (status) => {
    switch (status) {
        case 'in_stock':
            return { label: 'Healthy Stock', class: 'bg-green-50 text-green-700 border-green-200', icon: CheckCircle };
        case 'low_stock':
            return { label: 'Low Stock', class: 'bg-amber-50 text-amber-700 border-amber-200', icon: AlertTriangle };
        case 'out_of_stock':
            return { label: 'Out of Stock', class: 'bg-red-50 text-red-700 border-red-200', icon: XCircle };
        default:
            return { label: 'Unknown', class: 'bg-gray-50 text-gray-600 border-gray-200', icon: Package };
    }
};

const typeConfig = {
    opening_stock: { label: 'Opening Stock', icon: Package, class: 'bg-blue-50 text-blue-700 border-blue-200' },
    purchase: { label: 'Purchase', icon: ShoppingCart, class: 'bg-green-50 text-green-700 border-green-200' },
    sale: { label: 'Sale', icon: ArrowUpRight, class: 'bg-red-50 text-red-700 border-red-200' },
    return: { label: 'Return', icon: RefreshCw, class: 'bg-purple-50 text-purple-700 border-purple-200' },
    adjustment: { label: 'Adjustment', icon: Settings, class: 'bg-amber-50 text-amber-700 border-amber-200' },
    transfer: { label: 'Transfer', icon: Truck, class: 'bg-gray-50 text-gray-700 border-gray-200' },
};

export default function ProductDetail({ product = {}, movements = { data: [], meta: {} } }) {
    const badge = stockBadge(product.stock_status);
    const BadgeIcon = badge.icon;

    return (
        <AdminLayout>
            <Head title={`${product.name} - Inventory`} />

            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-3 mb-6">
                        <Link href={adminUrl('/admin/inventory')} className="text-gray-400 hover:text-gray-600">
                            <ArrowLeft className="w-5 h-5" />
                        </Link>
                        <div className="flex-1 min-w-0">
                            <h1 className="text-2xl font-semibold text-gray-900 truncate">{product.name}</h1>
                            <p className="text-sm text-gray-500">Inventory details and movement history.</p>
                        </div>
                        <Link
                            href={adminUrl(`/admin/products/${product.id}/edit`)}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                        >
                            <Edit className="w-4 h-4" />
                            Edit Product
                        </Link>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                        <div className="lg:col-span-2">
                            <div className="bg-white rounded-xl border border-gray-200 p-6">
                                <h2 className="text-sm font-semibold text-gray-900 mb-4">Basic Information</h2>
                                <div className="grid grid-cols-2 gap-y-4 gap-x-8">
                                    {[
                                        ['SKU', product.sku || '-'],
                                        ['Type', product.type?.replace('_', ' ') || '-'],
                                        ['Category', product.category || '-'],
                                        ['Unit', product.unit || '-'],
                                        ['Price', product.price ? `${product.price}` : '-'],
                                        ['Status', product.status],
                                    ].map(([label, value]) => (
                                        <div key={label}>
                                            <div className="text-xs text-gray-400 mb-0.5">{label}</div>
                                            <div className="text-sm font-medium text-gray-900 capitalize">{value}</div>
                                        </div>
                                    ))}
                                </div>

                                {product.variants?.length > 0 && (
                                    <div className="mt-6 pt-6 border-t border-gray-100">
                                        <h3 className="text-sm font-semibold text-gray-900 mb-3">Variants ({product.variant_count})</h3>
                                        <div className="space-y-2">
                                            {product.variants.map((v) => (
                                                <div key={v.id} className="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-2.5">
                                                    <div>
                                                        <span className="text-sm font-medium text-gray-900">{v.sku || `Variant #${v.id}`}</span>
                                                        {v.attributes && <span className="text-xs text-gray-400 ml-2">{JSON.stringify(v.attributes)}</span>}
                                                    </div>
                                                    <div className="text-right">
                                                        <span className="text-sm font-semibold text-gray-900">{v.stock}</span>
                                                        <span className="text-xs text-gray-400 ml-1">{product.unit}</span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="lg:col-span-1">
                            <div className="bg-white rounded-xl border border-gray-200 p-6">
                                <h2 className="text-sm font-semibold text-gray-900 mb-4">Current Stock</h2>
                                <div className="text-center py-4">
                                    <div className="text-5xl font-bold text-gray-900">{product.stock}</div>
                                    <div className="text-sm text-gray-400 mt-1">{product.unit}</div>
                                    <div className="mt-4">
                                        <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium border ${badge.class}`}>
                                            <BadgeIcon className="w-4 h-4" />
                                            {badge.label}
                                        </span>
                                    </div>
                                </div>
                                <div className="mt-4 pt-4 border-t border-gray-100 space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Low Stock Alert</span>
                                        <span className="font-medium text-gray-900">{product.low_stock_alert}</span>
                                    </div>
                                    {product.stock_status !== 'out_of_stock' && (
                                        <div className="flex justify-between">
                                            <span className="text-gray-500">Stock Used</span>
                                            <span className="font-medium text-gray-900">{Math.max(0, product.calculated_stock - product.stock)}</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="px-6 py-4 border-b border-gray-100">
                            <div className="flex items-center gap-2">
                                <RefreshCw className="w-4 h-4 text-gray-400" />
                                <h2 className="text-sm font-semibold text-gray-900">Movement Timeline</h2>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {movements.data?.length === 0 && (
                                        <tr>
                                            <td colSpan="5" className="px-6 py-16 text-center text-gray-500">
                                                <RefreshCw className="w-12 h-12 mx-auto mb-3 text-gray-300" />
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
                                                    ) : <span className="text-xs text-gray-300">-</span>}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-500 max-w-[200px]">
                                                    {m.description || <span className="text-gray-300">-</span>}
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
