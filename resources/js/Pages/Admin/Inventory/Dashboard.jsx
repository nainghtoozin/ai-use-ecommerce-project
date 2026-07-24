import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Package, AlertTriangle, CheckCircle, XCircle, ArrowRight, Clock, Activity } from 'lucide-react';
import { adminUrl } from '@/Utils/adminUrl';

const stockStatusConfig = {
    in_stock: { label: 'In Stock', color: 'text-green-600', bg: 'bg-green-50', border: 'border-green-200', icon: CheckCircle },
    low_stock: { label: 'Low Stock', color: 'text-amber-600', bg: 'bg-amber-50', border: 'border-amber-200', icon: AlertTriangle },
    out_of_stock: { label: 'Out of Stock', color: 'text-red-600', bg: 'bg-red-50', border: 'border-red-200', icon: XCircle },
};

const typeLabels = {
    opening_stock: 'Opening Stock',
    purchase: 'Purchase',
    sale: 'Sale',
    return: 'Return',
    adjustment: 'Adjustment',
    transfer: 'Transfer',
};

export default function InventoryDashboard({ stats = {}, recentMovements = [], recentActivity = [] }) {
    const statCards = [
        { label: 'Total Products', value: stats.total_products, icon: Package, color: 'text-blue-600', bg: 'bg-blue-50' },
        { label: 'In Stock', value: stats.in_stock, icon: CheckCircle, color: 'text-green-600', bg: 'bg-green-50' },
        { label: 'Low Stock', value: stats.low_stock, icon: AlertTriangle, color: 'text-amber-600', bg: 'bg-amber-50' },
        { label: 'Out of Stock', value: stats.out_of_stock, icon: XCircle, color: 'text-red-600', bg: 'bg-red-50' },
    ];

    return (
        <AdminLayout>
            <Head title="Inventory Dashboard" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <div className="flex items-center gap-3">
                            <Package className="w-8 h-8 text-gray-500" />
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900">Inventory Dashboard</h1>
                                <p className="text-sm text-gray-500">Overview of your stock and inventory activity.</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Link href={adminUrl('/admin/inventory')} className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Products Inventory
                            </Link>
                            <Link href={adminUrl('/admin/inventory/movements')} className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Stock Movements
                            </Link>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                        {statCards.map((card) => {
                            const Icon = card.icon;
                            return (
                                <div key={card.label} className="bg-white rounded-xl border border-gray-200 p-5">
                                    <div className="flex items-center justify-between mb-3">
                                        <span className="text-sm font-medium text-gray-500">{card.label}</span>
                                        <div className={`p-2 rounded-lg ${card.bg}`}>
                                            <Icon className={`w-4 h-4 ${card.color}`} />
                                        </div>
                                    </div>
                                    <div className="text-3xl font-bold text-gray-900">{card.value}</div>
                                </div>
                            );
                        })}
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                                <div className="flex items-center gap-2">
                                    <Clock className="w-4 h-4 text-gray-400" />
                                    <h2 className="text-sm font-semibold text-gray-900">Recent Movements</h2>
                                </div>
                                <Link href={adminUrl('/admin/inventory/movements')} className="text-xs font-medium text-blue-600 hover:text-blue-700 inline-flex items-center gap-1">
                                    View All <ArrowRight className="w-3 h-3" />
                                </Link>
                            </div>
                            <div className="divide-y divide-gray-100">
                                {recentMovements.length === 0 && (
                                    <div className="px-5 py-10 text-center text-gray-400 text-sm">
                                        <Clock className="w-8 h-8 mx-auto mb-2 text-gray-300" />
                                        <p>No stock movement yet.</p>
                                    </div>
                                )}
                                {recentMovements.slice(0, 7).map((m) => (
                                    <div key={m.id} className="px-5 py-3 flex items-center justify-between hover:bg-gray-50">
                                        <div className="flex items-center gap-3 min-w-0">
                                            <div className={`w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 ${m.quantity > 0 ? 'bg-green-50' : 'bg-red-50'}`}>
                                                <span className={`text-xs font-bold ${m.quantity > 0 ? 'text-green-600' : 'text-red-600'}`}>
                                                    {m.quantity > 0 ? '+' : ''}{m.quantity}
                                                </span>
                                            </div>
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-gray-900 truncate">{m.product_name}</p>
                                                <p className="text-xs text-gray-400">{typeLabels[m.type] ?? m.type}{m.product_sku ? ` · ${m.product_sku}` : ''}</p>
                                            </div>
                                        </div>
                                        <div className="text-xs text-gray-400 flex-shrink-0 ml-3">{m.created_at}</div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                                <div className="flex items-center gap-2">
                                    <Activity className="w-4 h-4 text-gray-400" />
                                    <h2 className="text-sm font-semibold text-gray-900">Recent Activity</h2>
                                </div>
                            </div>
                            <div className="divide-y divide-gray-100">
                                {recentActivity.length === 0 && (
                                    <div className="px-5 py-10 text-center text-gray-400 text-sm">
                                        <Activity className="w-8 h-8 mx-auto mb-2 text-gray-300" />
                                        <p>No inventory activity yet.</p>
                                    </div>
                                )}
                                {recentActivity.slice(0, 7).map((a) => (
                                    <div key={a.id} className="px-5 py-3 flex items-start gap-3 hover:bg-gray-50">
                                        <div className="w-1.5 h-1.5 rounded-full bg-blue-400 mt-2 flex-shrink-0" />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm text-gray-700 truncate">{a.description}</p>
                                            <p className="text-xs text-gray-400 mt-0.5">{a.created_at}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
