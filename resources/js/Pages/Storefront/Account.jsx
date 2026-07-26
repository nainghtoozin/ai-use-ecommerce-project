import { Head, Link, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

function StatCard({ label, value, color, icon }) {
    return (
        <div className="bg-white rounded-lg border border-gray-200 p-3">
            <div className="flex items-center gap-2 mb-1">
                <svg className="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={icon} />
                </svg>
                <p className="text-[11px] text-gray-500 uppercase tracking-wide font-medium">{label}</p>
            </div>
            <p className={`text-xl font-bold ${color}`}>{value}</p>
        </div>
    );
}

export default function Account({ tenant, customer, orderStats }) {
    const { auth } = usePage().props;
    const storeSlug = tenant.slug;

    return (
        <ShopLayout>
            <Head title={`My Account - ${tenant.name}`} />

            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
                    <h1 className="text-lg sm:text-xl font-bold text-gray-900">My Account</h1>
                    <span className="text-xs text-gray-500">Member since {new Date(customer.member_since).toLocaleDateString()}</span>
                </div>

                <div className="bg-white rounded-lg border border-gray-200 p-4 mb-5">
                    <div className="flex items-center gap-3">
                        <div className="w-11 h-11 rounded-full bg-blue-600 text-white flex items-center justify-center text-base font-bold shrink-0">
                            {customer.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0">
                            <h2 className="text-sm font-semibold text-gray-900 truncate">{customer.name}</h2>
                            <p className="text-xs text-gray-500 truncate">{customer.email}</p>
                        </div>
                    </div>
                </div>

                <h2 className="text-sm font-semibold text-gray-900 mb-3">Order Overview</h2>
                <div className="grid grid-cols-3 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 mb-5">
                    <StatCard label="Total" value={orderStats.total} color="text-gray-900" icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    <StatCard label="Pending" value={orderStats.pending} color="text-yellow-600" icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    <StatCard label="Processing" value={orderStats.processing} color="text-blue-600" icon="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    <StatCard label="Shipped" value={orderStats.shipped} color="text-indigo-600" icon="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    <StatCard label="Delivered" value={orderStats.delivered} color="text-green-600" icon="M5 13l4 4L19 7" />
                    <StatCard label="Cancelled" value={orderStats.cancelled} color="text-red-600" icon="M6 18L18 6M6 6l12 12" />
                </div>

                <div className="flex flex-wrap gap-2.5">
                    <Link
                        href={route('storefront.customer.orders', { store_slug: storeSlug })}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                        View Orders
                    </Link>
                    <Link
                        href={route('storefront.customer.addresses', { store_slug: storeSlug })}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium transition-colors"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        Manage Addresses
                    </Link>
                    <Link
                        href={route('storefront.index', { store_slug: storeSlug })}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium transition-colors"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                        Back to Store
                    </Link>
                </div>
            </div>
        </ShopLayout>
    );
}
