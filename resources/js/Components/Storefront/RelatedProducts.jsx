import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';

function safeNum(val) {
    const n = Number(val);
    return Number.isFinite(n) ? n : 0;
}

function ProductCard({ product }) {
    const { storefront, tenant } = usePage().props;
    const cc = getCurrencyConfig(usePage().props.platform_setting, usePage().props.website_info);
    const price = safeNum(product.promotion_price ?? product.price);
    const originalPrice = safeNum(product.promotion?.original_price ?? 0);
    const hasDiscount = originalPrice > price && originalPrice > 0;
    const discountPct = hasDiscount ? Math.round(((originalPrice - price) / originalPrice) * 100) : 0;
    const effectiveStock = safeNum(product.effective_stock ?? product.stock ?? 0);
    const [imageError, setImageError] = useState(false);

    const imageUrl = product.photo1_url && !imageError ? assetUrl(product.photo1_url) : null;

    return (
        <Link
            href={`/store/${tenant?.slug || ''}/products/${product.id}`}
            className="group block bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800 overflow-hidden transition-all hover:shadow-md hover:border-gray-200"
        >
            <div className="relative aspect-square bg-gray-50 dark:bg-gray-950 overflow-hidden">
                {imageUrl ? (
                    <img
                        src={imageUrl}
                        alt={product.name}
                        className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                        onError={() => setImageError(true)}
                    />
                ) : (
                    <div className="w-full h-full flex items-center justify-center">
                        <svg className="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                )}
                {hasDiscount && (
                    <div className="absolute top-2 right-2 px-1.5 py-0.5 bg-red-500 text-white text-[10px] font-bold rounded">
                        -{discountPct}%
                    </div>
                )}
                {product.is_combo && (
                    <div className="absolute top-2 left-2 px-1.5 py-0.5 bg-purple-500 text-white text-[10px] font-bold rounded">
                        Bundle
                    </div>
                )}
                {product.is_variable && !product.is_combo && (
                    <div className="absolute top-2 left-2 px-1.5 py-0.5 bg-blue-500 text-white text-[10px] font-bold rounded">
                        Options
                    </div>
                )}
            </div>
            <div className="p-3">
                <p className="text-xs text-gray-500 dark:text-gray-400 mb-0.5 truncate">
                    {product.category?.name || 'Uncategorized'}
                </p>
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 line-clamp-2 leading-snug">
                    {product.name}
                </h3>
                <div className="flex items-center justify-between mt-2">
                    <span className="text-sm font-bold text-gray-900 dark:text-gray-100">
                        {formatCurrency(price, cc)}
                    </span>
                    {effectiveStock <= 0 ? (
                        <span className="text-[10px] text-red-500 font-medium">Out of Stock</span>
                    ) : effectiveStock < 10 ? (
                        <span className="text-[10px] text-amber-500 font-medium">Only {effectiveStock} left</span>
                    ) : (
                        <span className="text-[10px] text-green-500 font-medium">In Stock</span>
                    )}
                </div>
            </div>
        </Link>
    );
}

export default function RelatedProducts({ products = [], title }) {
    if (!products || products.length === 0) {
        return null;
    }

    return (
        <section className="mt-8 lg:mt-12">
            <h2 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                {title || 'Related Products'}
            </h2>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                {products.map((product) => (
                    <ProductCard key={product.id} product={product} />
                ))}
            </div>
        </section>
    );
}
