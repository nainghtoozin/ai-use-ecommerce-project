import { Head, Link, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { assetUrl } from '@/Utils/helpers';

function Breadcrumb({ items }) {
    return (
        <nav className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 mb-6" aria-label="Breadcrumb">
            {items.map((item, index) => (
                <span key={index} className="flex items-center gap-1.5">
                    {index > 0 && <i className="bi bi-chevron-right text-[10px]"></i>}
                    {item.href ? (
                        <Link href={item.href} className="hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                            {item.label}
                        </Link>
                    ) : (
                        <span className="text-gray-700 dark:text-gray-200 font-medium">{item.label}</span>
                    )}
                </span>
            ))}
        </nav>
    );
}

function formatDate(dateStr) {
    if (!dateStr) return null;
    try {
        return new Date(dateStr).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    } catch {
        return null;
    }
}

export default function CmsPage({ title, children, updatedAt, breadcrumbs = [], maxWidth = 'max-w-4xl' }) {
    const { tenant } = usePage().props;
    const storeSlug = tenant?.slug;

    const defaultBreadcrumbs = [
        { label: 'Home', href: `/store/${storeSlug}` },
        ...breadcrumbs,
        { label: title },
    ];

    const formattedDate = formatDate(updatedAt);

    return (
        <ShopLayout>
            <Head title={`${title} - ${tenant?.name || 'Store'}`} />

            <div className="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
                <div className={`${maxWidth} mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12`}>
                    <Breadcrumb items={defaultBreadcrumbs} />
                    <h1 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-gray-100">
                        {title}
                    </h1>
                    {formattedDate && (
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Last updated: {formattedDate}
                        </p>
                    )}
                </div>
            </div>

            <div className={`${maxWidth} mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12`}>
                {children}
            </div>
        </ShopLayout>
    );
}
