import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '-';
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function isExpired(endsAt) {
    if (!endsAt) return false;
    return new Date(endsAt) < new Date();
}

function isActive(startsAt, endsAt) {
    const now = new Date();
    if (startsAt && new Date(startsAt) > now) return false;
    if (endsAt && new Date(endsAt) < now) return false;
    return true;
}

export default function FlashSalesIndex({ flashSales, query = '' }) {
    const [search, setSearch] = useState(query);
    const [deleteTarget, setDeleteTarget] = useState(null);

    function handleSearch(e) {
        e.preventDefault();
        router.get(adminUrl('/admin/flash-sales/search'), { query: search }, { preserveState: true });
    }

    function handleToggle(id) {
        router.post(adminUrl(`/admin/flash-sales/${id}/toggle`));
    }

    function handleDelete(id) {
        router.delete(adminUrl(`/admin/flash-sales/${id}`));
        setDeleteTarget(null);
    }

    return (
        <AdminLayout>
            <Head title="Flash Sales" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Flash Sales</h1>
                    <Link href={adminUrl('/admin/flash-sales/create')} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2 text-sm font-medium">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                        Create Flash Sale
                    </Link>
                </div>

                <form onSubmit={handleSearch} className="flex gap-2 mb-6">
                    <div className="relative flex-1">
                        <svg className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Search flash sales..." className="w-full pl-9 pr-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <button type="submit" className="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 text-sm font-medium">Search</button>
                    {query && (
                        <button type="button" onClick={() => { setSearch(''); router.get(adminUrl('/admin/flash-sales')); }} className="px-3 py-2 text-gray-500 hover:text-gray-700 text-sm">Clear</button>
                    )}
                </form>

                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead className="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Flash Sale</th>
                                    <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Discount</th>
                                    <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Products</th>
                                    <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Schedule</th>
                                    <th className="px-5 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th className="px-5 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {!flashSales?.data?.length ? (
                                    <tr>
                                        <td colSpan="6" className="px-5 py-16 text-center">
                                            <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                            <p className="text-gray-500 text-sm font-medium">No flash sales found</p>
                                            <p className="text-gray-400 text-xs mt-1">Create your first flash sale to get started.</p>
                                            <Link href={adminUrl('/admin/flash-sales/create')} className="inline-block mt-4 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">Create Flash Sale</Link>
                                        </td>
                                    </tr>
                                ) : flashSales.data.map((fs) => {
                                    const expired = isExpired(fs.ends_at);
                                    const currentlyActive = isActive(fs.starts_at, fs.ends_at);

                                    return (
                                        <tr key={fs.id} className="hover:bg-gray-50 dark:bg-gray-950/50 transition-colors">
                                            <td className="px-5 py-4">
                                                <div>
                                                    <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{fs.name}</p>
                                                    {fs.description && (
                                                        <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5 line-clamp-1">{fs.description}</p>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold ${fs.discount_type === 'percentage' ? 'text-blue-600 bg-blue-50' : 'text-emerald-600 bg-emerald-50'}`}>
                                                    {fs.discount_type === 'percentage' ? `${fs.discount_value}%` : `${Number(fs.discount_value).toFixed(2)}`}
                                                </span>
                                                {fs.discount_type === 'percentage' && fs.max_discount_amount && (
                                                    <p className="text-xs text-gray-400 mt-0.5">Cap: {Number(fs.max_discount_amount).toFixed(2)}</p>
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className="text-sm text-gray-700 dark:text-gray-300">{fs.products_count} Product(s)</span>
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="text-sm text-gray-700 dark:text-gray-300">
                                                    {fs.starts_at ? formatDate(fs.starts_at) : 'Any'} &rarr;
                                                </div>
                                                <div className="text-sm text-gray-700 dark:text-gray-300">
                                                    {fs.ends_at ? formatDate(fs.ends_at) : 'No end'}
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 text-center">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${expired ? 'bg-gray-100 text-gray-500' : fs.is_active && currentlyActive ? 'bg-emerald-50 text-emerald-700' : fs.is_active ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700'}`}>
                                                    {expired ? 'Expired' : fs.is_active && currentlyActive ? 'Live' : fs.is_active ? 'Scheduled' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <button onClick={() => handleToggle(fs.id)}
                                                        className="p-1.5 text-gray-400 hover:text-amber-600 rounded-md hover:bg-amber-50 transition-colors"
                                                        title={fs.is_active ? 'Deactivate' : 'Activate'}>
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={fs.is_active ? 'M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z' : 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z'} /></svg>
                                                    </button>
                                                    <Link href={adminUrl(`/admin/flash-sales/${fs.id}/edit`)}
                                                        className="p-1.5 text-gray-400 hover:text-blue-600 rounded-md hover:bg-blue-50 transition-colors"
                                                        title="Edit">
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                    </Link>
                                                    <button onClick={() => setDeleteTarget(fs)}
                                                        className="p-1.5 text-gray-400 hover:text-red-600 rounded-md hover:bg-red-50 transition-colors"
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

                {flashSales?.links && flashSales.links.length > 3 && (
                    <div className="mt-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <p className="text-sm text-gray-500">
                            Showing {flashSales.from} to {flashSales.to} of {flashSales.total} results
                        </p>
                        <div className="flex gap-1">
                            {flashSales.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'}
                                    className={`px-3 py-1.5 text-sm rounded-md transition-colors ${link.active ? 'bg-blue-600 text-white' : link.url ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-300 cursor-not-allowed'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }} />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {deleteTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={() => setDeleteTarget(null)}>
                    <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl max-w-md w-full mx-4 p-6" onClick={e => e.stopPropagation()}>
                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center">
                                <svg className="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
                            </div>
                            <div>
                                <h3 className="text-lg font-bold text-gray-900 dark:text-gray-100">Delete Flash Sale</h3>
                                <p className="text-sm text-gray-500">This action cannot be undone.</p>
                            </div>
                        </div>
                        <p className="text-sm text-gray-700 dark:text-gray-300 mb-6">
                            Are you sure you want to delete <span className="font-semibold">{deleteTarget.name}</span>?
                        </p>
                        <div className="flex justify-end gap-3">
                            <button onClick={() => setDeleteTarget(null)}
                                className="px-4 py-2 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-sm rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                            <button onClick={() => handleDelete(deleteTarget.id)}
                                className="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition-colors">Delete</button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
