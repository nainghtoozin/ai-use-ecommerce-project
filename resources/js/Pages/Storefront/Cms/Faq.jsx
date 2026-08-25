import { useState, useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import CmsPage from '@/Components/Storefront/CmsPage';
import { ChevronDown, Search } from 'lucide-react';
import { sanitizeStorefrontHtml } from '@/Utils/sanitizeStorefrontHtml';

function FaqItem({ faq, isOpen, toggle }) {
    return (
        <div className="border-b border-gray-200 dark:border-gray-800 last:border-b-0">
            <button
                type="button"
                onClick={toggle}
                className="w-full flex items-center justify-between py-4 px-5 text-left focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                aria-expanded={isOpen}
                aria-controls={`cms-faq-answer-${faq.id}`}
            >
                <span className="text-sm font-medium text-gray-900 dark:text-gray-100 pr-4">{faq.question}</span>
                <ChevronDown
                    className={`w-4 h-4 text-gray-400 dark:text-gray-500 flex-shrink-0 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
                />
            </button>
            {isOpen && (
                 <div id={`cms-faq-answer-${faq.id}`} className="px-5 pb-4">
                    <div
                        className="prose prose-sm dark:prose-invert max-w-none text-gray-600 dark:text-gray-400 leading-relaxed"
                        dangerouslySetInnerHTML={{ __html: sanitizeStorefrontHtml(faq.answer) }}
                    />
                </div>
            )}
        </div>
    );
}

export default function Faq({ tenant, faqs = [], categories = {} }) {
    const [openIndex, setOpenIndex] = useState(null);
    const [search, setSearch] = useState('');
    const [activeCategory, setActiveCategory] = useState('all');

    const toggle = (index) => setOpenIndex(openIndex === index ? null : index);

    const categoryKeys = useMemo(() => {
        const cats = new Set(faqs.map(f => f.category).filter(Boolean));
        return ['all', ...Array.from(cats)];
    }, [faqs]);

    const categoryLabels = {
        all: 'All',
        general: 'General',
        getting_started: 'Getting Started',
        billing: 'Billing',
        store_setup: 'Store Setup',
        features: 'Features',
        security: 'Security',
        support: 'Support',
        shipping: 'Shipping & Delivery',
        returns: 'Returns & Refunds',
        ...categories,
    };

    const filteredFaqs = useMemo(() => {
        let result = faqs;
        if (activeCategory !== 'all') {
            result = result.filter(f => f.category === activeCategory);
        }
        if (search.trim()) {
            const q = search.toLowerCase();
            result = result.filter(f =>
                f.question?.toLowerCase().includes(q) ||
                f.answer?.toLowerCase().includes(q)
            );
        }
        return result;
    }, [faqs, activeCategory, search]);

    return (
        <CmsPage
            title="Frequently Asked Questions"
            breadcrumbs={[{ label: 'FAQ' }]}
            maxWidth="max-w-3xl"
        >
            <p className="text-gray-500 dark:text-gray-400 text-center mb-8">
                Find answers to common questions about our store.
            </p>

            {/* Search */}
            <div className="relative mb-6">
                <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input
                    type="text"
                    value={search}
                    onChange={(e) => { setSearch(e.target.value); setOpenIndex(null); }}
                    placeholder="Search questions..."
                    className="w-full pl-11 pr-4 py-3 text-sm border border-gray-300 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
            </div>

            {/* Category filters */}
            {categoryKeys.length > 2 && (
                <div className="flex flex-wrap gap-2 mb-6">
                    {categoryKeys.map((cat) => (
                        <button
                            key={cat}
                            onClick={() => { setActiveCategory(cat); setOpenIndex(null); }}
                            className={`px-3 py-1.5 text-xs font-medium rounded-full transition-colors ${
                                activeCategory === cat
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'
                            }`}
                        >
                            {categoryLabels[cat] || cat}
                        </button>
                    ))}
                </div>
            )}

            {/* FAQ List */}
            {filteredFaqs.length > 0 ? (
                <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 divide-y divide-gray-200 dark:divide-gray-800 shadow-sm">
                    {filteredFaqs.map((faq, index) => (
                        <FaqItem
                            key={faq.id || index}
                            faq={faq}
                            isOpen={openIndex === index}
                            toggle={() => toggle(index)}
                        />
                    ))}
                </div>
            ) : (
                <div className="text-center py-16 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800">
                    <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <i className="bi bi-question-circle text-2xl text-gray-400 dark:text-gray-500"></i>
                    </div>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {search ? 'No results found for your search.' : 'No FAQs available yet.'}
                    </p>
                </div>
            )}
        </CmsPage>
    );
}
