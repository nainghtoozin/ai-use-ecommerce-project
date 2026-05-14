import { Head } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

export default function Faq({ websiteInfo }) {
    const faqs = [
        { q: 'How do I place an order?', a: 'Browse our products, add items to your cart, and proceed to checkout. Fill in your shipping details and choose a payment method to complete your order.' },
        { q: 'What payment methods do you accept?', a: 'We accept various payment methods including bank transfer, mobile payment, and cash on delivery where available.' },
        { q: 'How long does shipping take?', a: 'Shipping times vary depending on your location. Typically, orders are processed within 1-2 business days and delivered within 3-7 business days.' },
        { q: 'Can I cancel my order?', a: 'Yes, you can cancel your order as long as it has not been shipped yet. Go to My Orders and click Cancel if the option is available.' },
        { q: 'What is your return policy?', a: 'We offer easy returns within 14 days of delivery. Items must be unused and in their original packaging.' },
    ];

    return (
        <ShopLayout>
            <Head title={`FAQ - ${websiteInfo?.name || ''}`} />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div className="max-w-3xl mx-auto">
                    <h1 className="text-3xl font-bold text-gray-900 mb-8">Frequently Asked Questions</h1>
                    <div className="space-y-4">
                        {faqs.map((faq, index) => (
                            <details key={index} className="bg-white rounded-lg border border-gray-200 group">
                                <summary className="px-6 py-4 cursor-pointer font-medium text-gray-900 hover:text-blue-600 list-none flex items-center justify-between">
                                    {faq.q}
                                    <svg className="w-5 h-5 text-gray-500 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                    </svg>
                                </summary>
                                <div className="px-6 pb-4 text-gray-600 leading-relaxed">{faq.a}</div>
                            </details>
                        ))}
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
