import { Head, Link, router } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import Pagination from '@/Components/Pagination';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import { usePage } from '@inertiajs/react';

const STATUS_STYLES = {
    requested: 'bg-amber-50 text-amber-700 border-amber-200',
    approved: 'bg-blue-50 text-blue-700 border-blue-200',
    rejected: 'bg-rose-50 text-rose-700 border-rose-200',
    awaiting_shipment: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    in_transit: 'bg-sky-50 text-sky-700 border-sky-200',
    received: 'bg-teal-50 text-teal-700 border-teal-200',
    inspection: 'bg-purple-50 text-purple-700 border-purple-200',
    completed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    cancelled: 'bg-gray-100 text-gray-600 border-gray-200',
    expired: 'bg-gray-100 text-gray-600 border-gray-200',
};

export default function StorefrontReturns({ tenant, returns }) {
    const { props } = usePage();
    const cc = getCurrencyConfig(props.platform_setting, props.website_info);
    const rows = returns?.data || [];

    return (
        <ShopLayout>
            <Head title={`My Returns - ${tenant.name}`} />
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900 dark:text-gray-100">My returns</h1>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Track return requests and refunds for this store.</p>
                    </div>
                    <Link href={route('storefront.customer.orders', { store_slug: tenant.slug })} className="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">
                        My orders
                    </Link>
                </div>

                {rows.length === 0 ? (
                    <div className="mt-6 rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-900">
                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">No return requests yet</p>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Open a delivered order to request a return.</p>
                    </div>
                ) : (
                    <div className="mt-5 space-y-3">
                        {rows.map((item) => (
                            <Link
                                key={item.id}
                                href={route('storefront.customer.returns.show', { store_slug: tenant.slug, return: item.id })}
                                className="block rounded-xl border border-gray-200 bg-white p-4 hover:border-blue-300 dark:border-gray-800 dark:bg-gray-900"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p className="font-mono text-xs text-gray-500 dark:text-gray-400">{item.number}</p>
                                        <p className="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {item.order ? `Order #${item.order.invoice_number}` : 'Return request'}
                                        </p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">{item.reason || 'No reason provided'}</p>
                                    </div>
                                    <span className={`inline-block rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ${STATUS_STYLES[item.status] || 'border-gray-200 bg-gray-50 text-gray-600'}`}>
                                        {String(item.status).replace(/_/g, ' ')}
                                    </span>
                                </div>
                                <dl className="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                                    <div>
                                        <dt className="text-gray-500 dark:text-gray-400">Items</dt>
                                        <dd className="text-gray-800 dark:text-gray-200">{(item.items || []).length} line(s)</dd>
                                    </div>
                                    <div>
                                        <dt className="text-gray-500 dark:text-gray-400">Resolution</dt>
                                        <dd className="uppercase text-gray-800 dark:text-gray-200">{item.resolution}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-gray-500 dark:text-gray-400">Refund</dt>
                                        <dd className="text-gray-800 dark:text-gray-200">{item.refund ? formatCurrency(item.refund.amount, cc) : '—'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-gray-500 dark:text-gray-400">Requested</dt>
                                        <dd className="text-gray-800 dark:text-gray-200">
                                            {item.requested_at ? new Date(item.requested_at).toLocaleDateString() : '—'}
                                        </dd>
                                    </div>
                                </dl>
                            </Link>
                        ))}
                        <div className="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                            <Pagination meta={returns} />
                        </div>
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
