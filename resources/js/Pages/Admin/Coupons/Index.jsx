import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '-';
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function isExpired(endsAt) {
    if (!endsAt) return false;
    return new Date(endsAt) < new Date();
}

export default function CouponsIndex({ coupons, query = '' }) {
    const [search, setSearch] = useState(query);
    const [deleteTarget, setDeleteTarget] = useState(null);

    function handleSearch(e) {
        e.preventDefault();
        router.get(adminUrl('/admin/coupons/search'), { query: search }, { preserveState: true });
    }

    function handleToggle(id) {
        router.post(adminUrl(`/admin/coupons/${id}/toggle`));
    }

    function handleDuplicate(id) {
        router.post(adminUrl(`/admin/coupons/${id}/duplicate`));
    }

    function handleDelete(id) {
        router.delete(adminUrl(`/admin/coupons/${id}`));
        setDeleteTarget(null);
    }

    function handleCopy(code) {
        navigator.clipboard?.writeText(code);
    }

    function typeColor(type) {
        if (type === 'percentage') return 'text-blue-600 bg-blue-50';
        if (type === 'fixed') return 'text-emerald-600 bg-emerald-50';
        return 'text-purple-600 bg-purple-50';
    }

    return (
        <AdminLayout>
            <Head title="Coupons" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Coupons</h1>
                    <Link href={adminUrl('/admin/coupons/create')} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2 text-sm font-medium">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                        Add Coupon
                    </Link>
                </div>

                {/* Search */}
                <form onSubmit={handleSearch} className="flex gap-2 mb-6">
                    <div className="relative flex-1">
                        <svg className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Search by name, code, or type..." className="w-full pl-9 pr-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <button type="submit" className="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 text-sm font-medium">Search</button>
                    {query && (
                        <button type="button" onClick={() => { setSearch(''); router.get(adminUrl('/admin/coupons')); }} className="px-3 py-2 text-gray-500 hover:text-gray-700 dark:text-gray-300 text-sm">Clear</button>
                    )}
                </form>

                {/* Table */}
                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead className="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Coupon</th>
                                    <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Code</th>
                                    <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Discount</th>
                                    <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Applies To</th>
                                    <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Usage</th>
                                    <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Schedule</th>
                                    <th className="px-5 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th className="px-5 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {!coupons?.data?.length ? (
                                    <tr>
                                        <td colSpan="8" className="px-5 py-16 text-center">
                                            <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
                                            <p className="text-gray-500 dark:text-gray-400 text-sm font-medium">No coupons found</p>
                                            <p className="text-gray-400 dark:text-gray-500 text-xs mt-1">Get started by creating your first coupon.</p>
                                            <Link href={adminUrl('/admin/coupons/create')} className="inline-block mt-4 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">Create Coupon</Link>
                                        </td>
                                    </tr>
                                ) : coupons.data.map((c) => {
                                    const expired = isExpired(c.ends_at);
                                    const usagePct = c.usage_limit ? Math.min(100, Math.round((c.usage_count / c.usage_limit) * 100)) : 0;

                                    return (
                                        <tr key={c.id} className="hover:bg-gray-50 dark:bg-gray-950/50 transition-colors">
                                            <td className="px-5 py-4">
                                                <div>
                                                    <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{c.name}</p>
                                                    <div className="flex flex-wrap gap-1 mt-1">
                                                        {c.stackable && (
                                                            <span className="inline-flex items-center px-1.5 py-0.5 bg-emerald-50 text-emerald-600 rounded text-xs font-medium">Stackable</span>
                                                        )}
                                                        {c.applies_to === 'all' && (
                                                            <span className="inline-flex items-center px-1.5 py-0.5 bg-gray-50 dark:bg-gray-950 text-gray-500 dark:text-gray-400 rounded text-xs">All Products</span>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-5 py-4">
                                                <button onClick={() => handleCopy(c.code)}
                                                    className="group inline-flex items-center gap-1.5 px-2.5 py-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-md text-xs font-mono text-gray-700 dark:text-gray-300 hover:bg-gray-100 transition-colors"
                                                    title="Copy code">
                                                    {c.code}
                                                    <svg className="w-3 h-3 text-gray-400 group-hover:text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                                </button>
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="flex items-center gap-1.5">
                                                    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold ${typeColor(c.type)}`}>
                                                        {c.type === 'percentage' ? `${c.value}%` : c.type === 'fixed' ? `${Number(c.value).toFixed(2)}` : 'Free'}
                                                    </span>
                                                </div>
                                                {c.type === 'percentage' && c.max_discount_amount && (
                                                    <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Cap: {Number(c.max_discount_amount).toFixed(2)}</p>
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className="text-sm text-gray-700 dark:text-gray-300">
                                                    {c.applies_to === 'all' ? 'All Products' : c.applies_to === 'products' ? `${c.products_count} Product(s)` : `${c.categories_count} Categor(ies)`}
                                                </span>
                                                {c.minimum_order_amount > 0 && (
                                                    <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Min: {Number(c.minimum_order_amount).toFixed(2)}</p>
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="flex items-center gap-2">
                                                    <div className="flex-1 max-w-[100px]">
                                                        <div className="w-full h-1.5 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                                            <div className={`h-full rounded-full transition-all ${usagePct >= 90 ? 'bg-red-500' : usagePct >= 70 ? 'bg-amber-500' : 'bg-blue-500'}`}
                                                                style={{ width: `${usagePct}%` }} />
                                                        </div>
                                                    </div>
                                                    <span className="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                                        {c.usage_count}{c.usage_limit ? `/${c.usage_limit}` : ''}
                                                    </span>
                                                </div>
                                                {c.per_customer_limit && (
                                                    <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{c.per_customer_limit} per customer</p>
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="text-sm text-gray-700 dark:text-gray-300">
                                                    {c.starts_at ? formatDate(c.starts_at) : 'Any'} &rarr;
                                                </div>
                                                <div className="text-sm text-gray-700 dark:text-gray-300">
                                                    {c.ends_at ? formatDate(c.ends_at) : 'No end'}
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 text-center">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${expired ? 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400' : c.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}`}>
                                                    {expired ? 'Expired' : c.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <button onClick={() => handleToggle(c.id)}
                                                        className="p-1.5 text-gray-400 dark:text-gray-500 hover:text-amber-600 rounded-md hover:bg-amber-50 transition-colors"
                                                        title={c.is_active ? 'Deactivate' : 'Activate'}>
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={c.is_active ? 'M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z' : 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z'} /></svg>
                                                    </button>
                                                    <Link href={adminUrl(`/admin/coupons/${c.id}/edit`)}
                                                        className="p-1.5 text-gray-400 dark:text-gray-500 hover:text-blue-600 rounded-md hover:bg-blue-50 transition-colors"
                                                        title="Edit">
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                    </Link>
                                                    <button onClick={() => handleDuplicate(c.id)}
                                                        className="p-1.5 text-gray-400 dark:text-gray-500 hover:text-purple-600 rounded-md hover:bg-purple-50 transition-colors"
                                                        title="Duplicate">
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                                    </button>
                                                    <button onClick={() => setDeleteTarget(c)}
                                                        className="p-1.5 text-gray-400 dark:text-gray-500 hover:text-red-600 rounded-md hover:bg-red-50 transition-colors"
                                                        title="Delete">
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pagination */}
                {coupons?.links && coupons.links.length > 3 && (
                    <div className="mt-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Showing {coupons.from} to {coupons.to} of {coupons.total} results
                        </p>
                        <div className="flex gap-1">
                            {coupons.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'}
                                    className={`px-3 py-1.5 text-sm rounded-md transition-colors ${link.active ? 'bg-blue-600 text-white' : link.url ? 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:bg-gray-800' : 'text-gray-300 cursor-not-allowed'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }} />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Delete Confirmation Modal */}
            {deleteTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={() => setDeleteTarget(null)}>
                    <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl max-w-md w-full mx-4 p-6" onClick={e => e.stopPropagation()}>
                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center">
                                <svg className="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
                            </div>
                            <div>
                                <h3 className="text-lg font-bold text-gray-900 dark:text-gray-100">Delete Coupon</h3>
                                <p className="text-sm text-gray-500 dark:text-gray-400">This action cannot be undone.</p>
                            </div>
                        </div>
                        <p className="text-sm text-gray-700 dark:text-gray-300 mb-6">
                            Are you sure you want to delete <span className="font-semibold">{deleteTarget.name}</span>?
                            {deleteTarget.usage_count > 0 && (
                                <span className="block mt-1 text-amber-600">This coupon has been used {deleteTarget.usage_count} time(s).</span>
                            )}
                        </p>
                        <div className="flex justify-end gap-3">
                            <button onClick={() => setDeleteTarget(null)}
                                className="px-4 py-2 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-sm rounded-lg hover:bg-gray-50 dark:bg-gray-950 transition-colors">Cancel</button>
                            <button onClick={() => handleDelete(deleteTarget.id)}
                                className="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition-colors">Delete</button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
