import { useState } from 'react';
import { Link, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function SubscriptionsIndex({ subscriptions, filters, stats }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [showAssign, setShowAssign] = useState(false);
    const [assignTenant, setAssignTenant] = useState('');
    const [tenants, setTenants] = useState([]);
    const [planId, setPlanId] = useState('');
    const [assigning, setAssigning] = useState(false);

    function handleSearch(e) {
        e.preventDefault();
        router.get('/superadmin/subscriptions', { search, status: statusFilter }, { preserveState: true, replace: true });
    }

    function handleFilter(value) {
        setStatusFilter(value);
        router.get('/superadmin/subscriptions', { search, status: value }, { preserveState: true, replace: true });
    }

    function searchTenants(query) {
        if (query.length < 2) { setTenants([]); return; }
        router.get('/superadmin/subscriptions', { search_tenants: query }, { preserveState: true, only: ['tenants_list'] })
            .then(() => {});
    }

    function submitAssign(e) {
        e.preventDefault();
        setAssigning(true);
        router.post('/superadmin/subscriptions', {
            tenant_id: assignTenant,
            plan_id: planId,
        }, {
            preserveState: true,
            onSuccess: () => {
                setShowAssign(false);
                setAssignTenant('');
                setPlanId('');
                setAssigning(false);
            },
            onError: () => setAssigning(false),
        });
    }

    const statusColors = {
        trialing: 'bg-blue-100 text-blue-800',
        active: 'bg-green-100 text-green-800',
        past_due: 'bg-yellow-100 text-yellow-800',
        canceled: 'bg-gray-100 text-gray-800',
        expired: 'bg-red-100 text-red-800',
    };

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Subscriptions</h2>}>
            <Head title="Subscriptions" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    {/* Stats Cards */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <p className="text-sm text-gray-500">Active / Trialing</p>
                            <p className="mt-1 text-2xl font-semibold text-green-600">{stats.active}</p>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <p className="text-sm text-gray-500">Expiring Soon</p>
                            <p className="mt-1 text-2xl font-semibold text-yellow-600">{stats.expiring_soon}</p>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <p className="text-sm text-gray-500">Past Due</p>
                            <p className="mt-1 text-2xl font-semibold text-orange-600">{stats.past_due}</p>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <p className="text-sm text-gray-500">Expired</p>
                            <p className="mt-1 text-2xl font-semibold text-red-600">{stats.expired}</p>
                        </div>
                    </div>

                    {/* Search + Assign */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                            <form onSubmit={handleSearch} className="flex-1 flex gap-2">
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search by merchant name, slug, or email..."
                                    className="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                />
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                                >
                                    Search
                                </button>
                            </form>
                            <button
                                onClick={() => setShowAssign(!showAssign)}
                                className="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors"
                            >
                                {showAssign ? 'Cancel' : 'Assign Plan'}
                            </button>
                        </div>

                        {/* Assign Plan Form */}
                        {showAssign && (
                            <form onSubmit={submitAssign} className="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <h3 className="text-sm font-semibold text-gray-700 mb-3">Assign Subscription Plan</h3>
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Tenant ID</label>
                                        <input
                                            type="number"
                                            value={assignTenant}
                                            onChange={(e) => setAssignTenant(e.target.value)}
                                            placeholder="Enter tenant ID..."
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                            required
                                        />
                                        <p className="text-xs text-gray-400 mt-1">Find ID on Merchant page</p>
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Plan</label>
                                        <select
                                            value={planId}
                                            onChange={(e) => setPlanId(e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                            required
                                        >
                                            <option value="">Select plan...</option>
                                            {subscriptions.data.length > 0 && subscriptions.data[0].plans_list?.map((plan) => (
                                                <option key={plan.id} value={plan.id}>{plan.name}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="flex items-end">
                                        <button
                                            type="submit"
                                            disabled={assigning}
                                            className="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                                        >
                                            {assigning ? 'Assigning...' : 'Assign'}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        )}

                        {/* Filter */}
                        <div className="flex gap-4 mb-4">
                            <select
                                value={statusFilter}
                                onChange={(e) => handleFilter(e.target.value)}
                                className="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                            >
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="trialing">Trialing</option>
                                <option value="past_due">Past Due</option>
                                <option value="canceled">Canceled</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>

                        {/* Table */}
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Merchant</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {subscriptions.data.map((sub) => (
                                        <tr key={sub.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#{sub.id}</td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-sm font-medium text-gray-900">{sub.tenant?.name || '—'}</div>
                                                <div className="text-xs text-gray-500">{sub.tenant?.email || ''}</div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{sub.plan?.name || '—'}</td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColors[sub.status] || 'bg-gray-100 text-gray-800'}`}>
                                                    {sub.status}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {sub.expires_at ? new Date(sub.expires_at).toLocaleDateString() : '—'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {new Date(sub.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <Link
                                                    href={`/superadmin/subscriptions/${sub.id}`}
                                                    className="text-blue-600 hover:text-blue-900"
                                                >
                                                    Manage
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                    {subscriptions.data.length === 0 && (
                                        <tr>
                                            <td colSpan="7" className="px-6 py-12 text-center text-gray-500">
                                                No subscriptions found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {subscriptions.links && (
                            <div className="mt-6">
                                {subscriptions.links.map((link, i) => (
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
        </AdminLayout>
    );
}
