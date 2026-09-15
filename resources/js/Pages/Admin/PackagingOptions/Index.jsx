import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';
import Pagination from '@/Components/Pagination';
import { BackLink, PageHeader, PrimaryLink, StatusPill, TableCard, TableEmptyState, TD, TH } from '@/Components/Admin/StorefrontUI';

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
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <BackLink href={adminUrl('/admin/storefront/checkout')}>Back to Checkout</BackLink>
                <PageHeader
                    eyebrow="Checkout"
                    title="Packaging Options"
                    subtitle="Manage packaging types and fees offered during checkout."
                    actions={can('packaging-options.create') && (
                        <PrimaryLink href={adminUrl('/admin/packaging-options/create')}>Add Packaging Option</PrimaryLink>
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
                                <TH>Fee</TH>
                                <TH>Sort Order</TH>
                                <TH align="center">Active</TH>
                                <TH align="right">Actions</TH>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                            {!packagingOptions?.data?.length ? (
                                <TableEmptyState colSpan="7" title="No packaging options found." hint="Add your first packaging option to offer it at checkout." />
                            ) : packagingOptions.data.map((option, index) => (
                                <tr key={option.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <TD variant="muted">{index + 1}</TD>
                                    <TD variant="strong">{option.name}</TD>
                                    <TD><span className="font-mono text-xs">{option.code}</span></TD>
                                    <TD variant="num">{option.fee}</TD>
                                    <TD variant="num">{option.sort_order}</TD>
                                    <td className="px-6 py-4 text-center">
                                        {can('packaging-options.update') ? (
                                            <button onClick={() => handleToggle(option.id)} className="cursor-pointer">
                                                <StatusPill active={option.is_active} />
                                            </button>
                                        ) : (
                                            <StatusPill active={option.is_active} />
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-right text-sm">
                                        <div className="flex justify-end gap-2">
                                            {can('packaging-options.update') && (
                                                <Link href={adminUrl(`/admin/packaging-options/${option.id}/edit`)} className="inline-block px-1 py-1 text-blue-600 hover:text-blue-800">Edit</Link>
                                            )}
                                            {can('packaging-options.delete') && (
                                                <button onClick={() => handleDelete(option.id)} className="inline-block px-1 py-1 text-red-600 hover:text-red-800">Delete</button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    </div>
                    {packagingOptions?.links && packagingOptions.links.length > 3 && (
                        <Pagination meta={packagingOptions} />
                    )}
                </TableCard>
            </div>
        </AdminLayout>
    );
}
