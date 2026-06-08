import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import ComboViewDetail from '@/Components/ProductView/ComboViewDetail';
import {
    Eye,
    Edit3,
    ArrowLeft,
    Package,
    Layers,
    Gift,
    DollarSign,
    BarChart3,
    Tag,
    Globe,
    Calendar,
    TrendingDown,
    AlertCircle,
} from 'lucide-react';

const TYPE_ICONS = {
    single: Package,
    variable: Layers,
    combo: Gift,
};

const TYPE_COLORS = {
    single: 'bg-blue-50 text-blue-700 ring-blue-600/10',
    variable: 'bg-purple-50 text-purple-700 ring-purple-600/10',
    combo: 'bg-orange-50 text-orange-700 ring-orange-600/10',
};

const TYPE_LABELS = {
    single: 'Single Product',
    variable: 'Variable Product',
    combo: 'Combo Product',
};

function formatPrice(price) {
    return Number(price).toLocaleString() + ' MMK';
}

function getVariantStockStatus(stock) {
    if (stock <= 0) return { label: 'Out of Stock', bg: 'bg-red-50', text: 'text-red-700', ring: 'ring-red-600/10', dot: 'bg-red-500' };
    if (stock <= 5) return { label: 'Low Stock', bg: 'bg-amber-50', text: 'text-amber-700', ring: 'ring-amber-600/10', dot: 'bg-amber-500' };
    return { label: 'In Stock', bg: 'bg-emerald-50', text: 'text-emerald-700', ring: 'ring-emerald-600/10', dot: 'bg-emerald-500' };
}

function getProductStockStatus(product) {
    const total = product.effective_stock ?? product.stock ?? 0;
    if (total <= 0) return { label: 'Out of Stock', color: 'red', bg: 'bg-red-50', text: 'text-red-700', ring: 'ring-red-600/10', dot: 'bg-red-500' };
    if (total <= 10) return { label: 'Low Stock', color: 'amber', bg: 'bg-amber-50', text: 'text-amber-700', ring: 'ring-amber-600/10', dot: 'bg-amber-500' };
    return { label: 'In Stock', color: 'green', bg: 'bg-emerald-50', text: 'text-emerald-700', ring: 'ring-emerald-600/10', dot: 'bg-emerald-500' };
}

function VariantLabel({ attrs }) {
    const values = attrs ? Object.values(attrs) : [];
    if (values.length === 0) return null;
    return (
        <div className="flex flex-wrap gap-1">
            {values.map((val, i) => (
                <span key={i} className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                    {val}
                </span>
            ))}
        </div>
    );
}

export default function ProductShow({ product, relatedCombos = [] }) {
    const TypeIcon = TYPE_ICONS[product.type] || Package;
    const typeConfig = TYPE_COLORS[product.type] || TYPE_COLORS.single;
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);

    const isVariable = product.type === 'variable';
    const isCombo = product.type === 'combo';
    const isSingle = product.type === 'single';

    const totalStock = product.effective_stock ?? product.stock ?? 0;
    const stockStatus = getProductStockStatus(product);

    const variants = product.variants || [];
    const comboItems = product.combo_items || [];
    const safeRelatedCombos = relatedCombos || [];

    let priceRange = null;
    if (isVariable && variants.length > 0) {
        const prices = variants.map(v => v.price || product.price || 0).filter(p => p > 0);
        if (prices.length > 0) {
            priceRange = {
                min: Math.min(...prices),
                max: Math.max(...prices),
            };
        }
    }

    return (
        <AdminLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Link
                            href={adminUrl('/admin/products')}
                            className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
                        >
                            <ArrowLeft className="w-5 h-5 text-gray-500" />
                        </Link>
                        <div>
                            <div className="flex items-center gap-3">
                                <h2 className="text-xl font-semibold text-gray-800 truncate max-w-md">{product.name}</h2>
                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1 ${typeConfig}`}>
                                    {TYPE_LABELS[product.type] || 'Single'}
                                </span>
                            </div>
                            <p className="text-sm text-gray-500 mt-0.5">Product details and overview</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link
                            href={adminUrl(`/admin/products/${product.id}/edit`)}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors shadow-sm"
                        >
                            <Edit3 className="w-4 h-4" />
                            Edit
                        </Link>
                        <button
                            onClick={() => setDeleteModalOpen(true)}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-white border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            }
        >
            <Head title={product.name} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div className="flex flex-col lg:flex-row gap-6">
                    {/* Main Content */}
                    <div className="flex-1 min-w-0 space-y-6">
                        {/* Images */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div className="px-5 py-4 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900">Images</h3>
                            </div>
                            <div className="px-5 py-5">
                                {(product.photo1_url || product.photo2_url) ? (
                                    <div className="grid grid-cols-2 gap-4">
                                        {product.photo1_url && (
                                            <div>
                                                <p className="text-xs text-gray-500 mb-2 font-medium">Featured Image</p>
                                                <img
                                                    src={product.photo1_url}
                                                    alt={product.name}
                                                    className="w-full h-48 object-cover rounded-lg border border-gray-200"
                                                />
                                            </div>
                                        )}
                                        {product.photo2_url ? (
                                            <div>
                                                <p className="text-xs text-gray-500 mb-2 font-medium">Gallery Image</p>
                                                <img
                                                    src={product.photo2_url}
                                                    alt={product.name}
                                                    className="w-full h-48 object-cover rounded-lg border border-gray-200"
                                                />
                                            </div>
                                        ) : (
                                            <div className="flex flex-col items-center justify-center h-48 rounded-lg border-2 border-dashed border-gray-200 bg-gray-50">
                                                <Eye className="w-8 h-8 text-gray-300 mb-2" />
                                                <p className="text-sm text-gray-400">No gallery image</p>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-2 gap-4">
                                        {[0, 1].map(i => (
                                            <div key={i} className="flex flex-col items-center justify-center h-48 rounded-lg border-2 border-dashed border-gray-200 bg-gray-50">
                                                <Eye className="w-8 h-8 text-gray-300 mb-2" />
                                                <p className="text-sm text-gray-400">{i === 0 ? 'No featured image' : 'No gallery image'}</p>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Description */}
                        {product.description && (
                            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div className="px-5 py-4 border-b border-gray-100">
                                    <h3 className="text-sm font-semibold text-gray-900">Description</h3>
                                </div>
                                <div className="px-5 py-5">
                                    <p className="text-sm text-gray-700 whitespace-pre-wrap">{product.description}</p>
                                </div>
                            </div>
                        )}

                        {/* ── Variable Product: Pricing Summary + Variant Table ── */}
                        {isVariable && (
                            <>
                                {/* Pricing Summary */}
                                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                    <div className="px-5 py-4 border-b border-gray-100">
                                        <div className="flex items-center gap-2">
                                            <DollarSign className="w-4 h-4 text-gray-400" />
                                            <h3 className="text-sm font-semibold text-gray-900">Pricing</h3>
                                        </div>
                                    </div>
                                    <div className="px-5 py-5">
                                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                            <div>
                                                <p className="text-xs text-gray-500 mb-1">Base Price</p>
                                                <p className="text-lg font-semibold text-gray-900">{formatPrice(product.price)}</p>
                                            </div>
                                            {priceRange && priceRange.min !== priceRange.max && (
                                                <>
                                                    <div>
                                                        <p className="text-xs text-gray-500 mb-1">Price Range</p>
                                                        <p className="text-sm font-semibold text-gray-900">
                                                            {formatPrice(priceRange.min)} — {formatPrice(priceRange.max)}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <p className="text-xs text-gray-500 mb-1">Spread</p>
                                                        <div className="flex items-center gap-1">
                                                            <TrendingDown className="w-3.5 h-3.5 text-gray-400" />
                                                            <p className="text-sm font-medium text-gray-700">{formatPrice(priceRange.max - priceRange.min)}</p>
                                                        </div>
                                                    </div>
                                                </>
                                            )}
                                            {product.base_price && product.base_price > product.price && (
                                                <div>
                                                    <p className="text-xs text-gray-500 mb-1">Compare at Price</p>
                                                    <p className="text-lg font-medium text-gray-500 line-through">{formatPrice(product.base_price)}</p>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                {/* Variant Summary Cards */}
                                {variants.length > 0 && (
                                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                        <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Layers className="w-4 h-4 text-purple-500" />
                                                <h3 className="text-sm font-semibold text-gray-900">Variants</h3>
                                            </div>
                                            <span className="text-xs text-gray-500">{variants.length} variant{variants.length !== 1 ? 's' : ''}</span>
                                        </div>
                                        <div className="px-5 py-5">
                                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-5">
                                                <div className="p-3 rounded-lg bg-gray-50 border border-gray-100">
                                                    <p className="text-xs text-gray-500 mb-1">Total Variants</p>
                                                    <p className="text-2xl font-bold text-gray-900">{variants.length}</p>
                                                </div>
                                                <div className="p-3 rounded-lg bg-gray-50 border border-gray-100">
                                                    <p className="text-xs text-gray-500 mb-1">Total Units</p>
                                                    <p className="text-2xl font-bold text-gray-900">{totalStock}</p>
                                                </div>
                                                <div className="p-3 rounded-lg bg-gray-50 border border-gray-100 col-span-2 sm:col-span-1">
                                                    <p className="text-xs text-gray-500 mb-1">Stock Status</p>
                                                    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ring-1 ${stockStatus.bg} ${stockStatus.text} ${stockStatus.ring}`}>
                                                        <span className={`w-1.5 h-1.5 rounded-full ${stockStatus.dot}`} />
                                                        {stockStatus.label}
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Variant Table */}
                                            <div className="overflow-x-auto -mx-5 sm:mx-0">
                                                <table className="min-w-full divide-y divide-gray-200">
                                                    <thead className="bg-gray-50">
                                                        <tr>
                                                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variant</th>
                                                            <th className="hidden sm:table-cell px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                                                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                                            <th className="hidden sm:table-cell px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-gray-100 bg-white">
                                                        {variants.map((variant) => {
                                                            const vStockStatus = getVariantStockStatus(variant.stock ?? 0);
                                                            const attrs = variant.attributes || {};
                                                            return (
                                                                <tr key={variant.id} className="hover:bg-gray-50/50 transition-colors">
                                                                    <td className="px-4 py-3">
                                                                        <div className="space-y-1">
                                                                            <VariantLabel attrs={attrs} />
                                                                        </div>
                                                                    </td>
                                                                    <td className="hidden sm:table-cell px-4 py-3">
                                                                        <span className="text-sm font-mono text-gray-500">
                                                                            {variant.sku || '—'}
                                                                        </span>
                                                                    </td>
                                                                    <td className="px-4 py-3">
                                                                        <span className="text-sm font-medium text-gray-900">
                                                                            {variant.price ? formatPrice(variant.price) : (
                                                                                <span className="text-gray-400 italic">Uses base</span>
                                                                            )}
                                                                        </span>
                                                                    </td>
                                                                    <td className="px-4 py-3">
                                                                        <span className="text-sm font-semibold text-gray-900">{variant.stock ?? 0}</span>
                                                                    </td>
                                                                    <td className="hidden sm:table-cell px-4 py-3">
                                                                        <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1 ${vStockStatus.bg} ${vStockStatus.text} ${vStockStatus.ring}`}>
                                                                            <span className={`w-1.5 h-1.5 rounded-full ${vStockStatus.dot}`} />
                                                                            {vStockStatus.label}
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            );
                                                        })}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Empty variant state */}
                                {variants.length === 0 && (
                                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                        <div className="px-5 py-12 text-center">
                                            <div className="w-16 h-16 rounded-2xl bg-purple-50 flex items-center justify-center mx-auto mb-4">
                                                <Layers className="w-8 h-8 text-purple-300" />
                                            </div>
                                            <p className="text-sm font-medium text-gray-900">No variants defined</p>
                                            <p className="text-sm text-gray-500 mt-1">This variable product has no variants yet.</p>
                                            <Link
                                                href={adminUrl(`/admin/products/${product.id}/edit`)}
                                                className="mt-4 inline-flex items-center gap-1.5 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm font-medium"
                                            >
                                                <Edit3 className="w-4 h-4" />
                                                Add Variants
                                            </Link>
                                        </div>
                                    </div>
                                )}
                            </>
                        )}

                        {/* ── Single Product: Pricing + Inventory ── */}
                        {isSingle && (
                            <>
                                {/* Pricing */}
                                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                    <div className="px-5 py-4 border-b border-gray-100">
                                        <div className="flex items-center gap-2">
                                            <DollarSign className="w-4 h-4 text-gray-400" />
                                            <h3 className="text-sm font-semibold text-gray-900">Pricing</h3>
                                        </div>
                                    </div>
                                    <div className="px-5 py-5">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <p className="text-xs text-gray-500 mb-1">Selling Price</p>
                                                <p className="text-lg font-semibold text-gray-900">{formatPrice(product.price)}</p>
                                            </div>
                                            {product.base_price && product.base_price > product.price && (
                                                <div>
                                                    <p className="text-xs text-gray-500 mb-1">Compare at Price</p>
                                                    <p className="text-lg font-medium text-gray-500 line-through">{formatPrice(product.base_price)}</p>
                                                </div>
                                            )}
                                        </div>
                                        {product.base_price && product.base_price > product.price && (
                                            <div className="mt-3 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                Save {formatPrice(product.base_price - product.price)}
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* Inventory */}
                                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                    <div className="px-5 py-4 border-b border-gray-100">
                                        <div className="flex items-center gap-2">
                                            <BarChart3 className="w-4 h-4 text-gray-400" />
                                            <h3 className="text-sm font-semibold text-gray-900">Inventory</h3>
                                        </div>
                                    </div>
                                    <div className="px-5 py-5">
                                        <div className="grid grid-cols-3 gap-4">
                                            <div>
                                                <p className="text-xs text-gray-500 mb-1">Stock</p>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-lg font-semibold text-gray-900">{product.stock}</span>
                                                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                                                        product.stock === 0 ? 'bg-red-100 text-red-700' :
                                                        product.stock < 10 ? 'bg-amber-100 text-amber-700' :
                                                        'bg-green-100 text-green-700'
                                                    }`}>
                                                        {product.stock === 0 ? 'Out of Stock' : product.stock < 10 ? 'Low' : 'In Stock'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </>
                        )}
                        {/* ── Combo: Full Bundle View ── */}
                        {isCombo && comboItems.length > 0 && <ComboViewDetail product={product} />}

                        {isCombo && comboItems.length === 0 && (
                            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div className="px-5 py-12 text-center">
                                    <div className="w-16 h-16 rounded-2xl bg-orange-50 flex items-center justify-center mx-auto mb-4">
                                        <Gift className="w-8 h-8 text-orange-300" />
                                    </div>
                                    <p className="text-sm font-medium text-gray-900">No components added</p>
                                    <p className="text-sm text-gray-500 mt-1">This combo product has no items yet.</p>
                                    <Link
                                        href={adminUrl(`/admin/products/${product.id}/edit`)}
                                        className="mt-4 inline-flex items-center gap-1.5 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors text-sm font-medium"
                                    >
                                        <Edit3 className="w-4 h-4" />
                                        Add Components
                                    </Link>
                                </div>
                            </div>
                        )}

                        {/* ── Variant Inventory Summary (for variable products) ── */}
                        {isVariable && variants.length > 0 && (
                            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div className="px-5 py-4 border-b border-gray-100">
                                    <div className="flex items-center gap-2">
                                        <BarChart3 className="w-4 h-4 text-gray-400" />
                                        <h3 className="text-sm font-semibold text-gray-900">Inventory Summary</h3>
                                    </div>
                                </div>
                                <div className="px-5 py-5">
                                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                        <div className="p-3 rounded-lg bg-gray-50 border border-gray-100">
                                            <div className="flex items-center gap-2 mb-1">
                                                <Layers className="w-3.5 h-3.5 text-purple-500" />
                                                <p className="text-xs text-gray-500">Variants</p>
                                            </div>
                                            <p className="text-xl font-bold text-gray-900">{variants.length}</p>
                                        </div>
                                        <div className="p-3 rounded-lg bg-gray-50 border border-gray-100">
                                            <div className="flex items-center gap-2 mb-1">
                                                <BarChart3 className="w-3.5 h-3.5 text-blue-500" />
                                                <p className="text-xs text-gray-500">Total Units</p>
                                            </div>
                                            <p className="text-xl font-bold text-gray-900">{totalStock}</p>
                                        </div>
                                        <div className="p-3 rounded-lg bg-gray-50 border border-gray-100">
                                            <div className="flex items-center gap-2 mb-1">
                                                <p className="text-xs text-gray-500">Status</p>
                                            </div>
                                            <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ring-1 ${stockStatus.bg} ${stockStatus.text} ${stockStatus.ring}`}>
                                                <span className={`w-1.5 h-1.5 rounded-full ${stockStatus.dot}`} />
                                                {stockStatus.label}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* ── Related Combos ── */}
                        {safeRelatedCombos.length > 0 && (
                            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div className="px-5 py-4 border-b border-gray-100">
                                    <h3 className="text-sm font-semibold text-gray-900">Included in Combos ({safeRelatedCombos.length})</h3>
                                </div>
                                <div className="px-5 py-5">
                                    <div className="space-y-2">
                                        {safeRelatedCombos.map((combo) => (
                                            <Link
                                                key={combo.id}
                                                href={adminUrl(`/admin/products/${combo.id}`)}
                                                className="flex items-center justify-between p-3 rounded-lg border border-gray-100 hover:bg-gray-50 transition-colors"
                                            >
                                                <div className="flex items-center gap-3">
                                                    {combo.photo1_url ? (
                                                        <img src={combo.photo1_url} alt={combo.name} className="w-10 h-10 rounded-lg object-cover" />
                                                    ) : (
                                                        <div className="w-10 h-10 rounded-lg bg-gray-200 flex items-center justify-center">
                                                            <Gift className="w-5 h-5 text-gray-400" />
                                                        </div>
                                                    )}
                                                    <div>
                                                        <p className="text-sm font-medium text-gray-900">{combo.name}</p>
                                                        <p className="text-xs text-gray-500">{formatPrice(combo.price)}</p>
                                                    </div>
                                                </div>
                                                <span className="text-xs text-blue-600">View →</span>
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="w-full lg:w-80 flex-shrink-0 space-y-4">
                        {/* Status */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div className="px-4 py-3 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900">Status</h3>
                            </div>
                            <div className="px-4 py-4">
                                <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${
                                    product.status === 'active'
                                        ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/10'
                                        : product.status === 'draft'
                                            ? 'bg-gray-100 text-gray-600 ring-1 ring-gray-600/10'
                                            : 'bg-gray-100 text-gray-600 ring-1 ring-gray-600/10'
                                }`}>
                                    <span className={`w-1.5 h-1.5 rounded-full ${product.status === 'active' ? 'bg-emerald-500' : 'bg-gray-400'}`} />
                                    {product.status === 'active' ? 'Active' : product.status === 'draft' ? 'Draft' : 'Inactive'}
                                </span>
                            </div>
                        </div>

                        {/* Type */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div className="px-4 py-3 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900">Product Type</h3>
                            </div>
                            <div className="px-4 py-4">
                                <div className="flex items-center gap-2.5">
                                    <div className={`w-8 h-8 rounded-lg flex items-center justify-center ${typeConfig}`}>
                                        <TypeIcon className="w-4 h-4" />
                                    </div>
                                    <span className="text-sm text-gray-700">{TYPE_LABELS[product.type] || 'Single'}</span>
                                </div>
                            </div>
                        </div>

                        {/* Inventory Quick View */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div className="px-4 py-3 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900">Inventory</h3>
                            </div>
                            <div className="px-4 py-4">
                                {isVariable ? (
                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-gray-600">Total Units</span>
                                            <span className="text-lg font-bold text-gray-900">{totalStock}</span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-gray-600">Variants</span>
                                            <span className="text-sm font-medium text-gray-900">{variants.length}</span>
                                        </div>
                                        <hr className="border-gray-100" />
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-gray-600">Status</span>
                                            <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1 ${stockStatus.bg} ${stockStatus.text} ${stockStatus.ring}`}>
                                                <span className={`w-1.5 h-1.5 rounded-full ${stockStatus.dot}`} />
                                                {stockStatus.label}
                                            </span>
                                        </div>
                                    </div>
                                ) : isCombo ? (
                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-gray-600">Bundle Stock</span>
                                            <span className={`text-lg font-bold ${product.combo_availability?.available_stock <= 0 ? 'text-red-600' : product.combo_availability?.available_stock <= 5 ? 'text-amber-600' : 'text-emerald-600'}`}>
                                                {product.combo_availability?.available_stock ?? 0}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-gray-600">Components</span>
                                            <span className="text-sm font-medium text-gray-900">{product.combo_summary?.item_count ?? comboItems.length}</span>
                                        </div>
                                        {product.combo_availability?.bottleneck && (
                                            <>
                                                <hr className="border-gray-100" />
                                                <div>
                                                    <p className="text-xs text-gray-500 mb-1">Limited by</p>
                                                    <p className="text-xs font-medium text-gray-700 truncate">
                                                        {product.combo_availability.bottleneck.product_name}
                                                        {product.combo_availability.bottleneck.variant_label ? ` (${product.combo_availability.bottleneck.variant_label})` : ''}
                                                    </p>
                                                </div>
                                            </>
                                        )}
                                        <hr className="border-gray-100" />
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-gray-600">Status</span>
                                            <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1 ${stockStatus.bg} ${stockStatus.text} ${stockStatus.ring}`}>
                                                <span className={`w-1.5 h-1.5 rounded-full ${stockStatus.dot}`} />
                                                {stockStatus.label}
                                            </span>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-gray-600">Stock</span>
                                            <span className="text-lg font-bold text-gray-900">{product.stock}</span>
                                        </div>
                                        <hr className="border-gray-100" />
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-gray-600">Status</span>
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                                                product.stock === 0 ? 'bg-red-100 text-red-700' :
                                                product.stock < 10 ? 'bg-amber-100 text-amber-700' :
                                                'bg-green-100 text-green-700'
                                            }`}>
                                                {product.stock === 0 ? 'Out of Stock' : product.stock < 10 ? 'Low' : 'In Stock'}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Organization */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div className="px-4 py-3 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900">Organization</h3>
                            </div>
                            <div className="px-4 py-4 space-y-3">
                                {product.category && (
                                    <div>
                                        <p className="text-xs text-gray-500 mb-1">Category</p>
                                        <div className="flex items-center gap-2">
                                            <Tag className="w-4 h-4 text-gray-400" />
                                            <span className="text-sm text-gray-700">{product.category.name}</span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* SEO */}
                        {(product.meta_title || product.meta_description) && (
                            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div className="px-4 py-3 border-b border-gray-100">
                                    <div className="flex items-center gap-2">
                                        <Globe className="w-4 h-4 text-gray-400" />
                                        <h3 className="text-sm font-semibold text-gray-900">SEO</h3>
                                    </div>
                                </div>
                                <div className="px-4 py-4 space-y-3">
                                    {product.meta_title && (
                                        <div>
                                            <p className="text-xs text-gray-500 mb-1">Meta Title</p>
                                            <p className="text-sm text-gray-700">{product.meta_title}</p>
                                        </div>
                                    )}
                                    {product.meta_description && (
                                        <div>
                                            <p className="text-xs text-gray-500 mb-1">Meta Description</p>
                                            <p className="text-sm text-gray-700">{product.meta_description}</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Meta Info */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div className="px-4 py-3 border-b border-gray-100">
                                <div className="flex items-center gap-2">
                                    <Calendar className="w-4 h-4 text-gray-400" />
                                    <h3 className="text-sm font-semibold text-gray-900">Details</h3>
                                </div>
                            </div>
                            <div className="px-4 py-4 space-y-3">
                                <div>
                                    <p className="text-xs text-gray-500 mb-1">Created</p>
                                    <p className="text-sm text-gray-700">
                                        {new Date(product.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 mb-1">Last Updated</p>
                                    <p className="text-sm text-gray-700">
                                        {new Date(product.updated_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 mb-1">Product ID</p>
                                    <p className="text-sm font-mono text-gray-700">#{product.id}</p>
                                </div>
                            </div>
                        </div>

                        {/* Quick Actions */}
                        <div className="sticky bottom-4">
                            <div className="bg-white rounded-xl shadow-lg border border-gray-200 p-4 space-y-3">
                                <Link
                                    href={adminUrl(`/admin/products/${product.id}/edit`)}
                                    className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                                >
                                    <Edit3 className="w-4 h-4" />
                                    Edit Product
                                </Link>
                                <Link
                                    href={adminUrl('/admin/products')}
                                    className="w-full px-4 py-2.5 text-gray-700 bg-white border border-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors text-center block"
                                >
                                    Back to Products
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Delete Confirmation Modal */}
            {deleteModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
                    <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                                <AlertCircle className="w-5 h-5 text-red-600" />
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900">Delete Product</h3>
                                <p className="text-sm text-gray-500">This action cannot be undone</p>
                            </div>
                        </div>
                        <div className="mb-6 p-3 rounded-lg bg-gray-50 border border-gray-200">
                            <p className="text-sm text-gray-700">
                                Are you sure you want to delete <strong>{product.name}</strong>?
                            </p>
                            {product.has_orders ? (
                                <div className="mt-3 p-2.5 rounded-lg bg-red-50 border border-red-200">
                                    <p className="text-xs text-red-700 font-medium">Cannot delete this product</p>
                                    <p className="text-xs text-red-600 mt-0.5">This product exists in customer orders and cannot be deleted. Deactivate it instead.</p>
                                </div>
                            ) : (
                                <p className="text-xs text-gray-500 mt-2">
                                    This will permanently remove the product, its images, variants, and combo relationships.
                                </p>
                            )}
                        </div>
                        <div className="flex gap-3 justify-end">
                            <button
                                onClick={() => setDeleteModalOpen(false)}
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Cancel
                            </button>
                            {!product.has_orders && (
                                <button
                                    onClick={() => router.delete(adminUrl(`/admin/products/${product.id}`), {
                                        onSuccess: () => setDeleteModalOpen(false),
                                    })}
                                    className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                                >
                                    Delete Product
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
