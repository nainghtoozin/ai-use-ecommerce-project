import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { ArrowLeft, ArrowUpRight, ArrowDownRight, Plus, Settings, Eye, Trash2, X, Search, Filter, ChevronDown } from 'lucide-react';
import { adminUrl } from '@/Utils/adminUrl';
import Pagination from '@/Components/Pagination';

const reasonBadgeClass = {
    opening_correction: 'bg-blue-50 text-blue-700 border-blue-200',
    stock_count: 'bg-purple-50 text-purple-700 border-purple-200',
    damaged: 'bg-red-50 text-red-700 border-red-200',
    lost: 'bg-orange-50 text-orange-700 border-orange-200',
    expired: 'bg-yellow-50 text-yellow-700 border-yellow-200',
    manual_correction: 'bg-gray-50 text-gray-700 border-gray-200',
    customer_return: 'bg-green-50 text-green-700 border-green-200',
    supplier_correction: 'bg-teal-50 text-teal-700 border-teal-200',
    other: 'bg-slate-50 text-slate-700 border-slate-200',
};

export default function Adjustments({ adjustments = { data: [], meta: {} }, warehouses = [], reasons = {}, filters = {} }) {
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [showFilters, setShowFilters] = useState(false);
    const [search, setSearch] = useState(filters.search || '');

    const applyFilter = (key, value) => {
        router.get(adminUrl('/admin/inventory/adjustments'), { ...filters, [key]: value || undefined, page: 1 }, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        setSearch('');
        router.get(adminUrl('/admin/inventory/adjustments'), { per_page: filters.per_page }, { preserveState: true, preserveScroll: true });
    };

    const hasActiveFilters = filters.search || filters.reason || filters.type || filters.warehouse_id || filters.date_from || filters.date_to;

    const handleDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(adminUrl(`/admin/inventory/adjustments/${deleteTarget.id}`), {
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    return (
        <AdminLayout>
            <Head title="Stock Adjustments" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <div className="flex items-center gap-3">
                            <Link href={adminUrl('/admin/inventory/dashboard')} className="text-gray-400 hover:text-gray-600 dark:text-gray-400">
                                <ArrowLeft className="w-5 h-5" />
                            </Link>
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Stock Adjustments</h1>
                                <p className="text-sm text-gray-500 dark:text-gray-400">History of all stock corrections and adjustments.</p>
                            </div>
                        </div>
                        <Link
                            href={adminUrl('/admin/inventory/adjustments/create')}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
                        >
                            <Plus className="w-4 h-4" />
                            New Adjustment
                        </Link>
                    </div>

                    {/* Search and Filters */}
                    <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 mb-4">
                        <div className="p-4 border-b border-gray-200 dark:border-gray-800">
                            <div className="flex flex-wrap gap-3">
                                <div className="relative flex-1 min-w-[200px]">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" />
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && applyFilter('search', search)}
                                        placeholder="Search by product, SKU, or adjustment number..."
                                        className="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setShowFilters(!showFilters)}
                                    className={`inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium border rounded-lg transition-colors ${
                                        hasActiveFilters
                                            ? 'bg-blue-50 border-blue-300 text-blue-700'
                                            : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                                    }`}
                                >
                                    <Filter className="w-4 h-4" />
                                    Filters
                                    {hasActiveFilters && (
                                        <span className="w-5 h-5 flex items-center justify-center bg-blue-600 text-white text-xs rounded-full">
                                            {[filters.reason, filters.type, filters.warehouse_id, filters.date_from, filters.date_to].filter(Boolean).length}
                                        </span>
                                    )}
                                </button>
                                <select
                                    value={filters.per_page || '15'}
                                    onChange={(e) => applyFilter('per_page', e.target.value)}
                                    className="border border-gray-300 dark:border-gray-700 rounded-lg text-sm px-3 py-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="10">10</option>
                                    <option value="15">15</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                        </div>

                        {showFilters && (
                            <div className="p-4 bg-gray-50 dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800">
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                                    <div>
                                        <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Reason</label>
                                        <select
                                            value={filters.reason || ''}
                                            onChange={(e) => applyFilter('reason', e.target.value)}
                                            className="w-full border border-gray-300 dark:border-gray-700 rounded-lg text-sm px-3 py-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        >
                                            <option value="">All Reasons</option>
                                            {Object.entries(reasons).map(([key, label]) => (
                                                <option key={key} value={key}>{label}</option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Type</label>
                                        <select
                                            value={filters.type || ''}
                                            onChange={(e) => applyFilter('type', e.target.value)}
                                            className="w-full border border-gray-300 dark:border-gray-700 rounded-lg text-sm px-3 py-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        >
                                            <option value="">All Types</option>
                                            <option value="increase">Increase</option>
                                            <option value="decrease">Decrease</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Location</label>
                                        <select
                                            value={filters.warehouse_id || ''}
                                            onChange={(e) => applyFilter('warehouse_id', e.target.value)}
                                            className="w-full border border-gray-300 dark:border-gray-700 rounded-lg text-sm px-3 py-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        >
                                            <option value="">All Locations</option>
                                            {warehouses.map((wh) => (
                                                <option key={wh.id} value={wh.id}>{wh.name}</option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">From</label>
                                        <input
                                            type="date"
                                            value={filters.date_from || ''}
                                            onChange={(e) => applyFilter('date_from', e.target.value)}
                                            className="w-full border border-gray-300 dark:border-gray-700 rounded-lg text-sm px-3 py-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">To</label>
                                        <input
                                            type="date"
                                            value={filters.date_to || ''}
                                            onChange={(e) => applyFilter('date_to', e.target.value)}
                                            className="w-full border border-gray-300 dark:border-gray-700 rounded-lg text-sm px-3 py-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        />
                                    </div>
                                </div>

                                {hasActiveFilters && (
                                    <div className="mt-3 flex justify-end">
                                        <button
                                            type="button"
                                            onClick={clearFilters}
                                            className="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-300 underline"
                                        >
                                            Clear all filters
                                        </button>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Table */}
                    <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                <thead className="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Adjustment #</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Product</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Location</th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Before</th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Change</th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">After</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reason</th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                                    {adjustments.data?.length === 0 && (
                                        <tr>
                                            <td colSpan="9" className="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                                <Settings className="w-12 h-12 mx-auto mb-3 text-gray-300" />
                                                <p className="text-sm font-medium text-gray-900 dark:text-gray-100 mb-1">
                                                    {hasActiveFilters ? 'No adjustments match your filters.' : 'No adjustments yet.'}
                                                </p>
                                                <p className="text-xs text-gray-400 dark:text-gray-500">
                                                    {hasActiveFilters ? 'Try adjusting your search or filters.' : 'Adjustments will appear here when stock corrections are made.'}
                                                </p>
                                            </td>
                                        </tr>
                                    )}
                                    {adjustments.data?.map((a) => {
                                        const badgeClass = reasonBadgeClass[a.reason_key] || reasonBadgeClass.other;
                                        return (
                                            <tr key={a.id} className="hover:bg-gray-50 dark:bg-gray-950">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <Link href={adminUrl(`/admin/inventory/adjustments/${a.id}`)} className="text-sm font-mono font-medium text-blue-600 hover:text-blue-800">
                                                        {a.adjustment_number || `#${a.id}`}
                                                    </Link>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    {new Date(a.created_at).toLocaleString()}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {a.product ? (
                                                        <Link href={adminUrl(`/admin/inventory/product/${a.product.id}`)} className="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-blue-600">
                                                            {a.product.name}
                                                        </Link>
                                                    ) : (
                                                        <span className="text-sm text-gray-400 dark:text-gray-500">Deleted Product</span>
                                                    )}
                                                    {a.product?.sku && <div className="text-xs text-gray-400 dark:text-gray-500">{a.product.sku}</div>}
                                                    {a.variant && <div className="text-xs text-gray-400 dark:text-gray-500">Variant: {a.variant.sku}</div>}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    {a.warehouse?.name || '—'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-700 dark:text-gray-300">
                                                    {a.before_stock ?? '—'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right">
                                                    <span className={`inline-flex items-center gap-1 text-sm font-semibold font-mono ${a.quantity > 0 ? 'text-green-600' : 'text-red-600'}`}>
                                                        {a.quantity > 0 ? <ArrowUpRight className="w-3.5 h-3.5" /> : <ArrowDownRight className="w-3.5 h-3.5" />}
                                                        {a.quantity > 0 ? '+' : ''}{a.quantity}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {a.after_stock ?? '—'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${badgeClass}`}>
                                                        {a.reason_label}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Link
                                                            href={adminUrl(`/admin/inventory/adjustments/${a.id}`)}
                                                            className="p-1.5 text-gray-400 dark:text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                                                            title="View"
                                                        >
                                                            <Eye className="w-4 h-4" />
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            onClick={() => setDeleteTarget(a)}
                                                            className="p-1.5 text-gray-400 dark:text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors"
                                                            title="Delete"
                                                        >
                                                            <Trash2 className="w-4 h-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {adjustments?.meta && <Pagination meta={adjustments.meta} />}
                    </div>
                </div>
            </div>

            {/* Delete Confirmation Modal */}
            {deleteTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Delete Adjustment</h3>
                            <button onClick={() => setDeleteTarget(null)} className="text-gray-400 hover:text-gray-600 dark:text-gray-400">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Are you sure you want to delete this stock adjustment? This will remove the movement record and recalculate current stock.
                        </p>

                        <div className="bg-gray-50 dark:bg-gray-950 rounded-lg p-4 mb-6 space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-gray-500 dark:text-gray-400">Adjustment</span>
                                <span className="font-mono font-medium text-gray-900 dark:text-gray-100">{deleteTarget.adjustment_number || `#${deleteTarget.id}`}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-500 dark:text-gray-400">Product</span>
                                <span className="font-medium text-gray-900 dark:text-gray-100">{deleteTarget.product?.name || 'Deleted Product'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-500 dark:text-gray-400">Change</span>
                                <span className={`font-medium ${deleteTarget.quantity > 0 ? 'text-green-600' : 'text-red-600'}`}>
                                    {deleteTarget.quantity > 0 ? '+' : ''}{deleteTarget.quantity}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-500 dark:text-gray-400">Reason</span>
                                <span className="font-medium text-gray-900 dark:text-gray-100">{deleteTarget.reason_label}</span>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setDeleteTarget(null)}
                                className="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={handleDelete}
                                disabled={deleting}
                                className="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50"
                            >
                                {deleting ? 'Deleting...' : 'Delete'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
