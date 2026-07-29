import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { ArrowLeft, ArrowUpRight, ArrowDownRight, Package, ShoppingCart, RotateCcw, Settings, Truck, Building2, User, Calendar, FileText, MessageSquare } from 'lucide-react';
import { adminUrl } from '@/Utils/adminUrl';

const typeConfig = {
    opening_stock: { label: 'Opening Stock', icon: Package, class: 'bg-blue-50 text-blue-700 border-blue-200' },
    purchase: { label: 'Purchase', icon: ShoppingCart, class: 'bg-green-50 text-green-700 border-green-200' },
    sale: { label: 'Sale', icon: ArrowUpRight, class: 'bg-red-50 text-red-700 border-red-200' },
    return: { label: 'Return', icon: RotateCcw, class: 'bg-purple-50 text-purple-700 border-purple-200' },
    adjustment: { label: 'Adjustment', icon: Settings, class: 'bg-amber-50 text-amber-700 border-amber-200' },
    transfer: { label: 'Transfer', icon: Truck, class: 'bg-gray-50 text-gray-700 border-gray-200' },
};

export default function StockHistoryDetail({ movement = {} }) {
    const config = typeConfig[movement.type] ?? { label: movement.type, icon: Package, class: 'bg-gray-50 text-gray-700 border-gray-200' };
    const TypeIcon = config.icon;

    return (
        <AdminLayout>
            <Head title={`Movement #${movement.id}`} />

            <div className="py-6">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <div className="flex items-center gap-3">
                            <Link href={adminUrl('/admin/inventory/movements')} className="text-gray-400 hover:text-gray-600 dark:text-gray-400">
                                <ArrowLeft className="w-5 h-5" />
                            </Link>
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {movement.adjustment_number || `Movement #${movement.id}`}
                                </h1>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {new Date(movement.created_at).toLocaleString()}
                                </p>
                            </div>
                        </div>
                        <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium border ${config.class}`}>
                            <TypeIcon className="w-4 h-4" />
                            {config.label}
                        </span>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Product Info */}
                        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Product</h2>
                            {movement.product ? (
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                                        <Package className="w-5 h-5 text-blue-600" />
                                    </div>
                                    <div>
                                        <Link href={adminUrl(`/admin/inventory/product/${movement.product.id}`)} className="text-sm font-semibold text-gray-900 dark:text-gray-100 hover:text-blue-600">
                                            {movement.product.name}
                                        </Link>
                                        <div className="text-xs text-gray-400 dark:text-gray-500 font-mono">{movement.product.sku || 'No SKU'}</div>
                                        {movement.variant && (
                                            <div className="text-xs text-gray-400 dark:text-gray-500">Variant: {movement.variant.sku}</div>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <p className="text-sm text-gray-400 dark:text-gray-500">Deleted Product</p>
                            )}
                        </div>

                        {/* Location */}
                        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Inventory Location</h2>
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center">
                                    <Building2 className="w-5 h-5 text-green-600" />
                                </div>
                                <div>
                                    <div className="text-sm font-semibold text-gray-900 dark:text-gray-100">{movement.warehouse?.name || 'Primary Store'}</div>
                                    {movement.warehouse?.code && (
                                        <div className="text-xs text-gray-400 dark:text-gray-500 font-mono">{movement.warehouse.code}</div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Stock Change */}
                        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 lg:col-span-2">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Stock Change</h2>
                            <div className="grid grid-cols-3 gap-6 text-center">
                                <div className="bg-gray-50 dark:bg-gray-950 rounded-lg p-4">
                                    <div className="text-xs text-gray-500 dark:text-gray-400 mb-1">Before</div>
                                    <div className="text-3xl font-bold text-gray-900 dark:text-gray-100">{movement.before_stock}</div>
                                </div>
                                <div className="bg-gray-50 dark:bg-gray-950 rounded-lg p-4">
                                    <div className="text-xs text-gray-500 dark:text-gray-400 mb-1">Change</div>
                                    <div className={`text-3xl font-bold flex items-center justify-center gap-1 ${movement.quantity > 0 ? 'text-green-600' : 'text-red-600'}`}>
                                        {movement.quantity > 0 ? <ArrowUpRight className="w-6 h-6" /> : <ArrowDownRight className="w-6 h-6" />}
                                        {movement.quantity > 0 ? '+' : ''}{movement.quantity}
                                    </div>
                                </div>
                                <div className="bg-gray-50 dark:bg-gray-950 rounded-lg p-4">
                                    <div className="text-xs text-gray-500 dark:text-gray-400 mb-1">After</div>
                                    <div className="text-3xl font-bold text-gray-900 dark:text-gray-100">{movement.after_stock}</div>
                                </div>
                            </div>
                        </div>

                        {/* Details */}
                        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 lg:col-span-2">
                            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Details</h2>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="flex items-start gap-3">
                                    <FileText className="w-4 h-4 text-gray-400 dark:text-gray-500 mt-0.5 flex-shrink-0" />
                                    <div>
                                        <div className="text-xs text-gray-500 dark:text-gray-400">Type</div>
                                        <div className="text-sm font-medium text-gray-900 dark:text-gray-100">{config.label}</div>
                                    </div>
                                </div>

                                {movement.adjustment_number && (
                                    <div className="flex items-start gap-3">
                                        <FileText className="w-4 h-4 text-gray-400 dark:text-gray-500 mt-0.5 flex-shrink-0" />
                                        <div>
                                            <div className="text-xs text-gray-500 dark:text-gray-400">Adjustment Number</div>
                                            <div className="text-sm font-mono font-medium text-gray-900 dark:text-gray-100">{movement.adjustment_number}</div>
                                        </div>
                                    </div>
                                )}

                                {movement.reason_label && (
                                    <div className="flex items-start gap-3">
                                        <FileText className="w-4 h-4 text-gray-400 dark:text-gray-500 mt-0.5 flex-shrink-0" />
                                        <div>
                                            <div className="text-xs text-gray-500 dark:text-gray-400">Reason</div>
                                            <div className="text-sm font-medium text-gray-900 dark:text-gray-100">{movement.reason_label}</div>
                                        </div>
                                    </div>
                                )}

                                {movement.reference_type && (
                                    <div className="flex items-start gap-3">
                                        <FileText className="w-4 h-4 text-gray-400 dark:text-gray-500 mt-0.5 flex-shrink-0" />
                                        <div>
                                            <div className="text-xs text-gray-500 dark:text-gray-400">Reference</div>
                                            <div className="text-sm font-medium text-gray-900 dark:text-gray-100 capitalize">
                                                {movement.reference_type.replace('_', ' ')} #{movement.reference_id}
                                            </div>
                                        </div>
                                    </div>
                                )}

                                <div className="flex items-start gap-3">
                                    <User className="w-4 h-4 text-gray-400 dark:text-gray-500 mt-0.5 flex-shrink-0" />
                                    <div>
                                        <div className="text-xs text-gray-500 dark:text-gray-400">User</div>
                                        <div className="text-sm font-medium text-gray-900 dark:text-gray-100">System</div>
                                    </div>
                                </div>

                                <div className="flex items-start gap-3">
                                    <Calendar className="w-4 h-4 text-gray-400 dark:text-gray-500 mt-0.5 flex-shrink-0" />
                                    <div>
                                        <div className="text-xs text-gray-500 dark:text-gray-400">Date</div>
                                        <div className="text-sm font-medium text-gray-900 dark:text-gray-100">{new Date(movement.created_at).toLocaleString()}</div>
                                    </div>
                                </div>

                                {movement.description && (
                                    <div className="flex items-start gap-3 sm:col-span-2">
                                        <MessageSquare className="w-4 h-4 text-gray-400 dark:text-gray-500 mt-0.5 flex-shrink-0" />
                                        <div>
                                            <div className="text-xs text-gray-500 dark:text-gray-400">Notes</div>
                                            <div className="text-sm text-gray-700 dark:text-gray-300">{movement.description}</div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="mt-6">
                        <Link
                            href={adminUrl('/admin/inventory/movements')}
                            className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50"
                        >
                            Back to Stock History
                        </Link>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
