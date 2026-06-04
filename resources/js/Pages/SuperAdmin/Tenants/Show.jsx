import { Link, Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function ShowTenant({ tenant, users, stats }) {
    const statusColors = {
        active: 'bg-green-100 text-green-800',
        suspended: 'bg-yellow-100 text-yellow-800',
        trialing: 'bg-blue-100 text-blue-800',
    };

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Merchant: {tenant.name}</h2>}>
            <Head title={`Merchant - ${tenant.name}`} />

            <div className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    {/* Stats Cards */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <p className="text-sm text-gray-500">Status</p>
                            <p className="mt-1">
                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColors[tenant.status] || 'bg-gray-100 text-gray-800'}`}>
                                    {tenant.status}
                                </span>
                            </p>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <p className="text-sm text-gray-500">Products</p>
                            <p className="mt-1 text-2xl font-semibold text-gray-900">{stats.products}</p>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <p className="text-sm text-gray-500">Orders</p>
                            <p className="mt-1 text-2xl font-semibold text-gray-900">{stats.orders}</p>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <p className="text-sm text-gray-500">Revenue</p>
                            <p className="mt-1 text-2xl font-semibold text-gray-900">
                                {new Intl.NumberFormat().format(stats.revenue)} MMK
                            </p>
                        </div>
                    </div>

                    {/* Tenant Details */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Store Details</h3>
                            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <dt className="text-sm text-gray-500">Store Name</dt>
                                    <dd className="text-sm font-medium text-gray-900">{tenant.name}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm text-gray-500">Slug</dt>
                                    <dd className="text-sm font-medium text-gray-900">{tenant.slug}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm text-gray-500">Store URL</dt>
                                    <dd className="text-sm font-medium text-gray-900 flex items-center gap-2">
                                        <span>{tenant.store_url || '—'}</span>
                                        {tenant.store_url && (
                                            <button
                                                onClick={() => {
                                                    navigator.clipboard.writeText(tenant.store_url);
                                                    alert('Store URL copied!');
                                                }}
                                                className="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-600 bg-blue-50 rounded hover:bg-blue-100 transition-colors"
                                                title="Copy Store URL"
                                            >
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                                Copy Link
                                            </button>
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm text-gray-500">Domain</dt>
                                    <dd className="text-sm font-medium text-gray-900">{tenant.domain || '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm text-gray-500">Email</dt>
                                    <dd className="text-sm font-medium text-gray-900">{tenant.email || '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm text-gray-500">Created</dt>
                                    <dd className="text-sm font-medium text-gray-900">{new Date(tenant.created_at).toLocaleString()}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm text-gray-500">Updated</dt>
                                    <dd className="text-sm font-medium text-gray-900">{new Date(tenant.updated_at).toLocaleString()}</dd>
                                </div>
                            </dl>
                            <div className="mt-4 flex gap-2">
                                <Link
                                    href={`/superadmin/tenants/${tenant.id}/edit`}
                                    className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                                >
                                    Edit Merchant
                                </Link>
                                <Link
                                    href="/superadmin/tenants"
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    Back to List
                                </Link>
                            </div>
                        </div>
                    </div>

                    {/* Users */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                Users ({tenant.users_count ?? 0})
                            </h3>
                            {users.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {users.map((user) => (
                                                <tr key={user.id} className="hover:bg-gray-50">
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{user.name}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{user.email}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            {user.roles?.[0]?.name || 'N/A'}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${user.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                            {user.status}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{new Date(user.created_at).toLocaleDateString()}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                        <button
                                                            onClick={() => {
                                                                if (window.confirm(`Log in as ${user.name}?`)) {
                                                                    router.post(`/superadmin/impersonate/${user.id}`, {}, {
                                                                        preserveScroll: true,
                                                                    });
                                                                }
                                                            }}
                                                            className="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700 transition-colors"
                                                        >
                                                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                            </svg>
                                                            Login As User
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <p className="text-sm text-gray-500">No users associated with this tenant.</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
