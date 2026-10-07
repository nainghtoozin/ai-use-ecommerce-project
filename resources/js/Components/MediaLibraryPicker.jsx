import { useEffect, useRef, useState } from 'react';
import { adminUrl } from '@/Utils/adminUrl';

const PAGE_SIZE = 24;

export default function MediaLibraryPicker({ open, onClose, media = [], selectedId = null, onSelect, excludeIds = [] }) {
    const [search, setSearch] = useState('');
    const [items, setItems] = useState(media || []);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState((media || []).length);
    const [loading, setLoading] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);
    const [picked, setPicked] = useState(selectedId);
    const [serverMode, setServerMode] = useState(false);
    const debounceRef = useRef(null);
    const openRef = useRef(false);
    const seenRef = useRef(new Map());

    useEffect(() => {
        setPicked(selectedId);
    }, [selectedId, open]);

    useEffect(() => {
        if (open && !openRef.current) {
            openRef.current = true;
            setSearch('');
            seenRef.current = new Map((media || []).map((m) => [String(m.id), m]));
            fetchPage(1, '', true);
        }
        if (!open) {
            openRef.current = false;
        }
    }, [open]);

    function fetchPage(pageNumber, query, replace) {
        const params = new URLSearchParams({
            q: query,
            page: String(pageNumber),
            per_page: String(PAGE_SIZE),
        });
        if (replace) setLoading(true);
        else setLoadingMore(true);
        fetch(adminUrl(`/admin/storefront/media/search?${params.toString()}`), {
            credentials: 'include',
            headers: { Accept: 'application/json' },
        })
            .then((response) => {
                if (!response.ok) throw new Error('Search failed');
                return response.json();
            })
            .then((data) => {
                setServerMode(true);
                const rows = data.data || [];
                rows.forEach((m) => seenRef.current.set(String(m.id), m));
                setItems((prev) => (replace ? rows : [...prev, ...rows]));
                setPage(data.current_page || pageNumber);
                setLastPage(data.last_page || pageNumber);
                setTotal(data.total ?? 0);
            })
            .catch(() => {
                if (replace) {
                    setServerMode(false);
                    setItems(media || []);
                    setTotal((media || []).length);
                }
            })
            .finally(() => {
                setLoading(false);
                setLoadingMore(false);
            });
    }

    function handleSearchChange(value) {
        setSearch(value);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => fetchPage(1, value.trim(), true), 300);
    }

    useEffect(() => () => { if (debounceRef.current) clearTimeout(debounceRef.current); }, []);

    if (!open) return null;

    const excluded = new Set((excludeIds || []).map(String));
    const query = search.trim().toLowerCase();
    const visible = (serverMode ? items : (items || []).filter((item) => {
        if (!query) return true;
        return (item.original_name || '').toLowerCase().includes(query)
            || (item.alt_text || '').toLowerCase().includes(query);
    })).filter((item) => !excluded.has(String(item.id)));

    function confirm() {
        const item = seenRef.current.get(String(picked)) || (media || []).find((m) => String(m.id) === String(picked));
        if (item && onSelect) onSelect(item);
        onClose();
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />
            <div className="relative bg-white dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden">
                <div className="px-5 pt-5 pb-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                    <div>
                        <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">Choose from Media Library</h3>
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {serverMode && total > 0 ? `${total} image${total !== 1 ? 's' : ''}` : 'Select a tenant-owned image.'}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Close media library"
                        className="p-2 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors flex-shrink-0"
                    >
                        <i className="bi bi-x-lg"></i>
                    </button>
                </div>
                <div className="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => handleSearchChange(e.target.value)}
                        placeholder="Search by filename..."
                        className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    />
                </div>
                <div className="flex-1 overflow-y-auto p-4">
                    {loading ? (
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            {[1, 2, 3, 4, 5, 6].map((i) => (
                                <div key={i} className="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                                    <div className="aspect-square bg-gray-100 dark:bg-gray-800 animate-pulse" />
                                    <div className="px-2 py-1.5"><div className="h-3 bg-gray-100 dark:bg-gray-800 rounded animate-pulse" /></div>
                                </div>
                            ))}
                        </div>
                    ) : visible.length > 0 ? (
                        <>
                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                {visible.map((item) => {
                                    const isPicked = String(picked) === String(item.id);
                                    const isCurrent = String(selectedId) === String(item.id);
                                    return (
                                        <button
                                            key={item.id}
                                            type="button"
                                            onClick={() => setPicked(item.id)}
                                            className={`relative rounded-xl border overflow-hidden text-left transition-all ${
                                                isPicked
                                                    ? 'border-blue-600 ring-2 ring-blue-500'
                                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                                            }`}
                                        >
                                            <div className="aspect-square bg-gray-100 dark:bg-gray-800">
                                                {item.url ? (
                                                    <img src={item.url} alt={item.alt_text || item.original_name || ''} className="w-full h-full object-cover" loading="lazy" />
                                                ) : (
                                                    <div className="w-full h-full flex items-center justify-center">
                                                        <i className="bi bi-image text-2xl text-gray-300"></i>
                                                    </div>
                                                )}
                                            </div>
                                            <div className="px-2 py-1.5 bg-white dark:bg-gray-900">
                                                <p className="text-xs text-gray-700 dark:text-gray-300 truncate" title={item.original_name}>{item.original_name || 'Untitled'}</p>
                                                {isCurrent && !isPicked && (
                                                    <p className="text-[11px] text-green-600 font-medium">Current</p>
                                                )}
                                                {isPicked && (
                                                    <p className="text-[11px] text-blue-600 font-medium">Selected</p>
                                                )}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                            {serverMode && page < lastPage && (
                                <div className="mt-4 text-center">
                                    <button
                                        type="button"
                                        onClick={() => fetchPage(page + 1, search.trim(), false)}
                                        disabled={loadingMore}
                                        className="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-50 transition-colors"
                                    >
                                        {loadingMore ? 'Loading...' : 'Load more'}
                                    </button>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="text-center py-10">
                            <i className="bi bi-images text-3xl text-gray-300"></i>
                            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                {query ? 'No media matches this search.' : 'No media uploaded yet. Use Upload New instead.'}
                            </p>
                        </div>
                    )}
                </div>
                <div className="px-5 py-3.5 border-t border-gray-100 dark:border-gray-800 flex justify-end gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={confirm}
                        disabled={!picked}
                        className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
                    >
                        Use Selected
                    </button>
                </div>
            </div>
        </div>
    );
}
