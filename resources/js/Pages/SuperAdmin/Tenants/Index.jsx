import { useState } from 'react';
import { Link, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function TenantsIndex({ tenants, filters }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');

    function handleSearch(e) {
        e.preventDefault();
        router.get('/superadmin/tenants', {
            search,
            status: statusFilter,
        }, { preserveState: true, replace: true });
    }

    function handleFilter(value) {
        setStatusFilter(value);
        router.get('/superadmin/tenants', {
            search,
            status: value,
        }, { preserveState: true, replace: true });
    }

    function confirmDelete(tenant) {
        if (tenant.slug === 'default') {
            alert('The default tenant cannot be deleted.');
            return;
        }
        if (window.confirm(`Delete tenant "${tenant.name}"? All associated data will be orphaned.`)) {
            router.delete(`/superadmin/tenants/${tenant.id}`);
        }
    }

    function toggleStatus(tenant) {
        const action = tenant.status === 'active' ? 'suspend' : 'activate';
        if (window.confirm(`${action} tenant "${tenant.name}"?`)) {
            router.post(`/superadmin/tenants/${tenant.id}/toggle-status`);
        }
    }

    const statusColors = {
        active: 'bg-green-100 text-green-800',
        suspended: 'bg-yellow-100 text-yellow-800',
        trialing: 'bg-blue-100 text-blue-800',
    };

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Merchant Management</h2>}>
            <Head title="Merchant Management" />

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
                                        placeholder="Search merchants by name, slug, or domain..."
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
                                    href="/superadmin/tenants/create"
                                    className="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors flex items-center gap-1"
                                >
                                    Create Merchant
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
                                    <option value="suspended">Suspended</option>
                                    <option value="trialing">Trialing</option>
                                </select>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Merchant</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug / Domain</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Admins</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {tenants.data.map((tenant) => (
                                            <tr key={tenant.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-medium text-gray-900">{tenant.name}</div>
                                                    <div className="text-sm text-gray-500">{tenant.email || '—'}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <div>{tenant.slug}</div>
                                                    {tenant.domain && <div className="text-xs text-gray-400">{tenant.domain}</div>}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColors[tenant.status] || 'bg-gray-100 text-gray-800'}`}>
                                                        {tenant.status}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">{tenant.users_count ?? 0}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{new Date(tenant.created_at).toLocaleDateString()}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Link href={`/superadmin/tenants/${tenant.id}`} className="text-blue-600 hover:text-blue-900">
                                                            View
                                                        </Link>
                                                        <Link href={`/superadmin/tenants/${tenant.id}/edit`} className="text-indigo-600 hover:text-indigo-900">
                                                            Edit
                                                        </Link>
                                                        <button onClick={() => toggleStatus(tenant)} className={`${tenant.status === 'active' ? 'text-yellow-600 hover:text-yellow-900' : 'text-green-600 hover:text-green-900'}`}>
                                                            {tenant.status === 'active' ? 'Suspend' : 'Activate'}
                                                        </button>
                                                        {tenant.slug !== 'default' && (
                                                            <button onClick={() => confirmDelete(tenant)} className="text-red-600 hover:text-red-900">
                                                                Delete
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                        {tenants.data.length === 0 && (
                                            <tr>
                                                <td colSpan="6" className="px-6 py-12 text-center text-gray-500">
                                                    No merchants found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {tenants.links && (
                                <div className="mt-6">
                                    {tenants.links.map((link, i) => (
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
