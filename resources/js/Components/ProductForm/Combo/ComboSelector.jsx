import { useState } from 'react';
import { Search, X } from 'lucide-react';

export default function ComboSelector({ products, onSelect, excludeIds = [] }) {
    const [search, setSearch] = useState('');
    const [isOpen, setIsOpen] = useState(false);

    const filtered = products.filter((p) => {
        if (excludeIds.includes(p.id)) return false;
        if (!search) return true;
        return p.name.toLowerCase().includes(search.toLowerCase())
            || p.category_name?.toLowerCase().includes(search.toLowerCase());
    });

    function handleSelect(product, variant = null) {
        onSelect(product, variant);
        setSearch('');
        setIsOpen(false);
    }

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="w-full flex items-center gap-2 px-4 py-3 rounded-xl border-2 border-dashed border-gray-300 text-gray-500 hover:border-orange-400 hover:text-orange-600 hover:bg-orange-50/50 transition-all text-sm font-medium"
            >
                <Search className="w-4 h-4" />
                Add product to combo...
            </button>

            {isOpen && (
                <>
                    <div className="fixed inset-0 z-20" onClick={() => { setIsOpen(false); setSearch(''); }} />
                    <div className="absolute top-full left-0 right-0 mt-2 bg-white rounded-xl shadow-xl border border-gray-200 z-30 overflow-hidden">
                        <div className="p-3 border-b border-gray-100">
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search products..."
                                    className="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    autoFocus
                                />
                            </div>
                        </div>

                        <div className="max-h-72 overflow-y-auto">
                            {filtered.length === 0 ? (
                                <div className="px-4 py-8 text-center text-sm text-gray-500">
                                    No products found
                                </div>
                            ) : (
                                filtered.map((product) => (
                                    <div key={product.id} className="border-b border-gray-50 last:border-b-0">
                                        <button
                                            type="button"
                                            onClick={() => handleSelect(product)}
                                            className="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors text-left"
                                        >
                                            {product.photo1_url ? (
                                                <img src={product.photo1_url} alt={product.name} className="w-9 h-9 rounded-lg object-cover border border-gray-200" />
                                            ) : (
                                                <div className="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                                                    <Search className="w-4 h-4 text-gray-300" />
                                                </div>
                                            )}
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-gray-900 truncate">{product.name}</p>
                                                <p className="text-xs text-gray-500">
                                                    {product.type === 'variable' ? 'Variable' : 'Single'}
                                                    {product.category_name && ` · ${product.category_name}`}
                                                </p>
                                            </div>
                                            <span className="text-xs font-medium text-gray-600">{product.stock} in stock</span>
                                        </button>

                                        {product.type === 'variable' && product.variants?.length > 0 && (
                                            <div className="bg-gray-50/50 pl-12 pr-4 py-1 space-y-0.5">
                                                {product.variants.map((v) => (
                                                    <button
                                                        key={v.id}
                                                        type="button"
                                                        onClick={() => handleSelect(product, v)}
                                                        className="w-full flex items-center gap-2 px-3 py-1.5 rounded hover:bg-white transition-colors text-left"
                                                    >
                                                        <span className="text-xs text-gray-500 truncate flex-1">{v.label}</span>
                                                        <span className="text-xs text-gray-400">{v.stock} stk</span>
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
