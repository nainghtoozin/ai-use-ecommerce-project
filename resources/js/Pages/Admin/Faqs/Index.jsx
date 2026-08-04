import { useState, useRef } from 'react';
import { Link, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

const statusColors = {
    active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    inactive: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
};

export default function FaqsIndex({ faqs, categories, filters }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [categoryFilter, setCategoryFilter] = useState(filters?.category || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [selected, setSelected] = useState([]);
    const [bulkAction, setBulkAction] = useState('');
    const dragItem = useRef(null);
    const dragOverItem = useRef(null);

    function handleSearch(e) {
        e.preventDefault();
        applyFilters({ search });
    }

    function applyFilters(overrides = {}) {
        router.get(adminUrl('/admin/faqs'), {
            search: overrides.search ?? search,
            category: overrides.category ?? categoryFilter,
            status: overrides.status ?? statusFilter,
        }, { preserveState: true, replace: true });
    }

    function handleToggle(faq) {
        router.post(adminUrl(`/admin/faqs/${faq.id}/toggle`), {}, { preserveState: true });
    }

    function handleDelete(faq) {
        const q = faq.question_en || 'this FAQ';
        if (window.confirm(`Delete "${q}"?`)) {
            router.delete(adminUrl(`/admin/faqs/${faq.id}`));
        }
    }

    function handleDuplicate(faq) {
        router.post(adminUrl(`/admin/faqs/${faq.id}/duplicate`), {}, { preserveState: true });
    }

    function handleSelectAll(e) {
        setSelected(e.target.checked ? faqs.data.map(f => f.id) : []);
    }

    function handleSelect(id) {
        setSelected(prev => prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]);
    }

    function handleBulkAction() {
        if (!bulkAction || selected.length === 0) return;
        if (bulkAction === 'delete' && !window.confirm(`Delete ${selected.length} FAQ(s)?`)) return;
        router.post(adminUrl('/admin/faqs/bulk-action'), { ids: selected, action: bulkAction }, {
            preserveState: true,
            onSuccess: () => { setSelected([]); setBulkAction(''); },
        });
    }

    function handleDragStart(index) { dragItem.current = index; }
    function handleDragEnter(index) { dragOverItem.current = index; }
    function handleDragEnd() {
        if (dragItem.current === null || dragOverItem.current === null || dragItem.current === dragOverItem.current) {
            dragItem.current = null; dragOverItem.current = null; return;
        }
        const items = [...faqs.data];
        const [dragged] = items.splice(dragItem.current, 1);
        items.splice(dragOverItem.current, 0, dragged);
        router.post(adminUrl('/admin/faqs/reorder'), { ids: items.map(i => i.id) }, { preserveState: true });
        dragItem.current = null; dragOverItem.current = null;
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">FAQ Management</h2>}>
            <Head title="FAQ Management" />
            <div className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                                <form onSubmit={handleSearch} className="flex-1 flex gap-2">
                                    <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search FAQs..." className="flex-1 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                                    <button type="submit" className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">Search</button>
                                </form>
                                <Link href={adminUrl('/admin/faqs/create')} className="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">+ Add FAQ</Link>
                            </div>

                            <div className="flex flex-wrap gap-3 mb-4">
                                <select value={categoryFilter} onChange={(e) => { setCategoryFilter(e.target.value); applyFilters({ category: e.target.value }); }} className="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm text-sm">
                                    <option value="">All Categories</option>
                                    {Object.entries(categories).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                </select>
                                <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); applyFilters({ status: e.target.value }); }} className="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm text-sm">
                                    <option value="">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            {selected.length > 0 && (
                                <div className="flex items-center gap-3 mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                    <span className="text-sm text-blue-700 dark:text-blue-300 font-medium">{selected.length} selected</span>
                                    <select value={bulkAction} onChange={(e) => setBulkAction(e.target.value)} className="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                                        <option value="">Choose action...</option>
                                        <option value="enable">Enable</option>
                                        <option value="disable">Disable</option>
                                        <option value="delete">Delete</option>
                                    </select>
                                    <button onClick={handleBulkAction} disabled={!bulkAction} className="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">Apply</button>
                                </div>
                            )}

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                    <thead className="bg-gray-50 dark:bg-gray-950">
                                        <tr>
                                            <th className="px-4 py-3 w-10"><input type="checkbox" onChange={handleSelectAll} checked={selected.length === faqs.data.length && faqs.data.length > 0} className="rounded border-gray-300 dark:border-gray-700" /></th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Question</th>
                                            <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Lang</th>
                                            <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Order</th>
                                            <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                        {faqs.data.map((faq, index) => (
                                            <tr key={faq.id} draggable onDragStart={() => handleDragStart(index)} onDragEnter={() => handleDragEnter(index)} onDragEnd={handleDragEnd} onDragOver={(e) => e.preventDefault()} className="hover:bg-gray-50 dark:hover:bg-gray-950 cursor-grab active:cursor-grabbing">
                                                <td className="px-4 py-3"><input type="checkbox" checked={selected.includes(faq.id)} onChange={() => handleSelect(faq.id)} className="rounded border-gray-300 dark:border-gray-700" /></td>
                                                <td className="px-4 py-3 whitespace-nowrap"><span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">{categories[faq.category] || faq.category}</span></td>
                                                <td className="px-4 py-3"><div className="text-sm font-medium text-gray-900 dark:text-gray-100 max-w-md truncate">{faq.question_en}</div>{faq.question_my && <div className="text-xs text-gray-500 dark:text-gray-400 max-w-md truncate mt-0.5">{faq.question_my}</div>}</td>
                                                <td className="px-4 py-3 text-center">
                                                    <div className="flex items-center justify-center gap-1">
                                                        <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium ${faq.question_en ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-500'}`}>EN</span>
                                                        <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium ${faq.question_my ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-500'}`}>MY</span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-center text-sm text-gray-500 dark:text-gray-400">{faq.sort_order}</td>
                                                <td className="px-4 py-3 text-center">
                                                    <button onClick={() => handleToggle(faq)} className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer transition-colors ${statusColors[faq.is_active ? 'active' : 'inactive']}`}>
                                                        {faq.is_active ? 'Active' : 'Inactive'}
                                                    </button>
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <button onClick={() => handleDuplicate(faq)} className="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300" title="Duplicate">Copy</button>
                                                        <Link href={adminUrl(`/admin/faqs/${faq.id}/edit`)} className="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Edit</Link>
                                                        <button onClick={() => handleDelete(faq)} className="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                        {faqs.data.length === 0 && (
                                            <tr><td colSpan="7" className="px-6 py-12 text-center text-gray-500 dark:text-gray-400">No FAQs found. Create your first FAQ to get started.</td></tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {faqs.links && faqs.links.length > 3 && (
                                <div className="mt-6 flex flex-wrap gap-1">
                                    {faqs.links.map((link, i) => (
                                        <button key={i} onClick={() => router.get(link.url, {}, { preserveState: true })} disabled={!link.url} className={`px-3 py-1 text-sm rounded ${link.active ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border hover:bg-gray-50'} ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
