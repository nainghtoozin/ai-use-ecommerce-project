import { useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { useTranslation } from '@/Utils/useTranslation';

function FaqItem({ faq, isOpen, toggle }) {
    return (
        <div className="border-b border-gray-200 dark:border-gray-800 last:border-b-0">
            <button
                type="button"
                onClick={toggle}
                className="w-full flex items-center justify-between py-5 px-6 text-left focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 rounded-lg"
                aria-expanded={isOpen}
            >
                <span className="text-sm font-medium text-gray-900 dark:text-gray-100 pr-4">{faq.q}</span>
                <ChevronDown
                    className={`w-4 h-4 text-gray-400 dark:text-gray-500 flex-shrink-0 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
                />
            </button>
            {isOpen && (
                <div className="px-6 pb-5">
                    <p className="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">{faq.a}</p>
                </div>
            )}
        </div>
    );
}

export default function FaqSection() {
    const { t } = useTranslation();
    const [openIndex, setOpenIndex] = useState(null);

    const toggle = (index) => {
        setOpenIndex(openIndex === index ? null : index);
    };

    const faqItems = t('landing.faq.items');
    const faqs = Array.isArray(faqItems) ? faqItems : [];

    return (
        <section id="faq" className="py-16 sm:py-20 lg:py-24 bg-white dark:bg-gray-900 scroll-mt-16">
            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center max-w-2xl mx-auto mb-12">
                    <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-gray-100">
                        {t('landing.faq.title')}
                    </h2>
                    <p className="mt-4 text-gray-500 dark:text-gray-400 text-lg">
                        {t('landing.faq.subtitle')}
                    </p>
                </div>

                <div className="bg-gray-50 dark:bg-gray-950 rounded-2xl border border-gray-200 dark:border-gray-800 divide-y divide-gray-200 dark:divide-gray-800" role="list" aria-label="Frequently asked questions">
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
