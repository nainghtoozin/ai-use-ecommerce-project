import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';

export default function PackagingOptionsIndex({ packagingOptions }) {
    const { can } = usePermission();

    function handleToggle(id) {
        router.post(adminUrl(`/admin/packaging-options/${id}/toggle`));
    }

    function handleDelete(id) {
        if (confirm('Delete this packaging option?')) {
            router.delete(adminUrl(`/admin/packaging-options/${id}`));
        }
    }

    return (
        <AdminLayout>
            <Head title="Packaging Options" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <Link href={adminUrl('/admin/storefront/checkout')} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-4 transition-colors">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
                    Back to Checkout
                </Link>
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Packaging Options</h1>
                    {can('packaging-options.create') && (
                        <Link href={adminUrl('/admin/packaging-options/create')} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                            Add Packaging Option
                        </Link>
                    )}
                </div>

                <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead className="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Code</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fee</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Sort Order</th>
                                <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Active</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                            {!packagingOptions?.data?.length ? (
                                <tr><td colSpan="7" className="px-6 py-12 text-center text-gray-500 dark:text-gray-400">No packaging options found.</td></tr>
                            ) : packagingOptions.data.map((option, index) => (
                                <tr key={option.id} className="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{index + 1}</td>
                                    <td className="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{option.name}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{option.code}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{option.fee}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{option.sort_order}</td>
                                    <td className="px-6 py-4 text-center">
                                        {can('packaging-options.update') ? (
                                            <button onClick={() => handleToggle(option.id)}
                                                className={`px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer ${option.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                                {option.is_active ? 'Active' : 'Inactive'}
                                            </button>
                                        ) : (
                                            <span className={`px-2.5 py-0.5 rounded-full text-xs font-medium ${option.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                                {option.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-right text-sm">
                                        <div className="flex justify-end gap-2">
                                            {can('packaging-options.update') && (
                                                <Link href={adminUrl(`/admin/packaging-options/${option.id}/edit`)} className="text-blue-600 hover:text-blue-800">Edit</Link>
                                            )}
                                            {can('packaging-options.delete') && (
                                                <button onClick={() => handleDelete(option.id)} className="text-red-600 hover:text-red-800">Delete</button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {packagingOptions?.links && packagingOptions.links.length > 3 && (
                    <div className="mt-4 flex items-center justify-between">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Showing {packagingOptions.from} to {packagingOptions.to} of {packagingOptions.total} results</p>
                        <div className="flex gap-1">
                            {packagingOptions.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'}
                                    className={`px-3 py-1 text-sm rounded-md ${link.active ? 'bg-blue-600 text-white' : link.url ? 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:bg-gray-800' : 'text-gray-400 cursor-not-allowed'}`}>
                                    {link.label.replace('&laquo;', '\u00ab').replace('&raquo;', '\u00bb')}
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
