import { usePage } from '@inertiajs/react';
import ProductCard from '@/Components/ProductCard';

export default function FeaturedProducts({ products, title, subtitle, onAddToCart, addingId }) {
    if (!products?.length) return null;

    return (
        <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div className="text-center mb-3">
                <h2 className="text-xl sm:text-2xl font-bold text-gray-900">{title || 'Featured Products'}</h2>
                {subtitle && <p className="mt-1 text-sm text-gray-500">{subtitle}</p>}
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                {products.slice(0, 8).map((product) => (
                    <ProductCard
                        key={product.id}
                        product={product}
                        onAddToCart={onAddToCart}
                        addingId={addingId}
                    />
                ))}
            </div>
        </section>
    );
}
