import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import Pagination from '@/Components/Pagination';
import { BackLink, PageHeader, PrimaryLink, StatusPill, TableCard, TableEmptyState, TD, TH } from '@/Components/Admin/StorefrontUI';

export default function CodRulesIndex({ codRules }) {
    const { can } = usePermission();
    const cc = getCurrencyConfig(usePage().props.platform_setting, usePage().props.website_info);

    function handleToggle(id) {
        router.post(adminUrl(`/admin/cod-rules/${id}/toggle`));
    }

    function handleDelete(id) {
        if (confirm('Delete this COD rule?')) {
            router.delete(adminUrl(`/admin/cod-rules/${id}`));
        }
    }

    function getEligibilitySummary(rule) {
        const parts = [];

        if (rule.min_order_amount !== null || rule.max_order_amount !== null) {
            let range = '';
            if (rule.min_order_amount !== null && rule.max_order_amount !== null) {
                range = `${formatCurrency(rule.min_order_amount, cc)} - ${formatCurrency(rule.max_order_amount, cc)}`;
            } else if (rule.min_order_amount !== null) {
                range = `Min: ${formatCurrency(rule.min_order_amount, cc)}`;
            } else {
                range = `Max: ${formatCurrency(rule.max_order_amount, cc)}`;
            }
            parts.push(`Amount: ${range}`);
        }

        if (rule.allowed_city_ids?.length > 0) {
            parts.push(`${rule.allowed_city_ids.length} cities allowed`);
        }

        if (rule.excluded_city_ids?.length > 0) {
            parts.push(`${rule.excluded_city_ids.length} cities excluded`);
        }

        return parts.length > 0 ? parts.join(' | ') : 'All orders eligible';
    }

    return (
        <AdminLayout>
            <Head title="COD Rules" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <BackLink href={adminUrl('/admin/storefront/checkout')}>Back to Checkout</BackLink>
                <PageHeader
                    eyebrow="Checkout"
                    title="COD Rules"
                    subtitle="Configure Cash on Delivery eligibility, fees, and city restrictions."
                    actions={can('cod-rules.create') && (
                        <PrimaryLink href={adminUrl('/admin/cod-rules/create')}>Add COD Rule</PrimaryLink>
                    )}
                />

                <TableCard>
                    <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead className="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <TH>Name</TH>
                                <TH>Eligibility</TH>
                                <TH>COD Fee</TH>
                                <TH align="center">Active</TH>
                                <TH align="right">Actions</TH>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                            {!codRules?.data?.length ? (
                                <TableEmptyState colSpan="5" title="No COD rules found." hint="Add your first COD rule to control cash-on-delivery availability." />
                            ) : codRules.data.map((rule) => (
                                <tr key={rule.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <TD variant="strong">{rule.name}</TD>
                                    <TD>
                                        <span className="text-xs">{getEligibilitySummary(rule)}</span>
                                    </TD>
                                    <TD variant="num">
                                        {formatCurrency(rule.cod_fee, cc)}
                                        {rule.apply_cod_fee_to_total && (
                                            <span className="ml-1 text-xs text-gray-500">(to total)</span>
                                        )}
                                    </TD>
                                    <td className="px-6 py-4 text-center">
                                        {can('cod-rules.update') ? (
                                            <button onClick={() => handleToggle(rule.id)} className="cursor-pointer">
                                                <StatusPill active={rule.is_active} />
                                            </button>
                                        ) : (
                                            <StatusPill active={rule.is_active} />
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-right text-sm">
                                        <div className="flex justify-end gap-2">
                                            {can('cod-rules.update') && (
                                                <Link href={adminUrl(`/admin/cod-rules/${rule.id}/edit`)} className="inline-block px-1 py-1 text-blue-600 hover:text-blue-800">Edit</Link>
                                            )}
                                            {can('cod-rules.delete') && (
                                                <button onClick={() => handleDelete(rule.id)} className="inline-block px-1 py-1 text-red-600 hover:text-red-800">Delete</button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    </div>
                    {codRules?.links && codRules.links.length > 3 && (
                        <Pagination meta={codRules} />
                    )}
                </TableCard>
            </div>
        </AdminLayout>
    );
}
