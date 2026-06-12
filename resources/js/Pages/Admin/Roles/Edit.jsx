import { useState } from 'react';
import { useForm, Link, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

export default function RolesEdit({ role, permission_groups }) {
    const { data, setData, put, processing, errors } = useForm({
        name: role.name,
        permissions: role.permissions || [],
    });

    function handleSubmit(e) {
        e.preventDefault();
        put(adminUrl(`/admin/roles/${role.id}`), {
            onSuccess: () => {},
        });
    }

    function togglePermission(permissionName) {
        setData('permissions', data.permissions.includes(permissionName)
            ? data.permissions.filter((p) => p !== permissionName)
            : [...data.permissions, permissionName]
        );
    }

    function toggleGroup(groupName, permissionNames) {
        const groupPermissions = permissionNames;
        const allSelected = groupPermissions.every((p) => data.permissions.includes(p));
        if (allSelected) {
            setData('permissions', data.permissions.filter((p) => !groupPermissions.includes(p)));
        } else {
            const newPermissions = new Set([...data.permissions, ...groupPermissions]);
            setData('permissions', Array.from(newPermissions));
        }
    }

    function toggleSelectAll() {
        const allPermissionNames = permission_groups.flatMap((g) => g.items.map((p) => p.name));
        if (data.permissions.length === allPermissionNames.length) {
            setData('permissions', []);
        } else {
            setData('permissions', allPermissionNames);
        }
    }

    const allPermissionNames = permission_groups.flatMap((g) => g.items.map((p) => p.name));
    const allSelected = allPermissionNames.length > 0 && data.permissions.length === allPermissionNames.length;

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit Role: {role.name}</h2>}>
            <Head title={`Edit Role: ${role.name}`} />

            <div className="py-6">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Role Name</label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="e.g. editor, moderator"
                                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        required
                                    />
                                    {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                                </div>

                                <div>
                                    <div className="flex items-center justify-between mb-4">
                                        <label className="block text-sm font-medium text-gray-700">Permissions</label>
                                        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={allSelected}
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            />
                                            Select All
                                        </label>
                                    </div>
                                    {errors.permissions && <p className="mb-2 text-sm text-red-600">{errors.permissions}</p>}

                                    <div className="space-y-4">
                                        {permission_groups.map((group) => {
                                            const groupPermissionNames = group.items.map((p) => p.name);
                                            const groupAllSelected = groupPermissionNames.every((p) => data.permissions.includes(p));
                                            const groupSomeSelected = groupPermissionNames.some((p) => data.permissions.includes(p));

                                            return (
                                                <div key={group.group} className="border border-gray-200 rounded-lg overflow-hidden">
                                                    <div className="bg-gray-50 px-4 py-3 flex items-center justify-between">
                                                        <label className="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                                                            <input
                                                                type="checkbox"
                                                                checked={groupAllSelected}
                                                                ref={(el) => { if (el) el.indeterminate = groupSomeSelected && !groupAllSelected; }}
                                                                onChange={() => toggleGroup(group.group, groupPermissionNames)}
                                                                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                            />
                                                            {group.label}
                                                            <span className="text-xs text-gray-400">({group.items.length})</span>
                                                        </label>
                                                    </div>
                                                    <div className="px-4 py-3 grid grid-cols-2 md:grid-cols-3 gap-2">
                                                        {group.items.map((permission) => (
                                                            <label key={permission.id} className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer hover:text-gray-900">
                                                                <input
                                                                    type="checkbox"
                                                                    checked={data.permissions.includes(permission.name)}
                                                                    onChange={() => togglePermission(permission.name)}
                                                                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                                />
                                                                {permission.name.split('.').pop()}
                                                            </label>
                                                        ))}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                <div className="flex items-center justify-end gap-4">
                                    <Link href={adminUrl('/admin/roles')} className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                        Cancel
                                    </Link>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="px-6 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
                                    >
                                        {processing ? 'Saving...' : 'Update Role'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
