import { memo, useState, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    DollarSign, ShoppingBag, TrendingUp, AlertTriangle, XCircle,
    Search, Filter, FileText, Package, ChevronDown,
} from 'lucide-react';

const PER_PAGE_OPTIONS = [25, 50, 100, 1000];

const stockStatusConfig = {
    in_stock:     { label: 'In Stock',     className: 'bg-emerald-100 text-emerald-700' },
    low_stock:    { label: 'Low Stock',    className: 'bg-amber-100 text-amber-700' },
    out_of_stock: { label: 'Out of Stock', className: 'bg-red-100 text-red-700' },
};

function calcStockStatus(stock) {
    if (stock === null || stock <= 0) return stockStatusConfig.out_of_stock;
    if (stock <= 10) return stockStatusConfig.low_stock;
    return stockStatusConfig.in_stock;
}

function formatCurrency(amount) {
    return Number(amount || 0).toLocaleString() + ' MMK';
}

const colorMap = {
    blue:    { bg: 'bg-blue-50',    text: 'text-blue-600' },
    emerald: { bg: 'bg-emerald-50',  text: 'text-emerald-600' },
    amber:   { bg: 'bg-amber-50',   text: 'text-amber-600' },
    violet:  { bg: 'bg-violet-50',  text: 'text-violet-600' },
    red:     { bg: 'bg-red-50',     text: 'text-red-600' },
    slate:   { bg: 'bg-slate-50',   text: 'text-slate-600' },
};

const Card = memo(function Card({ icon: Icon, label, value, color }) {
    const c = colorMap[color] || colorMap.slate;
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-4 lg:p-5">
            <div className="flex flex-col gap-1.5 lg:gap-2">
                <div className={`p-2 rounded-lg w-fit ${c.bg}`}>
                    <Icon className={`w-4 h-4 lg:w-5 lg:h-5 ${c.text}`} />
                </div>
                <div className="min-w-0">
                    <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">{label}</p>
                    <p className="text-sm sm:text-base lg:text-lg font-bold text-gray-900 break-words mt-0.5 tabular-nums">{value}</p>
                </div>
            </div>
        </div>
    );
});

const StockBadge = memo(function StockBadge({ stock }) {
    const s = calcStockStatus(stock);
    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${s.className}`}>
            {s.label}
        </span>
    );
});

const TableRow = memo(function TableRow({ item }) {
    const avgPrice = item.total_quantity > 0
        ? (item.gross_revenue / item.total_quantity).toLocaleString()
        : '0';
    return (
        <tr className="hover:bg-gray-50 transition-colors">
            <td className="px-3 sm:px-5 py-3 sm:py-3.5">
                <span className="text-xs sm:text-sm font-mono text-gray-500">#{item.product_id}</span>
            </td>
            <td className="px-3 sm:px-5 py-3 sm:py-3.5">
                <div className="flex items-center gap-3">
                    <div className="p-1.5 rounded-lg bg-blue-50 shrink-0">
                        <Package className="w-4 h-4 text-blue-600" />
                    </div>
                    <span className="text-xs sm:text-sm font-medium text-gray-900 truncate max-w-[200px]">
                        {item.product_name}
                    </span>
                </div>
            </td>
            <td className="px-3 sm:px-5 py-3 sm:py-3.5 text-xs sm:text-sm text-gray-600">
                {item.category_name || <span className="text-gray-300">&mdash;</span>}
            </td>
            <td className="px-3 sm:px-5 py-3 sm:py-3.5 text-xs sm:text-sm text-gray-900 text-right tabular-nums">
                {Number(item.total_quantity).toLocaleString()}
            </td>
            <td className="px-3 sm:px-5 py-3 sm:py-3.5 text-xs sm:text-sm font-semibold text-gray-900 text-right tabular-nums">
                {formatCurrency(item.gross_revenue)}
            </td>
            <td className="px-3 sm:px-5 py-3 sm:py-3.5 text-xs sm:text-sm text-gray-900 text-right tabular-nums">
                {item.stock !== null ? Number(item.stock).toLocaleString() : <span className="text-gray-300">&mdash;</span>}
            </td>
            <td className="px-3 sm:px-5 py-3 sm:py-3.5">
                <StockBadge stock={item.stock} />
            </td>
            <td className="px-3 sm:px-5 py-3 sm:py-3.5 text-xs sm:text-sm text-gray-600 text-right tabular-nums">
                {item.order_count}
            </td>
        </tr>
    );
});

function FilterForm({ form, setForm, onSubmit, onReset, categories }) {
    return (
        <form onSubmit={onSubmit} className="bg-white rounded-xl border border-gray-200 p-4 lg:p-5 space-y-4">
            <div className="flex items-center gap-2 text-sm font-semibold text-gray-700">
                <Filter className="w-4 h-4" />
                Filters
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-4">
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Date From</label>
                    <input type="date" value={form.date_from}
                        onChange={e => setForm(p => ({ ...p, date_from: e.target.value }))}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Date To</label>
                    <input type="date" value={form.date_to}
                        onChange={e => setForm(p => ({ ...p, date_to: e.target.value }))}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Category</label>
                    <select value={form.category_id}
                        onChange={e => setForm(p => ({ ...p, category_id: e.target.value }))}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Categories</option>
                        {(categories || []).map(c => (
                            <option key={c.id} value={c.id}>{c.name}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Stock Status</label>
                    <select value={form.stock_status}
                        onChange={e => setForm(p => ({ ...p, stock_status: e.target.value }))}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Stock</option>
                        <option value="in_stock">In Stock</option>
                        <option value="low_stock">Low Stock</option>
                        <option value="out_of_stock">Out of Stock</option>
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
                    <input type="text" value={form.search}
                        onChange={e => setForm(p => ({ ...p, search: e.target.value }))}
                        placeholder="Product name or ID..."
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                </div>
            </div>
            <div className="flex items-center gap-2">
                <button type="submit"
                    className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors flex items-center gap-1.5">
                    <Search className="w-3.5 h-3.5" />
                    Search
                </button>
                <button type="button" onClick={onReset}
                    className="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 transition-colors">
                    Reset
                </button>
            </div>
        </form>
    );
}

export default function ProductSalesReport({ products, summary, top_selling, slow_moving, categories, filters }) {
    const baseUrl = '/admin/reports/product-sales';
    const today = new Date().toISOString().split('T')[0];

    const [form, setForm] = useState({
        date_from:    filters.date_from || today,
        date_to:      filters.date_to || today,
        category_id:  filters.category_id || '',
        search:       filters.search || '',
        stock_status: filters.stock_status || '',
    });

    const submit = useCallback((params) => {
        router.get(baseUrl + '?' + params.toString(), {}, { preserveState: true, preserveScroll: true });
    }, [baseUrl]);

    const handleSubmit = useCallback((e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        Object.entries(form).forEach(([k, v]) => { if (v) params.set(k, v); });
        submit(params);
    }, [form, submit]);

    const handleReset = useCallback(() => {
        const defaults = { date_from: today, date_to: today, category_id: '', search: '', stock_status: '' };
        setForm(defaults);
        const params = new URLSearchParams();
        params.set('date_from', today);
        params.set('date_to', today);
        submit(params);
    }, [today, submit]);

    const handlePerPage = useCallback((val) => {
        const params = new URLSearchParams(window.location.search);
        params.set('per_page', val);
        params.delete('page');
        window.location.href = baseUrl + '?' + params.toString();
    }, [baseUrl]);

    const params = new URLSearchParams(window.location.search);
    const perPage = params.get('per_page') || '25';

    return (
        <AdminLayout>
            <Head title="Product Sales Report" />

            <div className="max-w-[1600px] mx-auto px-4 sm:px-5 lg:px-6 py-6 lg:py-8 space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Product Sales Report</h1>
                    <p className="text-sm text-gray-500 mt-1">Product-level sales performance with inventory visibility</p>
                </div>

                <FilterForm form={form} setForm={setForm} onSubmit={handleSubmit} onReset={handleReset} categories={categories} />

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5 gap-3 lg:gap-4">
                    <Card icon={DollarSign}    label="Total Product Revenue" value={formatCurrency(summary?.total_revenue)} color="blue" />
                    <Card icon={ShoppingBag}   label="Total Units Sold"     value={Number(summary?.total_units_sold || 0).toLocaleString()} color="emerald" />
                    <Card icon={TrendingUp}    label="Top Selling Product"  value={summary?.top_selling_product || 'N/A'} color="amber" />
                    <Card icon={AlertTriangle} label="Low Stock Products"   value={Number(summary?.low_stock_count || 0).toLocaleString()} color="violet" />
                    <Card icon={XCircle}       label="Out of Stock Products" value={Number(summary?.out_of_stock_count || 0).toLocaleString()} color="red" />
                </div>

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 sm:px-5 py-4 border-b border-gray-100">
                        <div className="flex items-center gap-2">
                            <FileText className="w-4 h-4 text-gray-500" />
                            <h2 className="text-sm font-semibold text-gray-700">Product Sales</h2>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-sm text-gray-500">Rows:</span>
                            <div className="relative">
                                <select value={perPage} onChange={e => handlePerPage(e.target.value)}
                                    className="border border-gray-300 rounded-lg py-1.5 pl-3 pr-8 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer appearance-none">
                                    {PER_PAGE_OPTIONS.map(n => (
                                        <option key={n} value={n}>{n}</option>
                                    ))}
                                </select>
                                <ChevronDown className="absolute inset-y-0 right-0 top-1/2 -translate-y-1/2 mr-2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="bg-gray-50 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">SKU</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Product Name</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Category</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3 text-right">Qty Sold</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3 text-right">Gross Revenue</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3 text-right">Remaining Stock</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Stock Status</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3 text-right">Orders</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {!products?.data?.length ? (
                                    <tr>
                                        <td colSpan="8" className="px-5 py-16 text-center text-gray-400">
                                            <Package className="w-10 h-10 mx-auto mb-2 text-gray-300" />
                                            No product sales found matching your filters.
                                        </td>
                                    </tr>
                                ) : (
                                    products.data.map((item) => (
                                        <TableRow key={item.product_id} item={item} />
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {products?.last_page > 1 && (
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-gray-200 px-4 sm:px-5 py-4">
                            <p className="text-xs sm:text-sm text-gray-500">
                                Showing {products.from} to {products.to} of {products.total.toLocaleString()} results
                            </p>
                            <div className="flex items-center gap-1">
                                {(() => {
                                    function pageHref(page) {
                                        const p = new URLSearchParams(window.location.search);
                                        p.set('page', String(page));
                                        return baseUrl + '?' + p.toString();
                                    }
                                    const last = products.last_page;
                                    const current = products.current_page;
                                    const pages = [];
                                    const rangeLimit = 2;
                                    const start = Math.max(1, current - rangeLimit);
                                    const end = Math.min(last, current + rangeLimit);
                                    if (start > 1) { pages.push(1); if (start > 2) pages.push('...'); }
                                    for (let i = start; i <= end; i++) pages.push(i);
                                    if (end < last) { if (end < last - 1) pages.push('...'); pages.push(last); }
                                    return (
                                        <>
                                            {current > 1 && (
                                                <Link preserveScroll href={pageHref(current - 1)}
                                                    className="px-2 sm:px-3 py-1.5 text-xs sm:text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">&laquo;</Link>
                                            )}
                                            {pages.map((p, i) =>
                                                p === '...' ? (
                                                    <span key={`e${i}`} className="px-1.5 py-1.5 text-xs text-gray-400">...</span>
                                                ) : (
                                                    <Link key={p} preserveScroll href={pageHref(p)}
                                                        className={`px-2 sm:px-3 py-1.5 text-xs sm:text-sm rounded-lg transition-colors ${p === current
                                                            ? 'bg-blue-600 text-white'
                                                            : 'text-gray-600 hover:bg-gray-100'
                                                            }`}>{p}</Link>
                                                )
                                            )}
                                            {current < last && (
                                                <Link preserveScroll href={pageHref(current + 1)}
                                                    className="px-2 sm:px-3 py-1.5 text-xs sm:text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">&raquo;</Link>
                                            )}
                                        </>
                                    );
                                })()}
                            </div>
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div className="px-4 sm:px-5 py-4 border-b border-gray-100">
                            <div className="flex items-center gap-2">
                                <TrendingUp className="w-4 h-4 text-emerald-600" />
                                <h2 className="text-sm font-semibold text-gray-700">Top Selling Products</h2>
                            </div>
                            <p className="text-xs text-gray-400 mt-0.5">Highest units sold in selected period</p>
                        </div>
                        <div className="divide-y divide-gray-100">
                            {(!top_selling || top_selling.length === 0) ? (
                                <div className="px-5 py-10 text-center text-gray-400 text-sm">No data for this period.</div>
                            ) : (
                                top_selling.map((item, i) => (
                                    <div key={item.product_id} className="flex items-center gap-3 sm:gap-4 px-4 sm:px-5 py-3 hover:bg-gray-50 transition-colors">
                                        <span className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0 ${
                                            i === 0 ? 'bg-amber-100 text-amber-700' :
                                            i === 1 ? 'bg-slate-100 text-slate-600' :
                                            i === 2 ? 'bg-orange-100 text-orange-700' :
                                            'bg-gray-50 text-gray-400'
                                        }`}>
                                            {i + 1}
                                        </span>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-gray-900 truncate">{item.name}</p>
                                            <p className="text-xs text-gray-400">{Number(item.qty_sold).toLocaleString()} units &middot; {formatCurrency(item.revenue)}</p>
                                        </div>
                                        <StockBadge stock={item.stock} />
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div className="px-4 sm:px-5 py-4 border-b border-gray-100">
                            <div className="flex items-center gap-2">
                                <AlertTriangle className="w-4 h-4 text-amber-600" />
                                <h2 className="text-sm font-semibold text-gray-700">Slow Moving Products</h2>
                            </div>
                            <p className="text-xs text-gray-400 mt-0.5">In-stock products with no sales in selected period</p>
                        </div>
                        <div className="divide-y divide-gray-100">
                            {(!slow_moving || slow_moving.length === 0) ? (
                                <div className="px-5 py-10 text-center text-gray-400 text-sm">No slow moving products found.</div>
                            ) : (
                                slow_moving.map((item) => (
                                    <div key={item.id} className="flex items-center gap-3 sm:gap-4 px-4 sm:px-5 py-3 hover:bg-gray-50 transition-colors">
                                        <div className="p-1.5 rounded-lg bg-amber-50 shrink-0">
                                            <Package className="w-4 h-4 text-amber-600" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-gray-900 truncate">{item.name}</p>
                                            <p className="text-xs text-gray-400">Stock: {Number(item.stock).toLocaleString()} &middot; {formatCurrency(item.price)}</p>
                                        </div>
                                        <StockBadge stock={item.stock} />
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}