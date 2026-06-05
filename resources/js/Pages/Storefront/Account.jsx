import { Head, Link, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

function StatCard({ label, value, color }) {
    return (
        <div className="bg-white rounded-lg border border-gray-200 p-4">
            <p className="text-sm text-gray-500">{label}</p>
            <p className={`text-2xl font-bold mt-1 ${color}`}>{value}</p>
        </div>
    );
}

export default function Account({ tenant, customer, orderStats }) {
    const { auth } = usePage().props;
    const storeSlug = tenant.slug;

    return (
        <ShopLayout>
            <Head title={`My Account - ${tenant.name}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                    <h1 className="text-2xl font-bold text-gray-900">My Account</h1>
                    <span className="text-sm text-gray-500">Member since {new Date(customer.member_since).toLocaleDateString()}</span>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-6 mb-8">
                    <div className="flex items-center gap-4">
                        <div className="w-14 h-14 rounded-full bg-blue-600 text-white flex items-center justify-center text-xl font-bold">
                            {customer.name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <h2 className="text-lg font-semibold text-gray-900">{customer.name}</h2>
                            <p className="text-sm text-gray-500">{customer.email}</p>
                        </div>
                    </div>
                </div>

                <h2 className="text-lg font-semibold text-gray-900 mb-4">Order Overview</h2>
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
                    <StatCard label="Total" value={orderStats.total} color="text-gray-900" />
                    <StatCard label="Pending" value={orderStats.pending} color="text-yellow-600" />
                    <StatCard label="Processing" value={orderStats.processing} color="text-blue-600" />
                    <StatCard label="Shipped" value={orderStats.shipped} color="text-indigo-600" />
                    <StatCard label="Delivered" value={orderStats.delivered} color="text-green-600" />
                    <StatCard label="Cancelled" value={orderStats.cancelled} color="text-red-600" />
                </div>

                <div className="flex flex-wrap gap-4">
                    <Link
                        href={route('storefront.customer.orders', { store_slug: storeSlug })}
                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium"
                    >
                        <i className="bi bi-receipt"></i>
                        View Orders
                    </Link>
                    <Link
                        href={route('storefront.customer.addresses', { store_slug: storeSlug })}
                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium"
                    >
                        <i className="bi bi-geo-alt"></i>
                        Manage Addresses
                    </Link>
                    <Link
                        href={route('storefront.index', { store_slug: storeSlug })}
                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium"
                    >
                        <i className="bi bi-shop"></i>
                        Back to Store
                    </Link>
                </div>
            </div>
        </ShopLayout>
    );
}
