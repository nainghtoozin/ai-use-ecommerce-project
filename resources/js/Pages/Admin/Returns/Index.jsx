import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import Pagination from '@/Components/Pagination';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import {
    BackLink,
    Card,
    EmptyState,
    FilterBar,
    PageShell,
    StatCard,
    StatusBadge,
    formatDate,
} from '@/Components/Admin/AfterSalesUI';

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'requested', label: 'Requested' },
    { value: 'awaiting_shipment', label: 'Awaiting shipment' },
    { value: 'in_transit', label: 'In transit' },
    { value: 'received', label: 'Received' },
    { value: 'inspection', label: 'Inspection' },
    { value: 'completed', label: 'Completed' },
    { value: 'rejected', label: 'Rejected' },
];

export default function AdminReturnsIndex({ returns, filters = {}, counts = {} }) {
    const { platform_setting, website_info } = usePage().props;
    const cc = getCurrencyConfig(platform_setting, website_info);

    const rows = returns?.data || [];

    function applyFilters(next) {
        router.get(adminUrl('/admin/returns'), next, { preserveState: true, preserveScroll: true, replace: true });
    }

    const money = (value) => formatCurrency(value, cc);

    return (
        <AdminLayout>
            <Head title="Return Requests" />
            <PageShell
                title="Return Requests"
                subtitle="Approve, receive, inspect and complete product returns"
                actions={<BackLink href={adminUrl('/admin/orders')} label="Orders" />}
            >
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard label="Awaiting review" value={counts.requested ?? 0} tone="amber" />
                    <StatCard label="Awaiting shipment" value={counts.awaiting_shipment ?? 0} tone="blue" />
                    <StatCard label="Received / inspection" value={counts.received ?? 0} tone="amber" />
                    <StatCard label="Completed" value={counts.completed ?? 0} tone="emerald" />
                </div>

                <Card className="mt-4">
                    <FilterBar
                        statuses={STATUS_TABS.map((tab) => ({
                            ...tab,
                            count: ['requested', 'awaiting_shipment', 'received', 'completed'].includes(tab.value)
                                ? counts[tab.value]
                                : undefined,
                        }))}
                        filters={filters}
                        onChange={applyFilters}
                        searchPlaceholder="Search RMA, order or customer…"
                    />

                    {rows.length === 0 ? (
                        <EmptyState
                            title="No return requests"
                            description={filters.status ? 'No returns match this filter.' : 'Customer return requests will appear here.'}
                        />
                    ) : (
                        <>
                            <div className="hidden overflow-x-auto sm:block">
                                <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                                    <thead className="bg-gray-50 dark:bg-gray-800/50">
                                        <tr className="text-left text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            <th className="px-4 py-3 font-semibold">RMA</th>
                                            <th className="px-4 py-3 font-semibold">Order</th>
                                            <th className="px-4 py-3 font-semibold">Items</th>
                                            <th className="px-4 py-3 font-semibold">Resolution</th>
                                            <th className="px-4 py-3 font-semibold">Refund</th>
                                            <th className="px-4 py-3 font-semibold">Requested</th>
                                            <th className="px-4 py-3 font-semibold text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                        {rows.map((item) => (
                                            <tr key={item.id} className="hover:bg-gray-50/70 dark:hover:bg-gray-800/40">
                                                <td className="px-4 py-3">
                                                    <Link href={adminUrl(`/admin/returns/${item.id}`)} className="font-mono text-xs text-blue-600 hover:underline dark:text-blue-400">
                                                        {item.rma_number}
                                                    </Link>
                                                    <div className="mt-1"><StatusBadge status={item.status} /></div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <p className="font-mono text-xs text-gray-800 dark:text-gray-100">#{item.order?.invoice_number || item.order_id}</p>
                                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                                        {item.order ? `${item.order.first_name} ${item.order.last_name}` : '—'}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3 text-xs text-gray-700 dark:text-gray-200">
                                                    {(item.items || []).reduce((sum, line) => sum + Number(line.quantity || 0), 0)} unit(s)
                                                    <p className="text-[11px] text-gray-400">{(item.items || []).length} line(s)</p>
                                                </td>
                                                <td className="px-4 py-3 text-xs uppercase text-gray-600 dark:text-gray-300">{item.resolution}</td>
                                                <td className="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                                                    {item.refund ? (
                                                        <>
                                                            <span className="font-medium text-purple-600 dark:text-purple-300">{money(item.refund.amount)}</span>
                                                            <p className="text-[11px] text-gray-500">{item.refund.status}</p>
                                                        </>
                                                    ) : '—'}
                                                </td>
                                                <td className="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{formatDate(item.requested_at || item.created_at)}</td>
                                                <td className="px-4 py-3 text-right">
                                                    <Link href={adminUrl(`/admin/returns/${item.id}`)} className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">
                                                        Manage
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="divide-y divide-gray-100 dark:divide-gray-800 sm:hidden">
                                {rows.map((item) => (
                                    <Link key={item.id} href={adminUrl(`/admin/returns/${item.id}`)} className="block p-4 hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-mono text-xs text-blue-600 dark:text-blue-400">{item.rma_number}</p>
                                                <p className="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                    #{item.order?.invoice_number || item.order_id}
                                                </p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                    {item.order ? `${item.order.first_name} ${item.order.last_name}` : '—'}
                                                </p>
                                            </div>
                                            <StatusBadge status={item.status} />
                                        </div>
                                        <dl className="mt-3 space-y-1.5 text-xs">
                                            <div className="flex justify-between gap-3">
                                                <dt className="text-gray-500 dark:text-gray-400">Items</dt>
                                                <dd className="text-gray-800 dark:text-gray-200">
                                                    {(item.items || []).reduce((sum, line) => sum + Number(line.quantity || 0), 0)} unit(s)
                                                </dd>
                                            </div>
                                            <div className="flex justify-between gap-3">
                                                <dt className="text-gray-500 dark:text-gray-400">Resolution</dt>
                                                <dd className="uppercase text-gray-800 dark:text-gray-200">{item.resolution}</dd>
                                            </div>
                                            <div className="flex justify-between gap-3">
                                                <dt className="text-gray-500 dark:text-gray-400">Refund</dt>
                                                <dd className="text-gray-800 dark:text-gray-200">
                                                    {item.refund ? `${money(item.refund.amount)} (${item.refund.status})` : '—'}
                                                </dd>
                                            </div>
                                            <div className="flex justify-between gap-3">
                                                <dt className="text-gray-500 dark:text-gray-400">Requested</dt>
                                                <dd className="text-gray-800 dark:text-gray-200">{formatDate(item.requested_at || item.created_at)}</dd>
                                            </div>
                                        </dl>
                                    </Link>
                                ))}
                            </div>

                            <Pagination meta={returns} />
                        </>
                    )}
                </Card>
            </PageShell>
        </AdminLayout>
    );
}
