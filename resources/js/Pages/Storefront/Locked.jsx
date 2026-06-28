import { Head } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

export default function StorefrontLocked({ tenant }) {
    return (
        <ShopLayout>
            <Head title="Store Unavailable" />

            <div className="min-h-[60vh] flex items-center justify-center py-16">
                <div className="max-w-md mx-auto text-center px-4">
                    <div className="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i className="bi bi-shop text-4xl text-gray-400"></i>
                    </div>

                    <h1 className="text-2xl font-bold text-gray-900 mb-3">
                        This store is temporarily unavailable
                    </h1>

                    <p className="text-gray-500 leading-relaxed">
                        The store owner&apos;s subscription has expired. Please check back later.
                    </p>
                </div>
            </div>
        </ShopLayout>
    );
}
