import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';

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
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <Link href={adminUrl('/admin/storefront/checkout')} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-4 transition-colors">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
                    Back to Checkout
                </Link>
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">COD Rules</h1>
                    {can('cod-rules.create') && (
                        <Link href={adminUrl('/admin/cod-rules/create')} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                            Add COD Rule
                        </Link>
                    )}
                </div>

                <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead className="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Eligibility</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">COD Fee</th>
                                <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Active</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                            {!codRules?.data?.length ? (
                                <tr><td colSpan="5" className="px-6 py-12 text-center text-gray-500 dark:text-gray-400">No COD rules found.</td></tr>
                            ) : codRules.data.map((rule) => (
                                <tr key={rule.id} className="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td className="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{rule.name}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                        <span className="text-xs">{getEligibilitySummary(rule)}</span>
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                        {formatCurrency(rule.cod_fee, cc)}
                                        {rule.apply_cod_fee_to_total && (
                                            <span className="ml-1 text-xs text-gray-500">(to total)</span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-center">
                                        {can('cod-rules.update') ? (
                                            <button onClick={() => handleToggle(rule.id)}
                                                className={`px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer ${rule.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                                {rule.is_active ? 'Active' : 'Inactive'}
                                            </button>
                                        ) : (
                                            <span className={`px-2.5 py-0.5 rounded-full text-xs font-medium ${rule.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                                {rule.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-right text-sm">
                                        <div className="flex justify-end gap-2">
                                            {can('cod-rules.update') && (
                                                <Link href={adminUrl(`/admin/cod-rules/${rule.id}/edit`)} className="text-blue-600 hover:text-blue-800">Edit</Link>
                                            )}
                                            {can('cod-rules.delete') && (
                                                <button onClick={() => handleDelete(rule.id)} className="text-red-600 hover:text-red-800">Delete</button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {codRules?.links && codRules.links.length > 3 && (
                    <div className="mt-4 flex items-center justify-between">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Showing {codRules.from} to {codRules.to} of {codRules.total} results</p>
                        <div className="flex gap-1">
                            {codRules.links.map((link, i) => (
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
