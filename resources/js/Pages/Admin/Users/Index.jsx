import { useState } from 'react';
import { Link, usePage, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import PerPageSelect from '@/Components/PerPageSelect';
import { usePermission } from '@/Hooks/usePermission';

export default function UsersIndex({ users, filters, roles, showPagination = true, warning = null }) {
    const { can, isOwner, isSuperAdmin } = usePermission();
    const [search, setSearch] = useState(filters?.search || '');
    const [roleFilter, setRoleFilter] = useState(filters?.role || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');

    const filteredRoles = ['', ...roles];
    const filteredStatuses = ['', 'active', 'suspended', 'banned'];

    function handleSearch(e) {
        e.preventDefault();
        router.get(adminUrl('/admin/users'), {
            search,
            role: roleFilter,
            status: statusFilter,
        }, { preserveState: true, replace: true });
    }

    function handleFilterChange(type, value) {
        const params = { search, role: roleFilter, status: statusFilter, [type]: value };
        if (type === 'role') setRoleFilter(value);
        if (type === 'status') setStatusFilter(value);
        router.get(adminUrl('/admin/users'), params, { preserveState: true, replace: true });
    }

    function confirmDelete(user) {
        if (window.confirm(`Are you sure you want to delete "${user.name}"? This action cannot be undone.`)) {
            router.delete(adminUrl(`/admin/users/${user.id}`));
        }
    }

    function handleSuspend(user) {
        if (window.confirm(`Suspend "${user.name}"? They will not be able to log in.`)) {
            router.post(adminUrl(`/admin/users/${user.id}/suspend`));
        }
    }

    function handleBan(user) {
        if (window.confirm(`Ban "${user.name}"? They will not be able to log in.`)) {
            router.post(adminUrl(`/admin/users/${user.id}/ban`));
        }
    }

    function handleActivate(user) {
        router.post(adminUrl(`/admin/users/${user.id}/activate`));
    }

    const statusBadge = (status) => {
        const colors = {
            active: 'bg-green-100 text-green-800',
            suspended: 'bg-yellow-100 text-yellow-800',
            banned: 'bg-red-100 text-red-800',
        };
        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colors[status] || 'bg-gray-100 text-gray-800'}`}>
                {status}
            </span>
        );
    };

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">User Management</h2>}>
            <Head title="User Management" />

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
                                        placeholder="Search users..."
                                        className="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                    />
                                    <button
                                        type="submit"
                                        className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                                    >
                                        <i className="bi bi-search mr-1"></i> Search
                                    </button>
                                </form>
                                {can('users.create') && (
                                    <Link
                                        href={adminUrl('/admin/users/create')}
                                        className="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors flex items-center gap-1"
                                    >
                                        <i className="bi bi-plus-lg"></i> Create User
                                    </Link>
                                )}
                            </div>

                            <div className="flex gap-4 mb-6">
                                <select
                                    value={roleFilter}
                                    onChange={(e) => handleFilterChange('role', e.target.value)}
                                    className="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                >
                                    {filteredRoles.map((r) => (
                                        <option key={r} value={r}>{r || 'All Roles'}</option>
                                    ))}
                                </select>
                                <select
                                    value={statusFilter}
                                    onChange={(e) => handleFilterChange('status', e.target.value)}
                                    className="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                >
                                    {filteredStatuses.map((s) => (
                                        <option key={s} value={s}>{s || 'All Statuses'}</option>
                                    ))}
                                </select>
                            </div>

                            {/* Per Page Selector */}
                            <div className="flex justify-between items-center mb-4">
                                <PerPageSelect />
                                {warning && (
                                    <p className="text-sm text-amber-600">{warning}</p>
                                )}
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {users?.data?.map((user) => (
                                            <tr key={user.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center">
                                                        <div className="flex-shrink-0 h-10 w-10">
                                                            {user.profile_image_url ? (
                                                                <img className="h-10 w-10 rounded-full object-cover" src={user.profile_image_url} alt="" />
                                                            ) : (
                                                                <div className="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                                    <span className="text-sm font-medium text-blue-600">{user.name.charAt(0).toUpperCase()}</span>
                                                                </div>
                                                            )}
                                                        </div>
                                                        <div className="ml-4">
                                                            <div className="text-sm font-medium text-gray-900">{user.name}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{user.email}</td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center gap-1.5">
                                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            {user.role_name || '—'}
                                                        </span>
                                                        {user.is_owner && (
                                                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                                Owner
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">{statusBadge(user.status)}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{new Date(user.created_at).toLocaleDateString()}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <div className="flex items-center justify-end gap-2">
                                                        {can('users.view') && (
                                                            <Link href={adminUrl(`/admin/users/${user.id}`)} className="text-blue-600 hover:text-blue-900">
                                                                <i className="bi bi-eye"></i>
                                                            </Link>
                                                        )}
                                                        {can('users.update') && (
                                                            <Link href={adminUrl(`/admin/users/${user.id}/edit`)} className="text-indigo-600 hover:text-indigo-900">
                                                                <i className="bi bi-pencil"></i>
                                                            </Link>
                                                        )}
                                                        {can('users.suspend') && (isSuperAdmin || !user.is_owner) && user.status === 'active' && (
                                                            <button onClick={() => handleSuspend(user)} className="text-yellow-600 hover:text-yellow-900">
                                                                <i className="bi bi-pause-circle"></i>
                                                            </button>
                                                        )}
                                                        {can('users.ban') && (isSuperAdmin || !user.is_owner) && user.status === 'active' && (
                                                            <button onClick={() => handleBan(user)} className="text-red-600 hover:text-red-900">
                                                                <i className="bi bi-slash-circle"></i>
                                                            </button>
                                                        )}
                                                        {can('users.activate') && user.status !== 'active' && (
                                                            <button onClick={() => handleActivate(user)} className="text-green-600 hover:text-green-900">
                                                                <i className="bi bi-check-circle"></i>
                                                            </button>
                                                        )}
                                                        {can('users.delete') && (isSuperAdmin || !user.is_owner) && (
                                                            <button onClick={() => confirmDelete(user)} className="text-red-600 hover:text-red-900">
                                                                <i className="bi bi-trash"></i>
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                        {(!users?.data || users.data.length === 0) && (
                                            <tr>
                                                <td colSpan="6" className="px-6 py-12 text-center text-gray-500">
                                                    No users found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {users?.links && showPagination && (
                                <div className="mt-6">
                                    {users.links.map((link, i) => (
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
