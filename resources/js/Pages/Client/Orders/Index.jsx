import { Head, Link, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';

export default function ClientOrdersIndex({ orders }) {
    const cc = getCurrencyConfig(usePage().props.platform_setting, usePage().props.website_info);
    const statusColors = {
        pending: 'bg-yellow-100 text-yellow-800',
        confirmed: 'bg-blue-100 text-blue-800',
        shipped: 'bg-indigo-100 text-indigo-800',
        delivered: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    };

    const paymentColors = {
        unpaid: 'bg-red-100 text-red-800',
        paid: 'bg-blue-100 text-blue-800',
        verified: 'bg-green-100 text-green-800',
        rejected: 'bg-gray-100 text-gray-800',
    };

return (
        <ShopLayout>
            <Head title="My Orders" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h1 className="text-xl sm:text-2xl font-bold text-gray-900 mb-6 sm:mb-8">My Orders</h1>

                {!orders?.data?.length ? (
                    <div className="text-center py-12 sm:py-16 bg-white rounded-xl border border-gray-200">
                        <i className="bi bi-receipt text-5xl text-gray-300"></i>
                        <h3 className="mt-4 text-lg font-medium text-gray-900">No orders yet</h3>
                        <p className="mt-2 text-gray-500">Your order history will appear here once you make a purchase.</p>
                        <Link href="/" className="mt-6 inline-block px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Start Shopping
                        </Link>
                    </div>
                ) : (
                    <div className="space-y-4 sm:space-y-6">
                        {orders.data.map((order) => (
                            <Link
                                key={order.id}
                                href={`/orders/${order.id}`}
                                className="block bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow"
                            >
                                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                    <div>
                                        <div className="flex items-center gap-3">
                                            <h3 className="font-semibold text-gray-900">Order #{order.id}</h3>
                                            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[order.order_status] || 'bg-gray-100 text-gray-800'}`}>
                                                {order.order_status}
                                            </span>
                                            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${paymentColors[order.payment_status] || 'bg-gray-100 text-gray-800'}`}>
                                                {order.payment_status}
                                            </span>
                                        </div>
                                        <p className="text-sm text-gray-500 mt-1">
                                            {new Date(order.created_at).toLocaleDateString()} · {order.items?.reduce((s, i) => s + i.quantity, 0)} items
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-lg font-bold text-gray-900">{formatCurrency(order.total_amount, cc)}</p>
                                        <p className="text-sm text-gray-500">
                                            {order.payment_method?.name || order.paymentMethod?.name || ''}
                                        </p>
                                    </div>
                                </div>

                                {/* Items Preview */}
                                <div className="mt-3 pt-3 border-t border-gray-100 flex gap-2 overflow-hidden">
                                    {order.items?.slice(0, 3).map((item) => (
                                        <div key={item.id} className="flex items-center gap-2 bg-gray-50 px-3 py-1 rounded-full text-sm text-gray-700">
                                            <span className="font-medium">{item.product?.name || 'Product'}</span>
                                            <span className="text-gray-400">×{item.quantity}</span>
                                        </div>
                                    ))}
                                    {order.items?.length > 3 && (
                                        <span className="flex items-center text-sm text-gray-500">+{order.items.length - 3} more</span>
                                    )}
                                </div>
                            </Link>
                        ))}

                        {/* Pagination */}
                        {orders.links?.length > 3 && (
                            <div className="flex justify-center gap-1 mt-6">
                                {orders.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url || '#'}
                                        className={`px-3 py-2 text-sm rounded-lg transition-colors ${
                                            link.active ? 'bg-blue-600 text-white' : link.url ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed'
                                        }`}
                                    >
                                        {link.label.replace('&laquo;', '«').replace('&raquo;', '»').replace('Previous', '←').replace('Next', '→')}
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
