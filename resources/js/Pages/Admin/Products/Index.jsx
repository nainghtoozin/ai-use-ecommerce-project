import { useState, useEffect, useRef } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import PerPageSelect from '@/Components/PerPageSelect';
import {
    Package,
    Layers,
    Gift,
    Plus,
    Search,
    Filter,
    Trash2,
    DollarSign,
    CheckCircle,
    XCircle,
    AlertCircle,
    Eye,
    Pencil,
    Archive,
} from 'lucide-react';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import { usePermission } from '@/Hooks/usePermission';

function formatStockForProduct(product) {
    if (product.type === 'variable') {
        const total = product.variant_total_stock ?? product.total_variant_stock ?? product.effective_stock ?? 0;
        return {
            type: 'variable',
            total,
            status: total <= 0 ? 'out_of_stock' : total < 10 ? 'low_stock' : 'in_stock',
        };
    }

    if (product.type === 'combo') {
        const total = product.effective_stock ?? product.max_combos ?? 0;
        return {
            type: 'combo',
            total,
            status: total <= 0 ? 'out_of_stock' : total < 10 ? 'low_stock' : 'in_stock',
        };
    }

    const stock = product.stock ?? 0;
    return {
        type: 'single',
        total: stock,
        status: stock <= 0 ? 'out_of_stock' : stock < 10 ? 'low_stock' : 'in_stock',
    };
}

const STOCK_STYLES = {
    in_stock: { bg: 'bg-emerald-50', text: 'text-emerald-700', ring: 'ring-emerald-600/10', dot: 'bg-emerald-500', label: 'In Stock' },
    low_stock: { bg: 'bg-amber-50', text: 'text-amber-700', ring: 'ring-amber-600/10', dot: 'bg-amber-500', label: 'Low Stock' },
    out_of_stock: { bg: 'bg-red-50', text: 'text-red-700', ring: 'ring-red-600/10', dot: 'bg-red-500', label: 'Out of Stock' },
};

function InlineActions({ product, onDelete, can }) {
    return (
        <div className="flex items-center gap-1 whitespace-nowrap">
            <Link
                href={adminUrl(`/admin/products/${product.id}`)}
                className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                title="View"
            >
                <Eye className="w-4 h-4" />
            </Link>
            {can('products.edit') && (
                <Link
                    href={adminUrl(`/admin/products/${product.id}/edit`)}
                    className="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-md transition-colors"
                    title="Edit"
                >
                    <Pencil className="w-4 h-4" />
                </Link>
            )}
            {can('products.delete') && (
                <button
                    type="button"
                    onClick={() => onDelete(product)}
                    className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors"
                    title="Delete"
                >
                    <Trash2 className="w-4 h-4" />
                </button>
            )}
        </div>
    );
}

export default function AdminProductsIndex({ products, categories, brands = [], filters = {}, showPagination = true, warning = null }) {
    const cc = getCurrencyConfig(usePage().props.platform_setting, usePage().props.website_info);
    const { url, props: { auth } } = usePage();
    const { can } = usePermission();

    const [search, setSearch] = useState(filters.search || '');
    const [categoryId, setCategoryId] = useState(filters.category_id || '');
    const [brandId, setBrandId] = useState(filters.brand_id || '');
    const [type, setType] = useState(filters.type || '');
    const [status, setStatus] = useState(filters.status || '');
    const [stock, setStock] = useState(filters.stock || '');
    const [isFiltering, setIsFiltering] = useState(false);
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [selectedIds, setSelectedIds] = useState([]);
    const [selectAll, setSelectAll] = useState(false);
    const [showConfirmModal, setShowConfirmModal] = useState(false);
    const [bulkAction, setBulkAction] = useState('');

    const hasFilters = search || categoryId || brandId || type || status || stock;
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
            router.post(adminUrl('/admin/products/bulk-delete'), formData, {
                onSuccess: () => { setSelectedIds([]); setShowConfirmModal(false); }
            });
        } else if (bulkAction === 'activate') {
            router.post(adminUrl('/admin/products/bulk-activate'), formData, {
                onSuccess: () => { setSelectedIds([]); setShowConfirmModal(false); }
            });
        } else if (bulkAction === 'deactivate') {
            router.post(adminUrl('/admin/products/bulk-deactivate'), formData, {
                onSuccess: () => { setSelectedIds([]); setShowConfirmModal(false); }
            });
        }
    }

    const searchTimeout = useRef(null);
    const isInitialMount = useRef(true);

    function navigateToFilters(newFilters) {
        const query = {};
        if (newFilters.search) query.search = newFilters.search;
        if (newFilters.category_id) query.category_id = newFilters.category_id;
        if (newFilters.brand_id) query.brand_id = newFilters.brand_id;
        if (newFilters.type) query.type = newFilters.type;
        if (newFilters.status) query.status = newFilters.status;
        if (newFilters.stock) query.stock = newFilters.stock;

        setIsFiltering(true);
        router.get(adminUrl('/admin/products'), query, {
            replace: true,
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setIsFiltering(false),
        });
    }

    // Debounced search — fires 400ms after user stops typing
    useEffect(() => {
        if (isInitialMount.current) {
            isInitialMount.current = false;
            return;
        }

        if (searchTimeout.current) {
            clearTimeout(searchTimeout.current);
        }

        searchTimeout.current = setTimeout(() => {
            navigateToFilters({ search, category_id: categoryId, brand_id: brandId, type, status, stock });
        }, 400);

        return () => {
            if (searchTimeout.current) {
                clearTimeout(searchTimeout.current);
            }
        };
    }, [search]);

    // Immediate dropdown filters — no debounce needed
    useEffect(() => {
        if (isInitialMount.current) return;

        navigateToFilters({ search, category_id: categoryId, brand_id: brandId, type, status, stock });
    }, [categoryId, brandId, type, status, stock]);

    function resetFilters() {
        setSearch('');
        setCategoryId('');
        setBrandId('');
        setType('');
        setStatus('');
        setStock('');

        router.get(adminUrl('/admin/products'), {}, {
            replace: true,
            preserveScroll: true,
            preserveState: true,
        });
    }

    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [productToDelete, setProductToDelete] = useState(null);

    function openDeleteModal(product) {
        setProductToDelete(product);
        setDeleteModalOpen(true);
    }

    function confirmDelete() {
        if (productToDelete) {
            router.delete(adminUrl(`/admin/products/${productToDelete.id}`), {
                onSuccess: () => { setDeleteModalOpen(false); setProductToDelete(null); }
            });
        }
    }

    const productCount = products?.total || 0;
    const activeCount = products?.data?.filter(p => p.status === 'active').length || 0;

    return (
        <AdminLayout>
            <Head title="Products" />

            <div className="p-4 lg:p-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-xl lg:text-2xl font-bold text-gray-900">Products</h1>
                        <p className="text-sm text-gray-500 mt-0.5">
                            {productCount} product{productCount !== 1 ? 's' : ''} · {activeCount} active
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link
                            href={adminUrl('/admin/inventory')}
                            className="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium"
                        >
                            <Archive className="w-4 h-4" />
                            Inventory
                        </Link>
                        {can('products.create') && (
                            <Link
                                href={adminUrl('/admin/products/type-select')}
                                className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium shadow-sm"
                            >
                                <Plus className="w-4 h-4" />
                                Add Product
                            </Link>
                        )}
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <button
                        type="button"
                        onClick={() => setFiltersOpen(!filtersOpen)}
                        className="w-full px-4 py-3 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between hover:bg-gray-100/50 transition-colors"
                    >
                        <div className="flex items-center gap-2">
                            <Filter className="w-4 h-4 text-gray-400" />
                            <h3 className="text-sm font-medium text-gray-700">Filters</h3>
                        </div>
                        <svg className={`w-4 h-4 text-gray-400 transition-transform duration-200 ${filtersOpen ? 'rotate-0' : '-rotate-90'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div className={`transition-all duration-200 ease-in-out overflow-hidden ${filtersOpen ? 'max-h-96 opacity-100' : 'max-h-0 opacity-0'}`}>
                        <div className="p-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                            <div className="lg:col-span-2 relative">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search by name or ID..."
                                    className="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                />
                            </div>

                            <select
                                value={categoryId}
                                onChange={(e) => setCategoryId(e.target.value)}
                                className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                            >
                                <option value="">All Categories</option>
                                {categories?.map((cat) => (
                                    <option key={cat.id} value={cat.id}>{cat.name}</option>
                                ))}
                            </select>

                            <select
                                value={brandId}
                                onChange={(e) => setBrandId(e.target.value)}
                                className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                            >
                                <option value="">All Brands</option>
                                {brands?.map((brand) => (
                                    <option key={brand.id} value={brand.id}>{brand.name}</option>
                                ))}
                            </select>

                            <select
                                value={type}
                                onChange={(e) => setType(e.target.value)}
                                className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                            >
                                <option value="">All Types</option>
                                <option value="single">Single</option>
                                <option value="variable">Variable</option>
                                <option value="combo">Combo</option>
                            </select>

                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                            >
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>

                            <select
                                value={stock}
                                onChange={(e) => setStock(e.target.value)}
                                className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                            >
                                <option value="">All Stock</option>
                                <option value="out_of_stock">Out of Stock</option>
                                <option value="low_stock">Low Stock (&lt;10)</option>
                                <option value="in_stock">In Stock (10+)</option>
                            </select>
                        </div>

                        <div className="flex items-center justify-between mt-4 pt-3 border-t border-gray-100">
                            <div className="flex items-center gap-3">
                                {isFiltering && (
                                    <div className="flex items-center gap-2">
                                        <svg className="animate-spin h-3.5 w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        <span className="text-sm text-blue-600">Filtering...</span>
                                    </div>
                                )}
                                {hasFilters && (
                                    <button
                                        onClick={resetFilters}
                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors"
                                    >
                                        Clear all filters
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                    </div>
                </div>

                {/* Bulk Actions Bar */}
                {selectedIds.length > 0 && (
                    <div className="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <span className="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700">
                                {selectedIds.length} product{selectedIds.length !== 1 ? 's' : ''} selected
                            </span>
                            <div className="h-4 w-px bg-blue-200" />
                            {can('products.edit') && (
                                <button
                                    onClick={() => handleBulkAction('activate')}
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-emerald-700 bg-emerald-100 rounded-md hover:bg-emerald-200 transition-colors"
                                >
                                    <CheckCircle className="w-3.5 h-3.5" />
                                    Activate
                                </button>
                            )}
                            {can('products.edit') && (
                                <button
                                    onClick={() => handleBulkAction('deactivate')}
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-amber-700 bg-amber-100 rounded-md hover:bg-amber-200 transition-colors"
                                >
                                    <XCircle className="w-3.5 h-3.5" />
                                    Deactivate
                                </button>
                            )}
                            {can('products.delete') && (
                                <button
                                    onClick={() => handleBulkAction('delete')}
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200 transition-colors"
                                >
                                    <Trash2 className="w-3.5 h-3.5" />
                                    Delete
                                </button>
                            )}
                        </div>
                        <button
                            onClick={() => { setSelectedIds([]); setSelectAll(false); }}
                            className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                        >
                            Clear selection
                        </button>
                    </div>
                )}

                {/* Per Page, Product Count & Warning */}
                <div className="flex flex-wrap justify-between items-center gap-2">
                    <div className="flex items-center gap-4">
                        <PerPageSelect />
                        <span className="text-sm text-gray-500">
                            {productCount} product{productCount !== 1 ? 's' : ''}
                        </span>
                    </div>
                    {warning && <p className="text-sm text-amber-600">{warning}</p>}
                </div>

                {/* Products Table */}
                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50/80">
                                <tr>
                                    <th className="w-10 px-4 py-3">
                                        <input
                                            type="checkbox"
                                            checked={allSelected}
                                            onChange={handleSelectAll}
                                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                    </th>
                                    <th className="w-[80px] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th className="hidden md:table-cell px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                                    <th className="hidden md:table-cell px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th className="hidden md:table-cell px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand</th>
                                    <th className="hidden md:table-cell px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                                    <th className="hidden lg:table-cell px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th className="hidden sm:table-cell px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th className="w-[100px] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky right-0 bg-white z-10">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {!products?.data?.length ? (
                                    <tr>
                                        <td colSpan="11" className="px-4 py-16 text-center">
                                            <div className="flex flex-col items-center">
                                                <div className="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
                                                    <Package className="w-8 h-8 text-gray-300" />
                                                </div>
                                                <p className="text-sm font-medium text-gray-900">
                                                    {hasFilters ? 'No products match your filters' : 'No products yet'}
                                                </p>
                                                <p className="text-sm text-gray-500 mt-1">
                                                    {hasFilters ? 'Try adjusting your search or filter criteria.' : 'Get started by adding your first product.'}
                                                </p>
                                                {!hasFilters && can('products.create') && (
                                                    <Link
                                                        href={adminUrl('/admin/products/type-select')}
                                                        className="mt-4 inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium"
                                                    >
                                                        <Plus className="w-4 h-4" />
                                                        Add Product
                                                    </Link>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    products.data.map((product) => {
                                        const inventory = formatStockForProduct(product);
                                        const stockStyle = STOCK_STYLES[inventory.status] || STOCK_STYLES.in_stock;

                                        return (
                                            <tr key={product.id} className="group hover:bg-gray-50/50 transition-colors">
                                                <td className="px-4 py-3">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedIds.includes(product.id)}
                                                        onChange={() => handleSelectOne(product.id)}
                                                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                    />
                                                </td>

                                                <td className="px-4 py-3">
                                                    <Link href={adminUrl(`/admin/products/${product.id}`)}>
                                                        {product.photo1_url ? (
                                                            <img
                                                                src={product.photo1_url}
                                                                alt={product.name}
                                                                className="w-11 h-11 rounded-lg object-cover border border-gray-200 group-hover:border-gray-300 transition-colors"
                                                            />
                                                        ) : (
                                                            <div className="w-11 h-11 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center group-hover:bg-gray-150 transition-colors">
                                                                <Package className="w-5 h-5 text-gray-300" />
                                                            </div>
                                                        )}
                                                    </Link>
                                                </td>

                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={adminUrl(`/admin/products/${product.id}`)}
                                                        className="text-sm font-medium text-gray-900 hover:text-blue-600 transition-colors truncate block"
                                                    >
                                                        {product.name}
                                                    </Link>
                                                </td>

                                                <td className="hidden md:table-cell px-4 py-3">
                                                    <span className="inline-block max-w-[120px] truncate text-sm font-mono text-gray-500 whitespace-nowrap" title={product.sku_display || ''}>
                                                        {product.sku_display || '—'}
                                                    </span>
                                                </td>

                                                <td className="hidden md:table-cell px-4 py-3">
                                                    <span className="text-sm text-gray-700">{product.category?.name ?? '-'}</span>
                                                </td>

                                                <td className="hidden md:table-cell px-4 py-3">
                                                    <span className="text-sm text-gray-700">{product.brand?.name ?? '-'}</span>
                                                </td>

                                                <td className="hidden md:table-cell px-4 py-3">
                                                    <div className="flex items-center gap-1.5">
                                                        {product.type === 'variable' ? (
                                                            <span className="text-sm font-medium text-gray-900">
                                                                {inventory.total} pcs
                                                            </span>
                                                        ) : product.type === 'combo' ? (
                                                            <span className="text-sm font-medium text-gray-900">
                                                                Bundle
                                                            </span>
                                                        ) : (
                                                            <span className="text-sm font-medium text-gray-900">
                                                                {inventory.total}{product.unit?.short_name ? ` ${product.unit.short_name}` : ''}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>

                                                <td className="hidden lg:table-cell px-4 py-3">
                                                    <div className="flex items-center gap-1.5 whitespace-nowrap">
                                                        {product.type === 'variable' && (
                                                            <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-purple-50 text-purple-700 ring-1 ring-purple-600/10">🎨 Variable</span>
                                                        )}
                                                        {product.type === 'combo' && (
                                                            <span className="inline-flex items-center gap-1.5 text-sm text-gray-600">
                                                                <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-orange-50 text-orange-700">🧩 Bundle</span>
                                                            </span>
                                                        )}
                                                        {product.type === 'single' && (
                                                            <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700">📦 Single</span>
                                                        )}
                                                    </div>
                                                </td>

                                                <td className="hidden sm:table-cell px-4 py-3 whitespace-nowrap">
                                                    <div className="flex items-center gap-1">
                                                        <DollarSign className="w-3.5 h-3.5 text-gray-400 flex-shrink-0" />
                                                        {product.type === 'variable' && product.price_range ? (
                                                            <span className="text-sm font-medium text-gray-900 whitespace-nowrap">
                                                                {formatCurrency(product.price_range[0], cc)} - {formatCurrency(product.price_range[1], cc)}
                                                            </span>
                                                        ) : (
                                                            <span className="text-sm font-medium text-gray-900 whitespace-nowrap">
                                                                {formatCurrency(product.price, cc)}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3">
                                                    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ring-1 whitespace-nowrap ${stockStyle.bg} ${stockStyle.text} ${stockStyle.ring}`}>
                                                        <span className={`w-1.5 h-1.5 rounded-full ${stockStyle.dot}`} />
                                                        {stockStyle.label}
                                                    </span>
                                                </td>

                                                <td className="px-4 py-3 align-middle sticky right-0 bg-white z-10">
                                                    <InlineActions product={product} onDelete={openDeleteModal} can={can} />
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {showPagination && products?.links && products.links.length > 3 && (
                        <div className="px-6 py-4 border-t border-gray-200 bg-gray-50/50 flex items-center justify-between">
                            <p className="text-sm text-gray-500">
                                Showing <span className="font-medium">{products.from}</span> to <span className="font-medium">{products.to}</span> of <span className="font-medium">{products.total}</span>
                            </p>
                            <div className="flex gap-1">
                                {products.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url || '#'}
                                        className={`px-3 py-1.5 text-sm rounded-lg transition-colors ${
                                            link.active
                                                ? 'bg-blue-600 text-white font-medium'
                                                : link.url
                                                    ? 'text-gray-700 hover:bg-gray-100'
                                                    : 'text-gray-300 cursor-not-allowed'
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

            {/* Delete Confirmation Modal */}
            {deleteModalOpen && productToDelete && (
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
                                Are you sure you want to delete <strong>{productToDelete.name}</strong>?
                            </p>
                            {productToDelete.has_orders ? (
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
                                onClick={() => { setDeleteModalOpen(false); setProductToDelete(null); }}
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Cancel
                            </button>
                            {!productToDelete.has_orders && (
                                <button
                                    onClick={confirmDelete}
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
