import { Link, Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { assetUrl } from '@/Utils/helpers';

export default function UsersShow({ user, activities }) {
    const { props } = usePage();
    const isSuperAdmin = props?.auth?.user?.is_superadmin ?? false;

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

    const ownerBadge = user.is_owner
        ? <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Owner</span>
        : null;

    function confirmDelete() {
        if (window.confirm(`Are you sure you want to delete "${user.name}"? This action cannot be undone.`)) {
            router.delete(`/admin/users/${user.id}`);
        }
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">User Details</h2>}>
            <Head title={`User: ${user.name}`} />

            <div className="py-6">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="flex items-start justify-between">
                                <div className="flex items-center gap-4">
                                    <div className="flex-shrink-0 h-16 w-16">
                                        {user.profile_image ? (
                                            <img className="h-16 w-16 rounded-full object-cover" src={assetUrl(user.profile_image)} alt="" />
                                        ) : (
                                            <div className="h-16 w-16 rounded-full bg-blue-100 flex items-center justify-center">
                                                <span className="text-2xl font-medium text-blue-600">{user.name.charAt(0).toUpperCase()}</span>
                                            </div>
                                        )}
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-medium text-gray-900">{user.name}</h3>
                                        <p className="text-sm text-gray-500">{user.email}</p>
                                        <div className="flex items-center gap-2 mt-1">
                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {user.roles?.[0]?.name || 'N/A'}
                                            </span>
                                            {ownerBadge}
                                            {statusBadge(user.status)}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Link href={`/admin/users/${user.id}/edit`} className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                                        <i className="bi bi-pencil mr-1"></i> Edit
                                    </Link>
                                    {(isSuperAdmin || !user.is_owner) && (
                                        <button onClick={confirmDelete} className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">
                                            <i className="bi bi-trash mr-1"></i> Delete
                                        </button>
                                    )}
                                </div>
                            </div>

                            <div className="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <span className="text-gray-500">ID:</span>
                                    <span className="ml-2 text-gray-900 font-medium">#{user.id}</span>
                                </div>
                                <div>
                                    <span className="text-gray-500">Joined:</span>
                                    <span className="ml-2 text-gray-900">{new Date(user.created_at).toLocaleDateString()}</span>
                                </div>
                                <div>
                                    <span className="text-gray-500">Email Verified:</span>
                                    <span className="ml-2 text-gray-900">{user.email_verified_at ? new Date(user.email_verified_at).toLocaleDateString() : 'No'}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Recent Activity</h3>
                            {activities.length === 0 ? (
                                <p className="text-sm text-gray-500">No activity recorded yet.</p>
                            ) : (
                                <div className="space-y-3">
                                    {activities.map((activity) => (
                                        <div key={activity.id} className="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                                            <div className="flex-shrink-0 mt-0.5">
                                                <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm ${
                                                    activity.event === 'created' ? 'bg-green-100 text-green-600' :
                                                    activity.event === 'updated' ? 'bg-blue-100 text-blue-600' :
                                                    activity.event === 'deleted' ? 'bg-red-100 text-red-600' :
                                                    activity.event === 'suspended' ? 'bg-yellow-100 text-yellow-600' :
                                                    activity.event === 'banned' ? 'bg-red-100 text-red-600' :
                                                    activity.event === 'activated' ? 'bg-green-100 text-green-600' :
                                                    'bg-gray-100 text-gray-600'
                                                }`}>
                                                    <i className={`bi ${
                                                        activity.event === 'created' ? 'bi-plus-lg' :
                                                        activity.event === 'updated' ? 'bi-pencil' :
                                                        activity.event === 'deleted' ? 'bi-trash' :
                                                        activity.event === 'suspended' ? 'bi-pause-circle' :
                                                        activity.event === 'banned' ? 'bi-slash-circle' :
                                                        activity.event === 'activated' ? 'bi-check-circle' :
                                                        'bi-info-circle'
                                                    }`}></i>
                                                </div>
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm text-gray-900">{activity.description}</p>
                                                <p className="text-xs text-gray-500 mt-1">{new Date(activity.created_at).toLocaleString()}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="flex justify-start">
                        <Link href="/admin/users" className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            <i className="bi bi-arrow-left mr-1"></i> Back to Users
                        </Link>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
