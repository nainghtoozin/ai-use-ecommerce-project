import { useState, useRef, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { ArrowLeft, ArrowUpRight, ArrowDownRight, Search, Package, ShoppingCart, RotateCcw, Settings, Truck, CheckCircle, AlertTriangle, XCircle, Building2, Clock } from 'lucide-react';
import { adminUrl } from '@/Utils/adminUrl';

const typeConfig = {
    opening_stock: { label: 'Opening Stock', icon: Package, class: 'bg-blue-50 text-blue-700 border-blue-200' },
    purchase: { label: 'Purchase', icon: ShoppingCart, class: 'bg-green-50 text-green-700 border-green-200' },
    sale: { label: 'Sale', icon: ArrowUpRight, class: 'bg-red-50 text-red-700 border-red-200' },
    return: { label: 'Return', icon: RotateCcw, class: 'bg-purple-50 text-purple-700 border-purple-200' },
    adjustment: { label: 'Adjustment', icon: Settings, class: 'bg-amber-50 text-amber-700 border-amber-200' },
    transfer: { label: 'Transfer', icon: Truck, class: 'bg-gray-50 text-gray-700 border-gray-200' },
};

const stockBadge = (status) => {
    if (status === 'out_of_stock') return { label: 'Out of Stock', class: 'bg-red-50 text-red-700 border-red-200', icon: XCircle };
    if (status === 'low_stock') return { label: 'Low Stock', class: 'bg-amber-50 text-amber-700 border-amber-200', icon: AlertTriangle };
    return { label: 'In Stock', class: 'bg-green-50 text-green-700 border-green-200', icon: CheckCircle };
};

export default function ProductStockHistory({ product = null, movements = [] }) {
    const [productSearch, setProductSearch] = useState('');
    const [showDropdown, setShowDropdown] = useState(false);
    const [searchResults, setSearchResults] = useState([]);
    const [searching, setSearching] = useState(false);
    const searchRef = useRef(null);

    useEffect(() => {
        const handleClickOutside = (e) => {
            if (searchRef.current && !searchRef.current.contains(e.target)) {
                setShowDropdown(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (productSearch.length < 2) {
            setSearchResults([]);
            return;
        }

        const timer = setTimeout(() => {
            setSearching(true);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            fetch(adminUrl(`/admin/inventory/stock-history?search=${encodeURIComponent(productSearch)}`), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
            })
                .then(res => res.json())
                .then(data => {
                    setSearchResults(data.products || []);
                    setShowDropdown(true);
                })
                .catch(() => setSearchResults([]))
                .finally(() => setSearching(false));
        }, 300);

        return () => clearTimeout(timer);
    }, [productSearch]);

    const handleProductSelect = (productId) => {
        setShowDropdown(false);
        setProductSearch('');
        router.get(adminUrl('/admin/inventory/stock-history'), { product_id: productId }, { preserveState: true, preserveScroll: true });
    };

    const badge = product ? stockBadge(product.stock_status) : null;

    return (
        <AdminLayout>
            <Head title="Stock History" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-3 mb-6">
                        <Link href={adminUrl('/admin/inventory/dashboard')} className="text-gray-400 hover:text-gray-600 dark:text-gray-400">
                            <ArrowLeft className="w-5 h-5" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Stock History</h1>
                            <p className="text-sm text-gray-500 dark:text-gray-400">Product-specific inventory history and timeline.</p>
                        </div>
                    </div>

                    {/* Product Search */}
                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 mb-6">
                        <div className="relative" ref={searchRef}>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Search Product</label>
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" />
                                <input
                                    type="text"
                                    value={productSearch}
                                    onChange={(e) => {
                                        setProductSearch(e.target.value);
                                        if (e.target.value.length >= 2) setShowDropdown(true);
                                    }}
                                    onFocus={() => { if (searchResults.length > 0) setShowDropdown(true); }}
                                    placeholder="Search by product name, SKU, or barcode..."
                                    className="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-700 rounded-lg text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                />
                                {searching && (
                                    <div className="absolute right-3 top-1/2 -translate-y-1/2">
                                        <div className="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin" />
                                    </div>
                                )}
                            </div>

                            {showDropdown && searchResults.length > 0 && (
                                <div className="absolute z-20 mt-1 w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                    {searchResults.map((p) => (
                                        <button
                                            key={p.id}
                                            type="button"
                                            onClick={() => handleProductSelect(p.id)}
                                            className="w-full px-4 py-2.5 text-left hover:bg-gray-50 dark:bg-gray-950 flex items-center justify-between border-b border-gray-100 dark:border-gray-800 last:border-0"
                                        >
                                            <div>
                                                <div className="text-sm font-medium text-gray-900 dark:text-gray-100">{p.name}</div>
                                                <div className="text-xs text-gray-400 dark:text-gray-500 font-mono">{p.sku || 'No SKU'}</div>
                                            </div>
                                            <span className="text-xs text-gray-400 dark:text-gray-500 capitalize">{p.type}</span>
                                        </button>
                                    ))}
                                </div>
                            )}

                            {showDropdown && productSearch.length >= 2 && searchResults.length === 0 && !searching && (
                                <div className="absolute z-20 mt-1 w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg shadow-lg p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No products found
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Empty State — no product selected */}
                    {!product && (
                        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-16 text-center">
                            <Clock className="w-16 h-16 mx-auto mb-4 text-gray-300" />
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Search a product to view its stock history</h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                                Select a product above to see its complete inventory timeline, including opening stock, sales, adjustments, and transfers.
                            </p>
                        </div>
                    )}

                    {/* Product selected — show header + timeline */}
                    {product && (
                        <>
                            {/* Product Header */}
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 mb-6">
                                <div className="flex items-center justify-between flex-wrap gap-4">
                                    <div className="flex items-center gap-4">
                                        <div className="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center">
                                            <Package className="w-6 h-6 text-blue-600" />
                                        </div>
                                        <div>
                                            <Link href={adminUrl(`/admin/inventory/product/${product.id}`)} className="text-lg font-semibold text-gray-900 dark:text-gray-100 hover:text-blue-600">
                                                {product.name}
                                            </Link>
                                            <div className="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                                {product.sku && <span className="font-mono">{product.sku}</span>}
                                                <span className="capitalize">{product.type}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-6">
                                        {product.warehouse && (
                                            <div className="text-right">
                                                <div className="text-xs text-gray-500 dark:text-gray-400">Location</div>
                                                <div className="text-sm font-medium text-gray-900 dark:text-gray-100 flex items-center gap-1">
                                                    <Building2 className="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" />
                                                    {product.warehouse.name}
                                                </div>
                                            </div>
                                        )}

                                        <div className="text-right">
                                            <div className="text-xs text-gray-500 dark:text-gray-400">Current Stock</div>
                                            <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">{product.stock}</div>
                                        </div>

                                        {badge && (
                                            <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium border ${badge.class}`}>
                                                <badge.icon className="w-4 h-4" />
                                                {badge.label}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Timeline */}
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                                <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                                    <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Inventory Timeline</h2>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{movements.length} record{movements.length !== 1 ? 's' : ''} — newest first</p>
                                </div>

                                {movements.length === 0 ? (
                                    <div className="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                        <Clock className="w-12 h-12 mx-auto mb-3 text-gray-300" />
                                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100 mb-1">No stock movements yet.</p>
                                        <p className="text-xs text-gray-400 dark:text-gray-500">Movements will appear here when inventory changes occur.</p>
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                            <thead className="bg-gray-50 dark:bg-gray-950">
                                                <tr>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Quantity</th>
                                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance</th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Location</th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reference</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                                                {movements.map((m) => {
                                                    const config = typeConfig[m.type] ?? { label: m.type, icon: Package, class: 'bg-gray-50 text-gray-700 border-gray-200' };
                                                    const TypeIcon = config.icon;
                                                    return (
                                                        <tr key={m.id} className="hover:bg-gray-50 dark:bg-gray-950">
                                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                                {new Date(m.created_at).toLocaleString()}
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
                                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900 dark:text-gray-100">
                                                                {m.balance_after}
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                                {m.warehouse?.name || '—'}
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                                {m.reference_type ? (
                                                                    <span className="text-xs font-medium text-gray-400 dark:text-gray-500 capitalize">
                                                                        {m.reference_type.replace('_', ' ')} #{m.reference_id}
                                                                    </span>
                                                                ) : (
                                                                    <span className="text-gray-300">—</span>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
