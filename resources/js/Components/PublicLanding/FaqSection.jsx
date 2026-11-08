import { useState, useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import { ChevronDown, Search } from 'lucide-react';
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
                <span className="text-sm font-medium text-gray-900 dark:text-gray-100 pr-4">{faq.question}</span>
                <ChevronDown
                    className={`w-4 h-4 text-gray-400 dark:text-gray-500 flex-shrink-0 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
                />
            </button>
            {isOpen && (
                <div className="px-6 pb-5 prose prose-sm max-w-none text-gray-500 dark:text-gray-400 leading-relaxed" dangerouslySetInnerHTML={{ __html: faq.answer }} />
            )}
        </div>
    );
}

export default function FaqSection() {
    const { platformFaqs } = usePage().props;
    const { t } = useTranslation();
    const [openIndex, setOpenIndex] = useState(null);
    const [search, setSearch] = useState('');
    const [activeCategory, setActiveCategory] = useState('all');

    const toggle = (index) => setOpenIndex(openIndex === index ? null : index);

    // Use DB FAQs if available, fall back to translations
    const dbFaqs = Array.isArray(platformFaqs) && platformFaqs.length > 0 ? platformFaqs : null;

    const faqs = useMemo(() => {
        if (!dbFaqs) {
            const translated = t('landing.faq.items');
            return Array.isArray(translated) ? translated.map((item, i) => ({
                id: i,
                category: 'general',
                question: item.q,
                answer: `<p>${item.a}</p>`,
            })) : [];
        }
        return dbFaqs;
    }, [dbFaqs, t]);

    const categories = useMemo(() => {
        const cats = new Set(faqs.map(f => f.category).filter(Boolean));
        return ['all', ...Array.from(cats)];
    }, [faqs]);

    const categoryLabels = {
        all: t('landing.faq.all_categories') || 'All',
        general: 'General',
        getting_started: 'Getting Started',
        billing: 'Billing',
        store_setup: 'Store Setup',
        features: 'Features',
        security: 'Security',
        support: 'Support',
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
        <section id="faq" className="py-16 sm:py-20 lg:py-24 bg-white dark:bg-gray-900 scroll-mt-16">
            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center max-w-2xl mx-auto mb-10">
                    <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-gray-100">
                        {t('landing.faq.title')}
                    </h2>
                    <p className="mt-4 text-gray-500 dark:text-gray-400 text-lg">
                        {t('landing.faq.subtitle')}
                    </p>
                </div>

                {/* Search */}
                <div className="relative mb-6">
                    <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => { setSearch(e.target.value); setOpenIndex(null); }}
                        placeholder={t('landing.faq.search_placeholder') || 'Search frequently asked questions...'}
                        className="w-full pl-11 pr-4 py-3 text-sm border border-gray-300 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                </div>

                {/* Category filters */}
                {categories.length > 2 && (
                    <div className="flex flex-wrap gap-2 mb-6">
                        {categories.map((cat) => (
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
                    <div className="bg-gray-50 dark:bg-gray-950 rounded-2xl border border-gray-200 dark:border-gray-800 divide-y divide-gray-200 dark:divide-gray-800" role="list" aria-label="Frequently asked questions">
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
                    <div className="text-center py-12">
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {search ? 'No results found for your search.' : 'No FAQs available yet.'}
                        </p>
                    </div>
                )}
            </div>
        </section>
    );
}


