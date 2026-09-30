import { useState } from 'react';
import { Link, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import PerPageSelect from '@/Components/PerPageSelect';
import { usePermission } from '@/Hooks/usePermission';

function SkeletonRow() {
    return (
        <tr className="animate-pulse">
            <td className="px-6 py-4">
                <div className="flex items-center">
                    <div className="h-10 w-10 rounded-full bg-gray-200" />
                    <div className="ml-4 h-4 w-28 bg-gray-200 rounded" />
                </div>
            </td>
            <td className="px-6 py-4"><div className="h-4 w-36 bg-gray-200 rounded" /></td>
            <td className="px-6 py-4"><div className="h-4 w-24 bg-gray-200 rounded" /></td>
            <td className="px-6 py-4"><div className="h-5 w-16 bg-gray-200 rounded-full" /></td>
            <td className="px-6 py-4"><div className="h-4 w-20 bg-gray-200 rounded" /></td>
            <td className="px-6 py-4"><div className="flex justify-end gap-2"><div className="h-7 w-7 bg-gray-200 rounded-lg" /><div className="h-7 w-7 bg-gray-200 rounded-lg" /><div className="h-7 w-7 bg-gray-200 rounded-lg" /></div></td>
        </tr>
    );
}

export default function CustomersIndex({ customers, filters, showPagination = true, warning = null }) {
    const { can } = usePermission();
    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [loading, setLoading] = useState(false);
    const [statusModal, setStatusModal] = useState(null);
    const [reason, setReason] = useState('');
    const [reasonError, setReasonError] = useState('');
    const [selectedSuggestion, setSelectedSuggestion] = useState('');

    const reasonSuggestions = {
        suspend: [
            'Violation of store policies',
            'Suspicious or unusual account activity',
            'Repeated order/payment issues',
            'Customer requested account suspension',
            'Other',
        ],
        ban: [
            'Violation of store policies',
            'Fraudulent activity',
            'Repeated order/payment issues',
            'Abuse towards staff',
            'Other',
        ],
    };

    const filteredStatuses = ['', 'active', 'suspended', 'banned', 'inactive'];

    function handleSearch(e) {
        e.preventDefault();
        setLoading(true);
        router.get(adminUrl('/admin/customers'), {
            search,
            status: statusFilter,
        }, { preserveState: true, replace: true, onFinish: () => setLoading(false) });
    }

    function handleFilterChange(type, value) {
        const params = { search, status: statusFilter, [type]: value };
        if (type === 'status') setStatusFilter(value);
        setLoading(true);
        router.get(adminUrl('/admin/customers'), params, { preserveState: true, replace: true, onFinish: () => setLoading(false) });
    }

    function openStatusModal(action, customer) {
        setStatusModal({ action, customer });
        setReason('');
        setReasonError('');
        setSelectedSuggestion('');
    }

    function closeStatusModal() {
        setStatusModal(null);
        setReason('');
        setReasonError('');
        setSelectedSuggestion('');
    }

    function pickSuggestion(suggestion) {
        setSelectedSuggestion(suggestion);
        setReason(suggestion === 'Other' ? '' : suggestion);
        if (reasonError) setReasonError('');
    }

    function confirmStatusModal() {
        if (!reason.trim()) {
            setReasonError('A reason is required.');
            return;
        }
        const { action, customer } = statusModal;
        router.post(adminUrl(`/admin/customers/${customer.id}/${action}`), { reason: reason.trim() }, {
            onSuccess: closeStatusModal,
        });
    }

    function handleActivate(customer) {
        router.post(adminUrl(`/admin/customers/${customer.id}/activate`));
    }

    function confirmDelete(customer) {
        if (window.confirm(`Remove "${customer.name}" from this store? Their order history is preserved.`)) {
            router.delete(adminUrl(`/admin/customers/${customer.id}`));
        }
    }

    const statusBadge = (status, reason = null) => {
        const colors = {
            active: 'bg-green-100 text-green-800',
            suspended: 'bg-yellow-100 text-yellow-800',
            banned: 'bg-red-100 text-red-800',
            inactive: 'bg-gray-100 text-gray-800',
        };
        return (
            <span title={reason || undefined} className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colors[status] || 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200'}`}>
                {status}
            </span>
        );
    };

    const formatDate = (value) => value ? new Date(value).toLocaleDateString() : '—';
    const data = customers?.data || [];
    const isEmpty = !loading && data.length === 0;

    function actionButtons(customer, compact = false) {
        return (
            <div className={`flex items-center ${compact ? '' : 'justify-end'} gap-2`}>
                {can('customers.view') && (
                    <Link href={adminUrl(`/admin/customers/${customer.id}`)} className="text-blue-600 hover:text-blue-900" title="View">
                        <i className="bi bi-eye"></i>
                    </Link>
                )}
                {can('users.update') && (
                    <Link href={adminUrl(`/admin/customers/${customer.id}/edit`)} className="text-indigo-600 hover:text-indigo-900" title="Edit">
                        <i className="bi bi-pencil"></i>
                    </Link>
                )}
                {can('users.suspend') && customer.status === 'active' && (
                    <button onClick={() => openStatusModal('suspend', customer)} className="text-yellow-600 hover:text-yellow-900" title="Suspend">
                        <i className="bi bi-pause-circle"></i>
                    </button>
                )}
                {can('users.ban') && customer.status === 'active' && (
                    <button onClick={() => openStatusModal('ban', customer)} className="text-red-600 hover:text-red-900" title="Ban">
                        <i className="bi bi-slash-circle"></i>
                    </button>
                )}
                {can('users.activate') && customer.status !== 'active' && (
                    <button onClick={() => handleActivate(customer)} className="text-green-600 hover:text-green-900" title="Reactivate">
                        <i className="bi bi-check-circle"></i>
                    </button>
                )}
                {can('users.delete') && (
                    <button onClick={() => confirmDelete(customer)} className="text-red-600 hover:text-red-900" title="Remove">
                        <i className="bi bi-trash"></i>
                    </button>
                )}
            </div>
        );
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Customers</h2>}>
            <Head title="Customers" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="mb-4 flex items-center justify-between">
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {customers?.total ? `${customers.total} customer${customers.total !== 1 ? 's' : ''} total` : 'Storefront registered customers'}
                        </p>
                    </div>
                    <div className="bg-white dark:bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                                <form onSubmit={handleSearch} className="flex-1 flex gap-2">
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Search name, email, phone..."
                                        className="flex-1 rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                    />
                                    <button
                                        type="submit"
                                        className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                                    >
                                        <i className="bi bi-search mr-1"></i> Search
                                    </button>
                                </form>
                            </div>

                            <div className="flex gap-4 mb-6">
                                <select
                                    value={statusFilter}
                                    onChange={(e) => handleFilterChange('status', e.target.value)}
                                    className="rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                >
                                    {filteredStatuses.map((s) => (
                                        <option key={s} value={s}>{s || 'All Statuses'}</option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex justify-between items-center mb-4">
                                <PerPageSelect />
                                {warning && (
                                    <p className="text-sm text-amber-600">{warning}</p>
                                )}
                            </div>

                            <div className="hidden md:block overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                    <thead className="bg-gray-50 dark:bg-gray-950">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Customer</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Joined</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                        {loading && (
                                            <>
                                                <SkeletonRow />
                                                <SkeletonRow />
                                                <SkeletonRow />
                                                <SkeletonRow />
                                                <SkeletonRow />
                                            </>
                                        )}
                                        {!loading && data.map((customer) => (
                                            <tr key={customer.id} className="hover:bg-gray-50 dark:bg-gray-950">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center">
                                                        <div className="flex-shrink-0 h-10 w-10">
                                                            {customer.profile_image_url ? (
                                                                <img className="h-10 w-10 rounded-full object-cover" src={customer.profile_image_url} alt="" />
                                                            ) : (
                                                                <div className="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                                    <span className="text-sm font-medium text-blue-600">{customer.name?.charAt(0).toUpperCase()}</span>
                                                                </div>
                                                            )}
                                                        </div>
                                                        <div className="ml-4">
                                                            <div className="text-sm font-medium text-gray-900 dark:text-gray-100">{customer.name}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{customer.email}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{customer.phone || '—'}</td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {statusBadge(customer.status, customer.status_reason)}
                                                    {customer.status !== 'active' && customer.status_reason && (
                                                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-[200px] truncate" title={customer.status_reason}>
                                                            {customer.status_reason}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{formatDate(customer.joined_at)}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    {actionButtons(customer)}
                                                </td>
                                            </tr>
                                        ))}
                                        {isEmpty && (
                                            <tr>
                                                <td colSpan="6" className="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                                    No customers found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            <div className="md:hidden divide-y divide-gray-200 dark:divide-gray-800">
                                {loading && [1, 2, 3].map((i) => (
                                    <div key={i} className="p-4">
                                        <div className="h-14 bg-gray-100 dark:bg-gray-800 rounded-xl animate-pulse" />
                                    </div>
                                ))}
                                {!loading && data.map((customer) => (
                                    <div key={customer.id} className="p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex items-center gap-3 min-w-0">
                                                <div className="flex-shrink-0 h-10 w-10">
                                                    {customer.profile_image_url ? (
                                                        <img className="h-10 w-10 rounded-full object-cover" src={customer.profile_image_url} alt="" />
                                                    ) : (
                                                        <div className="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                            <span className="text-sm font-medium text-blue-600">{customer.name?.charAt(0).toUpperCase()}</span>
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{customer.name}</p>
                                                    <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{customer.email}</p>
                                                    <div className="flex items-center gap-2 mt-1.5">
                                                        {statusBadge(customer.status, customer.status_reason)}
                                                    </div>
                                                </div>
                                            </div>
                                            {actionButtons(customer, true)}
                                        </div>
                                        <div className="flex items-center gap-4 mt-2 text-[11px] text-gray-400 dark:text-gray-500">
                                            <span>{customer.phone || 'No phone'}</span>
                                            <span>Joined {formatDate(customer.joined_at)}</span>
                                        </div>
                                    </div>
                                ))}
                                {isEmpty && (
                                    <div className="p-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                        No customers found.
                                    </div>
                                )}
                            </div>

                            {customers?.links && showPagination && (
                                <div className="mt-6">
                                    {customers.links.map((link, i) => (
                                        <button
                                            key={i}
                                            onClick={() => router.get(link.url, {}, { preserveState: true })}
                                            disabled={!link.url}
                                            className={`px-3 py-1 mx-0.5 text-sm rounded ${link.active ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border hover:bg-gray-50'} ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {statusModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" onClick={closeStatusModal} />
                    <div className="relative bg-white dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
                        <div className="px-6 pt-6 pb-4 border-b border-gray-100 dark:border-gray-800">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 capitalize">
                                {statusModal.action} customer
                            </h3>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {statusModal.customer.name} ({statusModal.customer.email}) will not be able to log in to this store.
                            </p>
                        </div>
                        <div className="p-6 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Reason <span className="text-red-500">*</span>
                                </label>
                                <div className="flex flex-wrap gap-2 mb-3">
                                    {(reasonSuggestions[statusModal.action] || []).map((suggestion) => (
                                        <button
                                            key={suggestion}
                                            type="button"
                                            onClick={() => pickSuggestion(suggestion)}
                                            className={`px-3 py-1.5 text-xs font-medium rounded-full border transition-colors ${
                                                selectedSuggestion === suggestion
                                                    ? 'bg-blue-600 text-white border-blue-600'
                                                    : 'bg-gray-50 dark:bg-gray-950 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-700 hover:border-blue-400 hover:text-blue-600'
                                            }`}
                                        >
                                            {suggestion}
                                        </button>
                                    ))}
                                </div>
                                <textarea
                                    value={reason}
                                    onChange={(e) => { setReason(e.target.value); setSelectedSuggestion(''); if (reasonError) setReasonError(''); }}
                                    rows={4}
                                    placeholder={selectedSuggestion === 'Other' ? 'Describe the reason...' : 'Explain why this customer is being suspended or banned...'}
                                    className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                />
                                {reasonError && <p className="mt-1 text-sm text-red-600">{reasonError}</p>}
                            </div>
                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={closeStatusModal}
                                    className="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={confirmStatusModal}
                                    className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 capitalize"
                                >
                                    Confirm {statusModal.action}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
