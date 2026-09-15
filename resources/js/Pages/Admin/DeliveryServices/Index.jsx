import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';
import Pagination from '@/Components/Pagination';
import { BackLink, PageHeader, PrimaryLink, StatusPill, TableCard, TableEmptyState, TD, TH } from '@/Components/Admin/StorefrontUI';

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
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <BackLink href={adminUrl('/admin/storefront/checkout')}>Back to Checkout</BackLink>
                <PageHeader
                    eyebrow="Checkout"
                    title="Delivery Services"
                    subtitle="Manage delivery methods, fees, and city-specific pricing."
                    actions={can('delivery-services.create') && (
                        <PrimaryLink href={adminUrl('/admin/delivery-services/create')}>Add Delivery Service</PrimaryLink>
                    )}
                />

                <TableCard>
                    <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead className="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <TH>#</TH>
                                <TH>Name</TH>
                                <TH>Code</TH>
                                <TH>Base Fee</TH>
                                <TH>Fee/Kg</TH>
                                <TH>Days</TH>
                                <TH align="center">Active</TH>
                                <TH align="right">Actions</TH>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                            {!deliveryServices?.data?.length ? (
                                <TableEmptyState colSpan="8" title="No delivery services found." hint="Add your first delivery service to offer it at checkout." />
                            ) : deliveryServices.data.map((service, index) => (
                                <tr key={service.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <TD variant="muted">{index + 1}</TD>
                                    <TD variant="strong">{service.name}</TD>
                                    <TD><span className="font-mono text-xs">{service.code}</span></TD>
                                    <TD variant="num">{service.base_fee}</TD>
                                    <TD variant="num">{service.fee_per_kg ?? '-'}</TD>
                                    <TD variant="num">{service.min_days}-{service.max_days} days</TD>
                                    <td className="px-6 py-4 text-center">
                                        {can('delivery-services.update') ? (
                                            <button onClick={() => handleToggle(service.id)} className="cursor-pointer">
                                                <StatusPill active={service.is_active} />
                                            </button>
                                        ) : (
                                            <StatusPill active={service.is_active} />
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-right text-sm">
                                        <div className="flex justify-end gap-2">
                                            {can('delivery-services.update') && (
                                                <Link href={adminUrl(`/admin/delivery-services/${service.id}/edit`)} className="inline-block px-1 py-1 text-blue-600 hover:text-blue-800">Edit</Link>
                                            )}
                                            {can('delivery-services.delete') && (
                                                <button onClick={() => handleDelete(service.id)} className="inline-block px-1 py-1 text-red-600 hover:text-red-800">Delete</button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    </div>
                    {deliveryServices?.links && deliveryServices.links.length > 3 && (
                        <Pagination meta={deliveryServices} />
                    )}
                </TableCard>
            </div>
        </AdminLayout>
    );
}
