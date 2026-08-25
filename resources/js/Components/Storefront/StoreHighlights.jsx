export default function StoreHighlights({ items = [] }) {
    if (!items.length) return null;
    return <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4"><div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">{items.map((item, index) => <div key={`${item.title}-${index}`} style={{ borderRadius: 'var(--storefront-radius-card, 0.75rem)' }} className="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4"><div className="text-xl" style={{ color: 'var(--theme-color, #3B82F6)' }}>{icon(item.icon)}</div><h3 className="mt-2 font-semibold text-gray-900 dark:text-gray-100">{item.title}</h3>{item.description && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{item.description}</p>}</div>)}</div></section>;
}

function icon(name) { return { truck: '↗', shield: '✓', headset: '◌', heart: '♥', star: '★' }[name] || '★'; }
