import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';

export default function DeliveryServicesIndex({ deliveryServices, cities }) {
    const { can } = usePermission();

    function handleToggle(id) {
        router.post(adminUrl(`/admin/delivery-services/${id}/toggle`));
    }

    function handleDelete(id) {
        if (confirm('Delete this delivery service?')) {
            router.delete(adminUrl(`/admin/delivery-services/${id}`));
        }
    }

    return (
        <AdminLayout>
            <Head title="Delivery Services" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Delivery Services</h1>
                    {can('delivery-services.create') && (
                        <Link href={adminUrl('/admin/delivery-services/create')} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                            Add Delivery Service
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
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Base Fee</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fee/Kg</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Days</th>
                                <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Active</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                            {!deliveryServices?.data?.length ? (
                                <tr><td colSpan="8" className="px-6 py-12 text-center text-gray-500 dark:text-gray-400">No delivery services found.</td></tr>
                            ) : deliveryServices.data.map((service, index) => (
                                <tr key={service.id} className="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{index + 1}</td>
                                    <td className="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{service.name}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{service.code}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{service.base_fee}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{service.fee_per_kg ?? '-'}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{service.min_days}-{service.max_days} days</td>
                                    <td className="px-6 py-4 text-center">
                                        {can('delivery-services.update') ? (
                                            <button onClick={() => handleToggle(service.id)}
                                                className={`px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer ${service.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                                {service.is_active ? 'Active' : 'Inactive'}
                                            </button>
                                        ) : (
                                            <span className={`px-2.5 py-0.5 rounded-full text-xs font-medium ${service.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                                {service.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-right text-sm">
                                        <div className="flex justify-end gap-2">
                                            {can('delivery-services.update') && (
                                                <Link href={adminUrl(`/admin/delivery-services/${service.id}/edit`)} className="text-blue-600 hover:text-blue-800">Edit</Link>
                                            )}
                                            {can('delivery-services.delete') && (
                                                <button onClick={() => handleDelete(service.id)} className="text-red-600 hover:text-red-800">Delete</button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {deliveryServices?.links && deliveryServices.links.length > 3 && (
                    <div className="mt-4 flex items-center justify-between">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Showing {deliveryServices.from} to {deliveryServices.to} of {deliveryServices.total} results</p>
                        <div className="flex gap-1">
                            {deliveryServices.links.map((link, i) => (
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
