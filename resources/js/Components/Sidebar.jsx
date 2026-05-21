import { useCallback } from 'react';

function CategoryList({ categories, selectedCategory, onCategoryClick }) {
    return (
        <div className="space-y-0.5">
            <button
                onClick={() => onCategoryClick('')}
                className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-200 ${
                    !selectedCategory
                        ? 'bg-blue-50 text-blue-700 font-semibold'
                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                }`}
            >
                <svg className="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
                All Categories
            </button>

            {categories.map((cat) => (
                <button
                    key={cat.id}
                    onClick={() => onCategoryClick(cat.id)}
                    className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-200 ${
                        selectedCategory == cat.id
                            ? 'bg-blue-50 text-blue-700 font-semibold'
                            : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                    }`}
                >
                    <svg className="w-4 h-4 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <span className="truncate">{cat.name}</span>
                </button>
            ))}

            {categories.length === 0 && (
                <p className="text-sm text-gray-400 py-4 text-center">No categories available</p>
            )}
        </div>
    );
}

export default function Sidebar({ categories, selectedCategory, onCategoryChange, isOpen, onClose }) {
    const handleCategoryClick = useCallback((id) => {
        onCategoryChange(id);
        if (onClose) onClose();
    }, [onCategoryChange, onClose]);

    const list = (
        <CategoryList
            categories={categories}
            selectedCategory={selectedCategory}
            onCategoryClick={handleCategoryClick}
        />
    );

    return (
        <>
            {isOpen && (
                <div className="fixed inset-0 bg-black/50 z-40 lg:hidden" onClick={onClose} />
            )}

            <aside
                className={`fixed lg:hidden top-0 left-0 z-50 h-full w-72 bg-white shadow-xl transform transition-transform duration-300 ease-in-out overflow-y-auto ${
                    isOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex items-center justify-between p-4 border-b border-gray-200">
                    <h3 className="text-base font-semibold text-gray-900">Categories</h3>
                    <button onClick={onClose} className="p-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div className="p-4">
                    {list}
                </div>
            </aside>

            <div className="hidden lg:block w-64 flex-shrink-0">
                <div className="sticky top-20">
                    <div className="bg-white rounded-xl border border-gray-200 p-4">
                        <h3 className="text-sm font-semibold text-gray-900 mb-3 uppercase tracking-wide">
                            Categories
                        </h3>
                        {list}
                    </div>
                </div>
            </div>
        </>
    );
}
