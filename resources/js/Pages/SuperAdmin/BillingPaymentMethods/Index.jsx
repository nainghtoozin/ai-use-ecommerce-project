import { useState } from 'react';
import { Link, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { CreditCard } from 'lucide-react';

export default function BillingPaymentMethodsIndex({ paymentMethods, filters }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');

    function handleSearch(e) {
        e.preventDefault();
        router.get('/superadmin/billing-payment-methods', {
            search,
            status: statusFilter,
        }, { preserveState: true, replace: true });
    }

    function handleFilter(value) {
        setStatusFilter(value);
        router.get('/superadmin/billing-payment-methods', {
            search,
            status: value,
        }, { preserveState: true, replace: true });
    }

    function handleToggle(id) {
        router.post(`/superadmin/billing-payment-methods/${id}/toggle`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function handleDelete(id) {
        if (window.confirm('Archive this billing payment method?')) {
            router.delete(`/superadmin/billing-payment-methods/${id}`, {
                preserveState: true,
                preserveScroll: true,
            });
        }
    }

    function handleRestore(id) {
        router.post(`/superadmin/billing-payment-methods/${id}/restore`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    const items = paymentMethods?.data || [];

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Billing Payment Methods</h2>}>
            <Head title="Billing Payment Methods" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                                <form onSubmit={handleSearch} className="flex-1 flex gap-2">
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Search by name, display name, or bank..."
                                        className="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                    />
                                    <button
                                        type="submit"
                                        className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                                    >
                                        Search
                                    </button>
                                </form>
                                <Link
                                    href="/superadmin/billing-payment-methods/create"
                                    className="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors flex items-center gap-1"
                                >
                                    <CreditCard className="w-4 h-4" />
                                    Create Method
                                </Link>
                            </div>

                            <div className="flex gap-4 mb-6">
                                <select
                                    value={statusFilter}
                                    onChange={(e) => handleFilter(e.target.value)}
                                    className="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                >
                                    <option value="">All Methods</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bank</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Default</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {items.map((pm) => {
                                            const isArchived = !!pm.deleted_at;
                                            return (
                                                <tr key={pm.id} className={`hover:bg-gray-50 ${isArchived ? 'opacity-60' : ''}`}>
                                                    <td className="px-3 py-4 whitespace-nowrap text-center text-sm text-gray-400">
                                                        {pm.sort_order ?? 0}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="text-sm font-medium text-gray-900">{pm.display_name}</div>
                                                        {pm.bank_name && (
                                                            <div className="text-sm text-gray-500">{pm.bank_name}</div>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">
                                                        {pm.type === 'bank_transfer' ? 'Bank Transfer' : pm.type}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        {pm.bank_name || '-'}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        {pm.account_name ? `${pm.account_name} (${pm.account_number})` : '-'}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        {pm.currency || '-'}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-center">
                                                        {pm.is_default ? (
                                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                                                Default
                                                            </span>
                                                        ) : (
                                                            <span className="text-gray-300">—</span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-center">
                                                        {isArchived ? (
                                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                                Archived
                                                            </span>
                                                        ) : (
                                                            <button
                                                                onClick={() => handleToggle(pm.id)}
                                                                className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer transition-colors ${
                                                                    pm.is_active
                                                                        ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                                                        : 'bg-red-100 text-red-700 hover:bg-red-200'
                                                                }`}
                                                            >
                                                                {pm.is_active ? 'Active' : 'Inactive'}
                                                            </button>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        {isArchived ? (
                                                            <button
                                                                onClick={() => handleRestore(pm.id)}
                                                                className="text-green-600 hover:text-green-900"
                                                            >
                                                                Restore
                                                            </button>
                                                        ) : (
                                                            <div className="flex items-center justify-end gap-2">
                                                                <Link
                                                                    href={`/superadmin/billing-payment-methods/${pm.id}/edit`}
                                                                    className="text-indigo-600 hover:text-indigo-900"
                                                                >
                                                                    Edit
                                                                </Link>
                                                                <button
                                                                    onClick={() => handleDelete(pm.id)}
                                                                    className="text-red-600 hover:text-red-900"
                                                                >
                                                                    Archive
                                                                </button>
                                                            </div>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                        {items.length === 0 && (
                                            <tr>
                                                <td colSpan="9" className="px-6 py-12 text-center text-gray-500">
                                                    No billing payment methods found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {paymentMethods?.links && (
                                <div className="mt-6">
                                    {paymentMethods.links.map((link, i) => (
                                        <button
                                            key={i}
                                            onClick={() => router.get(link.url, {}, { preserveState: true })}
                                            disabled={!link.url}
                                            className={`px-3 py-1 mx-0.5 text-sm rounded ${link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border hover:bg-gray-50'} ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
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
