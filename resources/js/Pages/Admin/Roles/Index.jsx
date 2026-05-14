import { useState } from 'react';
import { Link, usePage, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function RolesIndex({ roles, filters }) {
    const { auth } = usePage().props;
    const userPermissions = auth?.user?.permissions || [];
    const canCreate = userPermissions.includes('roles.create');
    const canUpdate = userPermissions.includes('roles.update');
    const canDelete = userPermissions.includes('roles.delete');

    const [search, setSearch] = useState(filters?.search || '');

    function handleSearch(e) {
        e.preventDefault();
        router.get('/admin/roles', { search }, { preserveState: true, replace: true });
    }

    function confirmDelete(role) {
        const protectedRoles = ['superadmin', 'customer'];
        if (protectedRoles.includes(role.name)) {
            alert(`The "${role.name}" role is protected and cannot be deleted.`);
            return;
        }
        if (window.confirm(`Are you sure you want to delete "${role.name}"? This action cannot be undone.`)) {
            router.delete(`/admin/roles/${role.id}`);
        }
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Role Management</h2>}>
            <Head title="Role Management" />

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
                                        placeholder="Search roles..."
                                        className="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                    />
                                    <button
                                        type="submit"
                                        className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                                    >
                                        <i className="bi bi-search mr-1"></i> Search
                                    </button>
                                </form>
                                {canCreate && (
                                    <Link
                                        href="/admin/roles/create"
                                        className="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors flex items-center gap-1"
                                    >
                                        <i className="bi bi-plus-lg"></i> Create Role
                                    </Link>
                                )}
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permissions</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {roles.data.map((role) => (
                                            <tr key={role.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center">
                                                        <div className="w-9 h-9 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                            <i className="bi bi-shield-check text-blue-600"></i>
                                                        </div>
                                                        <div className="ml-3">
                                                            <div className="text-sm font-medium text-gray-900">{role.name}</div>
                                                            <div className="text-xs text-gray-500">{role.guard_name}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                        {role.permissions_count} permissions
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        {role.users_count} users
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{role.created_at}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Link href={`/admin/roles/${role.id}`} className="text-blue-600 hover:text-blue-900">
                                                            <i className="bi bi-eye"></i>
                                                        </Link>
                                                        {canUpdate && (
                                                            <Link href={`/admin/roles/${role.id}/edit`} className="text-indigo-600 hover:text-indigo-900">
                                                                <i className="bi bi-pencil"></i>
                                                            </Link>
                                                        )}
                                                        {canDelete && (
                                                            <button onClick={() => confirmDelete(role)} className="text-red-600 hover:text-red-900">
                                                                <i className="bi bi-trash"></i>
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                        {roles.data.length === 0 && (
                                            <tr>
                                                <td colSpan="5" className="px-6 py-12 text-center text-gray-500">
                                                    No roles found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {roles.links && (
                                <div className="mt-6">
                                    {roles.links.map((link, i) => (
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
