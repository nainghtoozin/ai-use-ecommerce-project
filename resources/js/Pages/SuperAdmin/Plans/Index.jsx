import { useState } from 'react';
import { Link, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function PlansIndex({ plans, filters }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');

    function handleSearch(e) {
        e.preventDefault();
        router.get('/superadmin/plans', {
            search,
            status: statusFilter,
        }, { preserveState: true, replace: true });
    }

    function handleFilter(value) {
        setStatusFilter(value);
        router.get('/superadmin/plans', {
            search,
            status: value,
        }, { preserveState: true, replace: true });
    }

    function confirmDelete(plan) {
        if (plan.slug === 'free') {
            alert('The free plan cannot be deleted.');
            return;
        }
        if (window.confirm(`Delete plan "${plan.name}"? This cannot be undone.`)) {
            router.delete(`/superadmin/plans/${plan.id}`);
        }
    }

    const statusColors = {
        active: 'bg-green-100 text-green-800',
        inactive: 'bg-gray-100 text-gray-800',
        deprecated: 'bg-yellow-100 text-yellow-800',
    };

    function formatPrice(price) {
        if (price === null || price === undefined) return '—';
        return '$' + parseFloat(price).toFixed(2);
    }

    function formatLimit(value) {
        if (value === null || value === undefined) return 'Unlimited';
        return value.toLocaleString();
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Subscription Plans</h2>}>
            <Head title="Subscription Plans" />

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
                                        placeholder="Search plans by name or slug..."
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
                                    href="/superadmin/plans/create"
                                    className="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors flex items-center gap-1"
                                >
                                    Create Plan
                                </Link>
                            </div>

                            <div className="flex gap-4 mb-6">
                                <select
                                    value={statusFilter}
                                    onChange={(e) => handleFilter(e.target.value)}
                                    className="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                >
                                    <option value="">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="deprecated">Deprecated</option>
                                </select>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monthly</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Yearly</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Products</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Storage</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {plans.data.map((plan) => (
                                            <tr key={plan.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-medium text-gray-900">{plan.name}</div>
                                                    <div className="text-sm text-gray-500">{plan.slug}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                                    {formatPrice(plan.monthly_price)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                                    {formatPrice(plan.yearly_price)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                                    {formatLimit(plan.product_limit)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                                    {formatLimit(plan.staff_limit)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                                    {plan.storage_limit !== null && plan.storage_limit !== undefined
                                                        ? plan.storage_limit + ' MB'
                                                        : 'Unlimited'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-center">
                                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColors[plan.status] || 'bg-gray-100 text-gray-800'}`}>
                                                        {plan.status}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Link href={`/superadmin/plans/${plan.id}/edit`} className="text-indigo-600 hover:text-indigo-900">
                                                            Edit
                                                        </Link>
                                                        {plan.slug !== 'free' && (
                                                            <button onClick={() => confirmDelete(plan)} className="text-red-600 hover:text-red-900">
                                                                Delete
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                        {plans.data.length === 0 && (
                                            <tr>
                                                <td colSpan="8" className="px-6 py-12 text-center text-gray-500">
                                                    No plans found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {plans.links && (
                                <div className="mt-6">
                                    {plans.links.map((link, i) => (
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
