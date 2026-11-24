import { useState, useMemo } from 'react';

export default function MobileProductFilterSheet({
    open,
    onClose,
    categories = [],
    brands = [],
    selectedCategory,
    setSelectedCategory,
    selectedBrand,
    setSelectedBrand,
    selectedType,
    setSelectedType,
    minPrice,
    setMinPrice,
    maxPrice,
    setMaxPrice,
    sortBy,
    setSortBy,
    inStock,
    setInStock,
    onApply,
    onReset,
}) {
    const [openSection, setOpenSection] = useState(null);
    const [categorySearch, setCategorySearch] = useState('');
    const [brandSearch, setBrandSearch] = useState('');
    const [expandedParents, setExpandedParents] = useState({});

    const toggle = (key) => setOpenSection((prev) => (prev === key ? null : key));

    const rowClass = "w-full flex items-center justify-between gap-2 px-3 py-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800";

    const filteredCategories = useMemo(() => {
        const q = categorySearch.trim().toLowerCase();
        if (!q) return categories;
        return categories.filter((c) => (c.name || '').toLowerCase().includes(q));
    }, [categories, categorySearch]);

    const filteredBrands = useMemo(() => {
        const q = brandSearch.trim().toLowerCase();
        if (!q) return brands;
        return brands.filter((b) => (b.name || '').toLowerCase().includes(q));
    }, [brands, brandSearch]);

    if (!open) return null;

    // Build hierarchy
    const parents = categories.filter((c) => !c.parent_id);
    const childrenByParent = categories.reduce((acc, c) => {
        if (c.parent_id) {
            acc[c.parent_id] = acc[c.parent_id] || [];
            acc[c.parent_id].push(c);
        }
        return acc;
    }, {});

    const selectedCategoryName = categories.find((c) => String(c.id) === String(selectedCategory))?.name || 'All Categories';
    const selectedBrandName = brands.find((b) => String(b.id) === String(selectedBrand))?.name || 'All Brands';

    return (
        <div className="fixed inset-0 z-50 md:hidden">
            <div className="absolute inset-0 bg-black/40" onClick={onClose} />
            <div className="absolute bottom-0 left-0 right-0 bg-white dark:bg-gray-900 rounded-t-2xl max-h-[85vh] overflow-y-auto pb-[env(safe-area-inset-bottom)] box-border">
                <div className="sticky top-0 bg-white dark:bg-gray-900 px-4 py-3 border-b flex items-center justify-between">
                    <h3 className="font-semibold text-sm">Filter Products</h3>
                    <button onClick={onClose} className="text-sm text-gray-500">Close</button>
                </div>
                <div className="p-3 space-y-2 box-border">
                    <div>
                        <button onClick={() => toggle('category')} className={rowClass} aria-expanded={openSection === 'category'}>
                            <span className="text-sm font-medium">Category</span>
                            <span className="flex items-center gap-1 text-sm text-gray-600">
                                {selectedCategoryName}
                                <svg className={`w-4 h-4 transition-transform ${openSection === 'category' ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
                            </span>
                        </button>
                        {openSection === 'category' && (
                            <div className="mt-1">
                                <input value={categorySearch} onChange={(e) => setCategorySearch(e.target.value)} placeholder="Search categories..." className="w-full border rounded-lg px-2 py-2 text-sm mb-2" />
                                <div className="max-h-[220px] overflow-y-auto space-y-1">
                                    {filteredCategories.length === 0 && <p className="text-sm text-gray-500 px-2">No categories found</p>}
                                    {parents.map((parent) => {
                                        const children = childrenByParent[parent.id] || [];
                                        const visibleChildren = categorySearch ? children.filter((c) => (c.name || '').toLowerCase().includes(categorySearch.toLowerCase())) : children;
                                        const parentMatches = !categorySearch || (parent.name || '').toLowerCase().includes(categorySearch.toLowerCase());
                                        if (!parentMatches && visibleChildren.length === 0) return null;
                                        return (
                                            <div key={parent.id}>
                                                <button onClick={() => setSelectedCategory(String(parent.id))} className={`block w-full text-left px-2 py-1.5 text-sm rounded hover:bg-gray-100 ${String(selectedCategory) === String(parent.id) ? 'bg-blue-50 text-blue-700' : ''}`}>
                                                    {parent.name}
                                                </button>
                                                {children.length > 0 && (
                                                    <button onClick={() => setExpandedParents((prev) => ({ ...prev, [parent.id]: !prev[parent.id] }))} className="text-xs text-gray-500 px-2">
                                                        {expandedParents[parent.id] ? 'Hide' : 'Show'} subcategories
                                                    </button>
                                                )}
                                                {(expandedParents[parent.id] || categorySearch) && visibleChildren.map((child) => (
                                                    <button key={child.id} onClick={() => setSelectedCategory(String(child.id))} className={`block w-full text-left px-4 py-1.5 text-sm rounded hover:bg-gray-100 ${String(selectedCategory) === String(child.id) ? 'bg-blue-50 text-blue-700' : ''}`}>
                                                        {child.name}
                                                    </button>
                                                ))}
                                            </div>
                                        );
                                    })}
                                    {/* Flat categories without parent */}
                                    {filteredCategories.filter((c) => !c.parent_id && !parents.find((p) => p.id === c.id)).map((c) => (
                                        <button key={c.id} onClick={() => setSelectedCategory(String(c.id))} className={`block w-full text-left px-2 py-1.5 text-sm rounded hover:bg-gray-100 ${String(selectedCategory) === String(c.id) ? 'bg-blue-50 text-blue-700' : ''}`}>{c.name}</button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                    <div>
                        <button onClick={() => toggle('brand')} className={rowClass} aria-expanded={openSection === 'brand'}>
                            <span className="text-sm font-medium">Brand</span>
                            <span className="flex items-center gap-1 text-sm text-gray-600">
                                {selectedBrandName}
                                <svg className={`w-4 h-4 transition-transform ${openSection === 'brand' ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
                            </span>
                        </button>
                        {openSection === 'brand' && (
                            <div className="mt-1">
                                <div className="relative mb-2">
                                    <input value={brandSearch} onChange={(e) => setBrandSearch(e.target.value)} placeholder="Search brands..." className="w-full border rounded-lg px-2 py-2 text-sm" />
                                    {brandSearch && <button onClick={() => setBrandSearch('')} className="absolute right-2 top-2 text-xs text-gray-500">Clear</button>}
                                </div>
                                <div className="max-h-[220px] overflow-y-auto space-y-1">
                                    {filteredBrands.length === 0 && <p className="text-sm text-gray-500 px-2">No brands found</p>}
                                    {filteredBrands.map((b) => (
                                        <button key={b.id} onClick={() => setSelectedBrand(String(b.id))} className={`block w-full text-left px-2 py-1.5 text-sm rounded hover:bg-gray-100 ${String(selectedBrand) === String(b.id) ? 'bg-blue-50 text-blue-700' : ''}`}>{b.name}</button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                    <div>
                        <button onClick={() => toggle('type')} className={rowClass} aria-expanded={openSection === 'type'}>
                            <span className="text-sm font-medium">Product Type</span>
                            <span className="flex items-center gap-1 text-sm text-gray-600">
                                {selectedType || 'All Types'}
                                <svg className={`w-4 h-4 transition-transform ${openSection === 'type' ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
                            </span>
                        </button>
                        {openSection === 'type' && (
                            <div className="mt-1 space-y-1 pl-1">
                                {['', 'single', 'variable', 'combo'].map((t) => (
                                    <button key={t} onClick={() => setSelectedType(t)} className={`block w-full text-left px-2 py-1.5 text-sm rounded hover:bg-gray-100 ${selectedType === t ? 'bg-blue-50 text-blue-700' : ''}`}>{t || 'All Types'}</button>
                                ))}
                            </div>
                        )}
                    </div>
                    <div className="flex gap-2 min-w-0">
                        <input value={minPrice} onChange={(e) => setMinPrice(e.target.value)} placeholder="Min" className="flex-1 min-w-0 border rounded-lg px-2 py-2 text-sm" />
                        <input value={maxPrice} onChange={(e) => setMaxPrice(e.target.value)} placeholder="Max" className="flex-1 min-w-0 border rounded-lg px-2 py-2 text-sm" />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={inStock} onChange={(e) => setInStock(e.target.checked)} />
                        In Stock Only
                    </label>
                    <select value={sortBy} onChange={(e) => setSortBy(e.target.value)} className="w-full border rounded-lg px-2 py-2 text-sm">
                        <option value="recommended">Recommended</option>
                        <option value="newest">Newest</option>
                        <option value="price_asc">Price Low to High</option>
                        <option value="price_desc">Price High to Low</option>
                        <option value="name_asc">Name A-Z</option>
                    </select>
                </div>
                <div className="sticky bottom-0 bg-white dark:bg-gray-900 px-3 py-2 border-t flex gap-2">
                    <button onClick={onReset} className="flex-1 py-2 border rounded-lg text-sm">Reset</button>
                    <button onClick={onClose} className="flex-1 py-2 bg-blue-600 text-white rounded-lg text-sm">Close</button>
                </div>
            </div>
        </div>
    );
}