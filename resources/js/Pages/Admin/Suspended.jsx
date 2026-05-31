import { Head } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function AdminSuspended() {
    return (
        <AdminLayout>
            <Head title="Store Suspended" />

            <div className="p-6 lg:p-8">
                <div className="max-w-2xl mx-auto text-center">
                    <div className="bg-white rounded-xl border border-gray-200 p-8 lg:p-12 shadow-sm">
                        <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i className="bi bi-pause-circle-fill text-3xl text-red-600"></i>
                        </div>

                        <h1 className="text-2xl font-bold text-gray-900 mb-3">
                            Store Suspended
                        </h1>

                        <p className="text-gray-600 mb-6 leading-relaxed">
                            Your store has been suspended. You are unable to manage products,
                            orders, or access store settings at this time.
                        </p>

                        <div className="bg-gray-50 rounded-lg p-4 mb-8 text-left text-sm text-gray-600 space-y-2">
                            <p className="font-medium text-gray-800">What this means:</p>
                            <ul className="list-disc list-inside space-y-1">
                                <li>Your storefront is not accessible to customers</li>
                                <li>Order processing and product management are disabled</li>
                                <li>All admin features are temporarily restricted</li>
                            </ul>
                        </div>

                        <p className="text-sm text-gray-500">
                            If you believe this is an error, please contact support for assistance.
                        </p>

                        <div className="mt-8 pt-6 border-t border-gray-100">
                            <button
                                type="button"
                                className="text-sm text-gray-500 hover:text-gray-700 underline"
                                onClick={() => router.post(route('logout'))}
                            >
                                Sign out
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
