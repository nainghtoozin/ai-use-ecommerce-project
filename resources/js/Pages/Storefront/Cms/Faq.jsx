import { useState, useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import {
    ChevronDown, ChevronRight, Search, CircleHelp, Rocket, Package,
    CreditCard, Truck, RotateCcw, LifeBuoy, Sparkles, Mail,
} from 'lucide-react';
import { sanitizeStorefrontHtml } from '@/Utils/sanitizeStorefrontHtml';

function FaqItem({ faq, isOpen, toggle }) {
    return (
        <div className="border-b border-gray-200 dark:border-gray-800 last:border-b-0">
            <button
                type="button"
                onClick={toggle}
                className="w-full flex items-center justify-between gap-3 py-3 px-4 sm:px-5 text-left focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                aria-expanded={isOpen}
                aria-controls={`cms-faq-answer-${faq.id}`}
            >
                <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{faq.question}</span>
                <span className={`flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center transition-colors ${isOpen ? 'bg-blue-100 dark:bg-blue-900/40' : 'bg-gray-100 dark:bg-gray-800'}`}>
                    <ChevronDown
                        className={`w-3.5 h-3.5 ${isOpen ? 'text-blue-600 dark:text-blue-400 rotate-180' : 'text-gray-400 dark:text-gray-500'} transition-transform duration-200`}
                    />
                </span>
            </button>
            {isOpen && (
                 <div id={`cms-faq-answer-${faq.id}`} className="px-4 sm:px-5 pb-3">
                    <div
                        className="prose prose-sm dark:prose-invert max-w-none text-gray-600 dark:text-gray-400 leading-relaxed"
                        dangerouslySetInnerHTML={{ __html: sanitizeStorefrontHtml(faq.answer) }}
                    />
                </div>
            )}
        </div>
    );
}

const TOPICS = [
    { icon: Rocket, label: 'Getting Started', category: 'getting_started', keyword: null },
    { icon: Package, label: 'Orders', category: null, keyword: 'order' },
    { icon: CreditCard, label: 'Billing & Payments', category: 'billing', keyword: null },
    { icon: Truck, label: 'Shipping & Delivery', category: 'shipping', keyword: null },
    { icon: RotateCcw, label: 'Returns & Refunds', category: 'returns', keyword: null },
    { icon: LifeBuoy, label: 'Support', category: 'support', keyword: null },
];

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

    const pickTopic = (topic) => {
        setOpenIndex(null);
        if (topic.category && categoryKeys.includes(topic.category)) {
            setActiveCategory(topic.category);
            setSearch('');
        } else if (topic.keyword) {
            setActiveCategory('all');
            setSearch(topic.keyword);
        }
        document.getElementById('faq-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
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

    const storeBase = `/store/${tenant.slug}`;
    const searching = search.trim() !== '';

    return (
        <ShopLayout>
            <Head title={`Frequently Asked Questions - ${tenant?.name || 'Store'}`} />

            <div className="bg-gradient-to-b from-blue-50 to-white dark:from-blue-950/30 dark:to-gray-900 border-b border-gray-200 dark:border-gray-800">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 pt-6 pb-5 sm:pt-8 sm:pb-6">
                    <nav className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 mb-2.5" aria-label="Breadcrumb">
                        <Link href={storeBase} className="hover:text-gray-700 dark:hover:text-gray-200 transition-colors">Home</Link>
                        <ChevronRight className="w-3 h-3" />
                        <span className="text-gray-700 dark:text-gray-200 font-medium">FAQ</span>
                    </nav>
                    <div className="max-w-2xl">
                        <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100 leading-tight">Frequently Asked Questions</h1>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Find quick answers about shopping, payments, delivery and your orders.</p>
                    </div>
                    <div className="relative mt-4 max-w-2xl">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => { setSearch(e.target.value); setOpenIndex(null); }}
                            placeholder="Search questions, orders, payments..."
                            className="w-full pl-12 pr-4 py-3 text-sm border border-gray-300 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                    </div>
                </div>
            </div>

            <div className="max-w-6xl mx-auto px-4 sm:px-6 py-5 sm:py-6">
                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide">Popular Topics</h2>
                <div className="mt-2.5 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
                    {TOPICS.map(({ icon: Icon, label, category, keyword }) => {
                        const isActive = (category && activeCategory === category) || (!category && searching && search.trim().toLowerCase() === keyword);
                        return (
                            <button
                                key={label}
                                type="button"
                                onClick={() => pickTopic({ category, keyword })}
                                className={`flex items-center gap-2.5 rounded-xl border px-3 py-2.5 text-left transition-all min-w-0 ${
                                    isActive
                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/40 shadow-sm'
                                        : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-blue-300 dark:hover:border-blue-700 hover:shadow-sm'
                                }`}
                            >
                                <span className={`flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center ${isActive ? 'bg-blue-600' : 'bg-blue-50 dark:bg-blue-900/30'}`}>
                                    <Icon className={`w-4 h-4 ${isActive ? 'text-white' : 'text-blue-600 dark:text-blue-400'}`} />
                                </span>
                                <span className="min-w-0">
                                    <span className="block text-[13px] font-semibold text-gray-900 dark:text-gray-100 leading-tight">{label}</span>
                                </span>
                            </button>
                        );
                    })}
                </div>

                <div className="mt-6">
                    <div className="flex items-center justify-between gap-3 mb-2.5">
                        <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide">Frequently Asked Questions</h2>
                        <p className="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">
                            {searching
                                ? `${filteredFaqs.length} question${filteredFaqs.length !== 1 ? 's' : ''} found for '${search.trim()}'`
                                : `${filteredFaqs.length} question${filteredFaqs.length !== 1 ? 's' : ''}`}
                        </p>
                    </div>

                    {categoryKeys.length > 2 && (
                        <div className="flex gap-1.5 overflow-x-auto pb-2 -mx-4 px-4 sm:mx-0 sm:px-0 sm:flex-wrap mb-3">
                            {categoryKeys.map((cat) => (
                                <button
                                    key={cat}
                                    onClick={() => { setActiveCategory(cat); setOpenIndex(null); }}
                                    className={`flex-shrink-0 px-3 py-1.5 text-xs font-medium rounded-full transition-colors ${
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

                    {filteredFaqs.length > 0 ? (
                        <div id="faq-list" className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 divide-y divide-gray-200 dark:divide-gray-800 shadow-sm overflow-hidden scroll-mt-24">
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
                        <div className="text-center px-4 py-10 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800">
                            <div className="w-12 h-12 mx-auto mb-3 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                <i className="bi bi-question-circle text-xl text-gray-400 dark:text-gray-500"></i>
                            </div>
                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">No questions found</p>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Try another search or browse a topic.</p>
                            <Link
                                href={route('storefront.support', { store_slug: tenant.slug })}
                                className="mt-3 inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                Browse Help & Support
                            </Link>
                        </div>
                    )}
                </div>

                <div className="mt-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-800/30 px-4 py-4 sm:px-5 flex flex-col sm:flex-row sm:items-center gap-3">
                    <div className="flex items-center gap-2.5 min-w-0 flex-1">
                        <span className="flex-shrink-0 w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                            <CircleHelp className="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        </span>
                        <div className="min-w-0">
                            <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">Still need help?</p>
                            <p className="text-[13px] text-gray-500 dark:text-gray-400 leading-snug">Our FAQs cover common questions. If you need more help, our support team is available.</p>
                        </div>
                    </div>
                    <div className="flex flex-col sm:flex-row gap-2 flex-shrink-0">
                        <Link
                            href={route('storefront.support', { store_slug: tenant.slug })}
                            className="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            Help & Support
                        </Link>
                        <Link
                            href={route('storefront.contact', { store_slug: tenant.slug })}
                            className="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                        >
                            <Mail className="w-4 h-4" />
                            Contact Us
                        </Link>
                    </div>
                </div>

                <div className="mt-3 rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-2.5">
                    <div className="flex items-center gap-2 min-w-0 flex-1">
                        <Sparkles className="w-4 h-4 flex-shrink-0 text-violet-500" />
                        <p className="text-[13px] text-gray-600 dark:text-gray-400">
                            <span className="font-semibold text-gray-900 dark:text-gray-100">Can't find your answer?</span>{' '}
                            Get instant help from our upcoming AI assistant.
                        </p>
                    </div>
                    <button
                        type="button"
                        disabled
                        title="Coming soon"
                        className="flex-shrink-0 inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-violet-100 dark:bg-violet-950/50 text-violet-500 dark:text-violet-400 text-sm font-medium rounded-lg cursor-not-allowed opacity-70"
                    >
                        <Sparkles className="w-4 h-4" />
                        Ask AI · Coming Soon
                    </button>
                </div>
            </div>
        </ShopLayout>
    );
}
