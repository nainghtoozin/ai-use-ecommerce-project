import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { useCart } from '@/Hooks/useCart';

export default function StoreShow({ tenant, product, promotion, detail }) {
    const { addToCart, addingId } = useCart();
    const [selectedVariantId, setSelectedVariantId] = useState(null);
    const [quantity, setQuantity] = useState(1);
    const [added, setAdded] = useState(false);

    const currentPrice = product.is_variable && selectedVariantId
        ? product.variants.find(v => v.id === Number(selectedVariantId))?.price || product.price
        : promotion?.promotion_price ?? product.price;

    const currency = ' MMK';

    const handleAddToCart = async () => {
        await addToCart(product.id, quantity, selectedVariantId || undefined);
        setAdded(true);
        setTimeout(() => setAdded(false), 2000);
    };

    return (
        <ShopLayout>
            <Head title={`${product.name} - ${tenant.name}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <nav className="flex mb-8 text-sm text-gray-500">
                    <Link href={`/store/${tenant.slug}`} className="hover:text-indigo-600">{tenant.name}</Link>
                    <span className="mx-2">/</span>
                    {product.category && (
                        <>
                            <Link href={`/store/${tenant.slug}/products?category=${product.category.id}`} className="hover:text-indigo-600">
                                {product.category.name}
                            </Link>
                            <span className="mx-2">/</span>
                        </>
                    )}
                    <span className="text-gray-900">{product.name}</span>
                </nav>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        {product.photo1 ? (
                            <img
                                src={`/storage/${product.photo1}`}
                                alt={product.name}
                                className="w-full rounded-lg object-cover"
                            />
                        ) : (
                            <div className="flex h-96 items-center justify-center rounded-lg bg-gray-100 text-gray-400">
                                No image
                            </div>
                        )}
                    </div>

                    <div>
                        {product.category && (
                            <p className="text-sm text-indigo-600 font-medium">{product.category.name}</p>
                        )}
                        {product.brand && (
                            <p className="text-xs text-gray-500 mt-1">{product.brand.name}</p>
                        )}
                        <h1 className="mt-2 text-2xl font-bold text-gray-900">{product.name}</h1>

                        <p className="mt-4 text-3xl font-bold text-gray-900">
                            {Number(currentPrice).toLocaleString()}{currency}
                        </p>

                        {promotion && (
                            <p className="mt-1 text-sm text-green-600 font-medium">
                                Save {promotion.promotion_type === 'percentage' ? `${promotion.discount_value}%` : `${promotion.discount_value} MMK`}
                            </p>
                        )}

                        {product.description && (
                            <p className="mt-4 text-gray-600">{product.description}</p>
                        )}

                        {product.is_variable && product.variants?.length > 0 && (
                            <div className="mt-6">
                                <p className="text-sm font-medium text-gray-900 mb-2">Options</p>
                                <div className="flex flex-wrap gap-2">
                                    {product.variants.map((variant) => (
                                        <button
                                            key={variant.id}
                                            onClick={() => setSelectedVariantId(variant.id)}
                                            className={`px-4 py-2 rounded-lg border text-sm font-medium ${
                                                selectedVariantId === variant.id
                                                    ? 'border-indigo-600 bg-indigo-50 text-indigo-700'
                                                    : 'border-gray-300 text-gray-700 hover:border-gray-400'
                                            }`}
                                        >
                                            {variant.name}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="mt-6 flex items-center gap-4">
                            <div className="flex items-center border rounded-lg">
                                <button
                                    onClick={() => setQuantity(q => Math.max(1, q - 1))}
                                    className="px-3 py-2 text-gray-600 hover:text-gray-900"
                                >
                                    -
                                </button>
                                <span className="px-4 py-2 text-gray-900 font-medium">{quantity}</span>
                                <button
                                    onClick={() => setQuantity(q => q + 1)}
                                    className="px-3 py-2 text-gray-600 hover:text-gray-900"
                                >
                                    +
                                </button>
                            </div>

                            <button
                                onClick={handleAddToCart}
                                disabled={addingId === product.id}
                                className="flex-1 rounded-lg bg-indigo-600 px-6 py-3 text-white font-medium hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {added ? 'Added to Cart!' : addingId === product.id ? 'Adding...' : 'Add to Cart'}
                            </button>
                        </div>

                        {detail && (
                            <div className="mt-8 border-t pt-6">
                                <h3 className="text-sm font-medium text-gray-900">Details</h3>
                                <p className="mt-2 text-sm text-gray-600">{detail}</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
