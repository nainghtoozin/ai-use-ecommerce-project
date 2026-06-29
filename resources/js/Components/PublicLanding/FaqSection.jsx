import { useState } from 'react';
import { ChevronDown } from 'lucide-react';

const faqs = [
    {
        q: 'How do I create a store?',
        a: 'Click "Start Free Trial" and fill in your store name and details. You\'ll be guided through the setup process and your store will be ready in minutes.',
    },
    {
        q: 'Is there a free plan available?',
        a: 'Yes! Our Free plan includes standard products, order management, and cash on delivery. No credit card required to start.',
    },
    {
        q: 'Can I use my own domain name?',
        a: 'The Starter plan and above include custom domain support. You can use your own domain to give your store a professional branded URL.',
    },
    {
        q: 'What payment methods are supported?',
        a: 'We support multiple payment gateways including KBZPay, WavePay, AYA Pay, cash on delivery, and bank transfers. Higher plans include Stripe and PayPal for international payments.',
    },
    {
        q: 'Can I upgrade or downgrade my plan?',
        a: 'Yes, you can change your plan at any time. When upgrading, you get immediate access to new features. Our team can assist with plan changes.',
    },
    {
        q: 'Is there a trial period for paid plans?',
        a: 'Yes, paid plans come with a free trial period. You can explore all features before committing to a subscription.',
    },
    {
        q: 'How does Telegram integration work?',
        a: 'Our Telegram bot sends real-time notifications for new orders, payments, and inventory alerts. You can also manage orders directly from Telegram.',
    },
    {
        q: 'What kind of support do you offer?',
        a: 'We provide email support for all plans. Priority support is available for higher-tier plans. Check our contact page for more details.',
    },
];

function FaqItem({ faq, isOpen, toggle }) {
    return (
        <div className="border-b border-gray-200 last:border-b-0">
            <button
                type="button"
                onClick={toggle}
                className="w-full flex items-center justify-between py-5 px-6 text-left focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 rounded-lg"
                aria-expanded={isOpen}
            >
                <span className="text-sm font-medium text-gray-900 pr-4">{faq.q}</span>
                <ChevronDown
                    className={`w-4 h-4 text-gray-400 flex-shrink-0 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
                />
            </button>
            {isOpen && (
                <div className="px-6 pb-5">
                    <p className="text-sm text-gray-500 leading-relaxed">{faq.a}</p>
                </div>
            )}
        </div>
    );
}

export default function FaqSection() {
    const [openIndex, setOpenIndex] = useState(null);

    const toggle = (index) => {
        setOpenIndex(openIndex === index ? null : index);
    };

    return (
        <section id="faq" className="py-16 sm:py-20 lg:py-24 bg-white scroll-mt-16">
            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center max-w-2xl mx-auto mb-12">
                    <h2 className="text-3xl sm:text-4xl font-bold text-gray-900">
                        Frequently Asked Questions
                    </h2>
                    <p className="mt-4 text-gray-500 text-lg">
                        Everything you need to know about the platform.
                    </p>
                </div>

                <div className="bg-gray-50 rounded-2xl border border-gray-200 divide-y divide-gray-200" role="list" aria-label="Frequently asked questions">
                    {faqs.map((faq, index) => (
                        <FaqItem
                            key={index}
                            faq={faq}
                            isOpen={openIndex === index}
                            toggle={() => toggle(index)}
                        />
                    ))}
                </div>
            </div>
        </section>
    );
}
