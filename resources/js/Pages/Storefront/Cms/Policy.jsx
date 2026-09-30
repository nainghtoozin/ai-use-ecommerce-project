import { useEffect, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import RichContent from '@/Components/Storefront/RichContent';
import { ShieldCheck, FileText, ChevronRight, ChevronDown, ArrowLeft } from 'lucide-react';

const PAGE_META = {
    'Privacy Policy': {
        icon: ShieldCheck,
        subtitle: 'How we collect, use and protect your information.',
        emptyTitle: 'Privacy Policy',
        emptyMessage: 'This store has not published its privacy policy yet.',
    },
    'Terms & Conditions': {
        icon: FileText,
        subtitle: 'Please review the terms that apply when using this store.',
        emptyTitle: 'Terms & Conditions',
        emptyMessage: 'This store has not published its terms and conditions yet.',
    },
};

function slugify(text, index) {
    const base = (text || '')
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/[\s_-]+/g, '-')
        .slice(0, 60);
    return `section-${index}-${base || 'untitled'}`;
}

export default function Policy({ tenant, page }) {
    const meta = PAGE_META[page.title] || {
        icon: FileText,
        subtitle: null,
        emptyTitle: page.title,
        emptyMessage: 'This page is being prepared. Please check back later.',
    };
    const Icon = meta.icon;

    const contentRef = useRef(null);
    const [sections, setSections] = useState([]);
    const [activeSection, setActiveSection] = useState(null);
    const [tocOpen, setTocOpen] = useState(false);

    useEffect(() => {
        const root = contentRef.current;
        if (!root || !page.content) {
            setSections([]);
            return;
        }
        const headings = Array.from(root.querySelectorAll('h2, h3'));
        const found = headings.map((el, index) => {
            const id = slugify(el.textContent, index);
            el.id = id;
            return { id, text: el.textContent?.trim() || `Section ${index + 1}`, level: el.tagName.toLowerCase() };
        });
        setSections(found);
        setActiveSection(found[0]?.id || null);
    }, [page.content]);

    useEffect(() => {
        if (sections.length === 0) return;
        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        setActiveSection(entry.target.id);
                    }
                }
            },
            { rootMargin: '-20% 0px -70% 0px' }
        );
        const root = contentRef.current;
        sections.forEach(({ id }) => {
            const el = root?.querySelector(`#${CSS.escape(id)}`);
            if (el) observer.observe(el);
        });
        return () => observer.disconnect();
    }, [sections]);

    const scrollTo = (id) => {
        contentRef.current?.querySelector(`#${CSS.escape(id)}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTocOpen(false);
    };

    const storeBase = `/store/${tenant.slug}`;

    return (
        <ShopLayout>
            <Head title={`${page.title} - ${tenant?.name || 'Store'}`} />

            <div className="max-w-6xl mx-auto px-4 sm:px-6 py-5 sm:py-7">
                <nav className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 mb-2.5" aria-label="Breadcrumb">
                    <Link href={storeBase} className="hover:text-gray-700 dark:hover:text-gray-200 transition-colors">Home</Link>
                    <ChevronRight className="w-3 h-3" />
                    <span className="text-gray-700 dark:text-gray-200 font-medium">{page.title}</span>
                </nav>

                <div className="rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-700 px-4 py-4 sm:px-5 text-white shadow-sm">
                    <div className="flex items-center gap-2.5">
                        <span className="flex-shrink-0 w-9 h-9 rounded-xl bg-white/15 flex items-center justify-center">
                            <Icon className="w-5 h-5 text-white" />
                        </span>
                        <div className="min-w-0">
                            <h1 className="text-xl sm:text-2xl font-bold leading-tight">{page.title}</h1>
                            {meta.subtitle && (
                                <p className="text-[13px] text-blue-100">"{meta.subtitle}"</p>
                            )}
                        </div>
                    </div>
                    {page.content && page.updated_at && (
                        <p className="mt-2 text-xs text-blue-100/80">
                            Last updated: {new Date(page.updated_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                        </p>
                    )}
                </div>

                {page.content ? (
                    <>
                        {sections.length > 0 && (
                            <div className="lg:hidden mt-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
                                <button
                                    type="button"
                                    onClick={() => setTocOpen(!tocOpen)}
                                    aria-expanded={tocOpen}
                                    className="w-full flex items-center justify-between px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    On this page
                                    <ChevronDown className={`w-4 h-4 text-gray-400 transition-transform ${tocOpen ? 'rotate-180' : ''}`} />
                                </button>
                                {tocOpen && (
                                    <div className="border-t border-gray-200 dark:border-gray-800 px-2 py-1.5 max-h-56 overflow-y-auto">
                                        {sections.map((section) => (
                                            <button
                                                key={section.id}
                                                type="button"
                                                onClick={() => scrollTo(section.id)}
                                                className={`block w-full text-left px-2.5 py-1.5 text-[13px] rounded-lg transition-colors ${
                                                    section.level === 'h3' ? 'pl-5' : ''
                                                } ${
                                                    activeSection === section.id
                                                        ? 'text-blue-600 dark:text-blue-400 font-semibold bg-blue-50 dark:bg-blue-950/40'
                                                        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'
                                                }`}
                                            >
                                                {section.text}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="mt-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
                            <div className="grid grid-cols-1 lg:grid-cols-4 items-start">
                                {sections.length > 0 ? (
                                    <>
                                        <aside className="hidden lg:block lg:col-span-1 border-r border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/30 p-3 lg:sticky lg:top-20 self-start max-h-[70vh] overflow-y-auto">
                                        <p className="px-2 pb-1.5 text-xs font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide">On this page</p>
                                        <nav className="space-y-px">
                                            {sections.map((section) => (
                                                <button
                                                    key={section.id}
                                                    type="button"
                                                    onClick={() => scrollTo(section.id)}
                                                    className={`block w-full text-left px-2 py-1.5 text-[13px] rounded-lg border-l-2 transition-colors ${
                                                        section.level === 'h3' ? 'ml-2' : ''
                                                    } ${
                                                        activeSection === section.id
                                                            ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold bg-blue-50 dark:bg-blue-950/40'
                                                            : 'border-transparent text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'
                                                    }`}
                                                >
                                                    <span className="line-clamp-2">{section.text}</span>
                                                </button>
                                            ))}
                                        </nav>
                                    </aside>
                                        <article ref={contentRef} className="lg:col-span-3 min-w-0 p-4 sm:p-6">
                                            <RichContent content={page.content} className="text-[15px] leading-relaxed" />
                                        </article>
                                    </>
                                ) : (
                                    <article ref={contentRef} className="p-4 sm:p-6 max-w-3xl min-w-0">
                                        <RichContent content={page.content} className="text-[15px] leading-relaxed" />
                                    </article>
                                )}
                            </div>
                        </div>
                    </>
                ) : (
                    <div className="mt-4 max-w-xl mx-auto text-center rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-6 py-10">
                        <span className="inline-flex w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 items-center justify-center mb-3">
                            <Icon className="w-6 h-6 text-gray-400 dark:text-gray-500" />
                        </span>
                        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">{meta.emptyTitle}</h2>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">"{meta.emptyMessage}"</p>
                        <Link
                            href={storeBase}
                            className="mt-4 inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            <ArrowLeft className="w-4 h-4" />
                            Back to Store
                        </Link>
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
