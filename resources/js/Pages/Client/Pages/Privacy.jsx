import { Head } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

export default function Privacy({ websiteInfo }) {
    return (
        <ShopLayout>
            <Head title={`Privacy Policy - ${websiteInfo?.name || ''}`} />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div className="max-w-3xl mx-auto">
                    <h1 className="text-3xl font-bold text-gray-900 mb-6">Privacy Policy</h1>
                    <div className="prose prose-gray max-w-none space-y-4 text-gray-600 leading-relaxed">
                        <p>Your privacy is important to us. This privacy policy explains how we collect, use, and protect your personal information.</p>
                        <h3 className="text-lg font-semibold text-gray-900 mt-6">Information We Collect</h3>
                        <p>We collect information you provide when placing an order, creating an account, or contacting us, including your name, email address, phone number, and shipping address.</p>
                        <h3 className="text-lg font-semibold text-gray-900 mt-6">How We Use Your Information</h3>
                        <p>We use your information to process orders, provide customer support, and improve our services. We do not share your personal information with third parties except as necessary to fulfill your orders.</p>
                        <h3 className="text-lg font-semibold text-gray-900 mt-6">Data Security</h3>
                        <p>We implement appropriate security measures to protect your personal information from unauthorized access, alteration, or disclosure.</p>
                        <h3 className="text-lg font-semibold text-gray-900 mt-6">Contact</h3>
                        <p>If you have any questions about this privacy policy, please contact us.</p>
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
