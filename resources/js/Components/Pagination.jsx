import { Link } from '@inertiajs/react';

export default function Pagination({ meta }) {
    if (!meta?.links) return null;

    const links = meta.links;

    return (
        <div className="px-6 py-4 border-t border-gray-200">
            <div className="flex items-center justify-between">
                <div className="text-sm text-gray-500">
                    Showing {meta.from ?? 0} to {meta.to ?? 0} of {meta.total ?? 0} results
                </div>
                <nav className="flex items-center gap-1">
                    {links.map((link, i) => {
                        if (!link.url) {
                            return (
                                <span
                                    key={i}
                                    className="px-3 py-1 text-sm text-gray-400 cursor-not-allowed"
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
                                        : 'text-gray-700 hover:bg-gray-100'
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
