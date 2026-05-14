import { Head } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

export default function Terms({ websiteInfo }) {
    return (
        <ShopLayout>
            <Head title={`Terms of Service - ${websiteInfo?.name || ''}`} />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div className="max-w-3xl mx-auto">
                    <h1 className="text-3xl font-bold text-gray-900 mb-6">Terms of Service</h1>
                    <div className="prose prose-gray max-w-none space-y-4 text-gray-600 leading-relaxed">
                        <p>By using our website and services, you agree to the following terms and conditions.</p>
                        <h3 className="text-lg font-semibold text-gray-900 mt-6">Orders and Payments</h3>
                        <p>All orders are subject to availability and acceptance. We reserve the right to refuse any order. Prices are subject to change without notice.</p>
                        <h3 className="text-lg font-semibold text-gray-900 mt-6">Shipping and Delivery</h3>
                        <p>Shipping times are estimates and not guaranteed. We are not responsible for delays caused by shipping carriers or customs.</p>
                        <h3 className="text-lg font-semibold text-gray-900 mt-6">Returns and Refunds</h3>
                        <p>Returns are accepted within 14 days of delivery for unused items in original packaging. Refunds are processed within 5-7 business days after we receive the returned item.</p>
                        <h3 className="text-lg font-semibold text-gray-900 mt-6">Limitation of Liability</h3>
                        <p>We are not liable for any indirect, incidental, or consequential damages arising from the use of our products or services.</p>
                        <h3 className="text-lg font-semibold text-gray-900 mt-6">Changes to Terms</h3>
                        <p>We reserve the right to update these terms at any time. Continued use of our services constitutes acceptance of the updated terms.</p>
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
