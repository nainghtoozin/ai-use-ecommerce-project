import { Link, Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function RolesShow({ role, grouped_permissions }) {
    function confirmDelete() {
        if (window.confirm(`Are you sure you want to delete "${role.name}"? This action cannot be undone.`)) {
            router.delete(`/admin/roles/${role.id}`);
        }
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Role Details</h2>}>
            <Head title={`Role: ${role.name}`} />

            <div className="py-6">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="flex items-start justify-between">
                                <div className="flex items-center gap-4">
                                    <div className="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center">
                                        <i className="bi bi-shield-check text-2xl text-blue-600"></i>
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-medium text-gray-900">{role.name}</h3>
                                        <p className="text-sm text-gray-500">Guard: {role.guard_name}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Link href={`/admin/roles/${role.id}/edit`} className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                                        <i className="bi bi-pencil mr-1"></i> Edit
                                    </Link>
                                    <button onClick={confirmDelete} className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">
                                        <i className="bi bi-trash mr-1"></i> Delete
                                    </button>
                                </div>
                            </div>

                            <div className="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div className="p-4 bg-blue-50 rounded-lg">
                                    <p className="text-blue-600 font-medium">{role.permissions_count}</p>
                                    <p className="text-gray-500">Permissions</p>
                                </div>
                                <div className="p-4 bg-green-50 rounded-lg">
                                    <p className="text-green-600 font-medium">{role.users_count}</p>
                                    <p className="text-gray-500">Assigned Users</p>
                                </div>
                                <div className="p-4 bg-gray-50 rounded-lg">
                                    <p className="text-gray-600 font-medium">{new Date(role.created_at).toLocaleDateString()}</p>
                                    <p className="text-gray-500">Created</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                Assigned Permissions ({role.permissions_count})
                            </h3>
                            {grouped_permissions.length === 0 ? (
                                <p className="text-sm text-gray-500">No permissions assigned to this role.</p>
                            ) : (
                                <div className="space-y-4">
                                    {grouped_permissions.map((group) => (
                                        <div key={group.group} className="border border-gray-200 rounded-lg overflow-hidden">
                                            <div className="bg-gray-50 px-4 py-3">
                                                <h4 className="text-sm font-medium text-gray-700">{group.label}</h4>
                                            </div>
                                            <div className="px-4 py-3">
                                                <div className="flex flex-wrap gap-2">
                                                    {group.permissions.map((permission) => (
                                                        <span
                                                            key={permission}
                                                            className="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-700"
                                                        >
                                                            {permission}
                                                        </span>
                                                    ))}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="flex justify-start">
                        <Link href="/admin/roles" className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            <i className="bi bi-arrow-left mr-1"></i> Back to Roles
                        </Link>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
