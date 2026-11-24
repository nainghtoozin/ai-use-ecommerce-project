import { useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { useBuyNow, buyNowKey } from '@/Hooks/useBuyNow';
import { useWishlist } from '@/Hooks/useWishlist';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import { assetUrl } from '@/Utils/helpers';

export default function MobileProductCard({ product, onAddToCart, onSelectVariant, addingId }) {
    const { props } = usePage();
    const { auth, tenant, wishlisted_ids = [] } = props;
    const cc = getCurrencyConfig(props.platform_setting, props.website_info);
    const { buyNow, buyingKey } = useBuyNow();
    const { toggleWishlist } = useWishlist();
    const [isAdding, setIsAdding] = useState(false);
    const [optimisticWishlisted, setOptimisticWishlisted] = useState(wishlisted_ids.includes(product.id));

    const productUrl = tenant?.slug ? `/store/${tenant.slug}/products/${product.id}` : `/client/product/${product.id}`;
    const isVariable = !!product.is_variable;
    const isOutOfStock = product.stock_status === 'out_of_stock' || (product.stock !== undefined && Number(product.stock) <= 0 && !isVariable);

    const handleAddToCart = async (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (addingId === product.id || isAdding) return;
        if (isVariable) {
            if (onSelectVariant) onSelectVariant(product);
            return;
        }
        setIsAdding(true);
        if (onAddToCart) await onAddToCart(product.id, { name: product.name, image: product.photo1_url || null });
        setIsAdding(false);
    };

    const handleWishlist = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (!auth?.user) {
            router.visit(tenant?.slug ? `/store/${tenant.slug}/login` : '/login');
            return;
        }
        setOptimisticWishlisted((prev) => !prev);
        toggleWishlist(product.id, optimisticWishlisted);
    };

    const handleBuyNow = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (isOutOfStock) return;
        if (isVariable) {
            if (onSelectVariant) onSelectVariant(product);
            return;
        }
        buyNow({ productId: product.id, quantity: 1 });
    };

    const image = product.photo1_url || product.photo1 || '';

    return (
        <div className="flex gap-3 p-3 bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm">
            <Link href={productUrl} className="flex-shrink-0">
                <div className="w-24 h-24 rounded-lg bg-gray-50 dark:bg-gray-800 overflow-hidden flex items-center justify-center">
                    {image ? (
                        <img src={assetUrl(image, false)} alt={product.name} className="w-full h-full object-contain" />
                    ) : (
                        <span className="text-xs text-gray-400">No image</span>
                    )}
                </div>
            </Link>
            <div className="flex-1 min-w-0">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        {product.category?.name && <p className="text-[10px] uppercase tracking-wide text-gray-400 truncate">{product.category.name}</p>}
                        <Link href={productUrl}>
                            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 line-clamp-2">{product.name}</h3>
                        </Link>
                    </div>
                    <button onClick={handleWishlist} className="flex-shrink-0 w-8 h-8 rounded-full border border-gray-200 bg-white flex items-center justify-center shadow-sm" aria-label="Wishlist">
                        <svg className={`w-4 h-4 ${optimisticWishlisted ? 'text-red-500 fill-red-500' : 'text-gray-600'}`} fill={optimisticWishlisted ? 'currentColor' : 'none'} stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </button>
                </div>
                {product.brand?.name && <p className="text-[11px] text-gray-500 truncate">{product.brand.name}</p>}
                <div className="mt-1 flex items-center gap-2 flex-wrap">
                    {product.promotion_badge && (
                        <span className="text-[9px] font-bold px-1.5 py-0.5 rounded bg-red-500 text-white">{product.promotion_badge}</span>
                    )}
                    <span className="text-sm font-bold text-gray-900 dark:text-gray-100">{formatCurrency(product.price, cc)}</span>
                    {product.original_price && Number(product.original_price) > Number(product.price) && (
                        <span className="text-[10px] text-gray-400 line-through">{formatCurrency(product.original_price, cc)}</span>
                    )}
                </div>
                {product.stock_status && <p className="text-[10px] text-gray-500 mt-0.5">{product.stock_status}</p>}
                <div className="mt-2 flex gap-2">
                    <button
                        onClick={handleAddToCart}
                        disabled={addingId === product.id || isAdding}
                        className="flex-1 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white disabled:opacity-50"
                    >
                        {addingId === product.id || isAdding ? 'Adding...' : 'Add to Cart'}
                    </button>
                    <button
                        onClick={handleBuyNow}
                        disabled={buyingKey === buyNowKey(product.id, null)}
                        className="flex-1 py-1.5 text-xs font-semibold rounded-lg border border-blue-600 text-blue-600 disabled:opacity-50"
                    >
                        {buyingKey === buyNowKey(product.id, null) ? '...' : 'Buy Now'}
                    </button>
                </div>
            </div>
        </div>
    );
}