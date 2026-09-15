import { Link } from '@inertiajs/react';

export default function Pagination({ meta }) {
    if (!meta?.links) return null;

    const links = meta.links;

    return (
        <div className="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-800">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="text-sm text-gray-500 dark:text-gray-400 text-center sm:text-left">
                    Showing {meta.from ?? 0} to {meta.to ?? 0} of {meta.total ?? 0} results
                </div>
                <nav className="flex flex-wrap items-center justify-center gap-1">
                    {links.map((link, i) => {
                        if (!link.url) {
                            return (
                                <span
                                    key={i}
                                    className="px-3 py-1 text-sm text-gray-400 dark:text-gray-500 cursor-not-allowed"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            );
                        }

                        return (
                            <Link
                                key={i}
                                href={link.url}
                                preserveState
                                preserveScroll
                                className={`px-3 py-1 text-sm rounded-lg ${
                                    link.active
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        );
                    })}
                </nav>
            </div>
        </div>
    );
}
