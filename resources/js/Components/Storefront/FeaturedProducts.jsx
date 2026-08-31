import { usePage } from '@inertiajs/react';
import ProductCard from '@/Components/ProductCard';

export default function FeaturedProducts({ products, title, subtitle, variant = null, onAddToCart, onSelectVariant, addingId }) {
    const { storefront } = usePage().props;
    if (!products?.length) return null;
    const layout = variant || storefront?.design?.variants?.products || 'grid';
    const productVariant = layout === 'image-focused' ? 'image-focused' : layout === 'compact' ? 'compact' : storefront?.design?.product_cards?.variant;

    return (
        <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div className="text-center mb-3">
                <h2 className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-gray-100">{title || 'Featured Products'}</h2>
                {subtitle && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{subtitle}</p>}
            </div>
            <div className={layout === 'horizontal' ? 'flex gap-3 overflow-x-auto snap-x pb-2 sm:grid sm:grid-cols-3 lg:grid-cols-4 sm:overflow-visible' : 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4'}>
                {products.slice(0, 8).map((product) => (
                    <ProductCard
                        key={product.id}
                        product={product}
                        variant={productVariant}
                        onAddToCart={onAddToCart}
                        onSelectVariant={onSelectVariant}
                        addingId={addingId}
                    />
                ))}
            </div>
        </section>
    );
}
