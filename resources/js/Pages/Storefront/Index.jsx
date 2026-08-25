import { useState, useCallback } from 'react';
import { Head, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import BackToTopButton from '@/Components/BackToTopButton';
import StorefrontHero from '@/Components/Storefront/StorefrontHero';
import FeaturedCategories from '@/Components/Storefront/FeaturedCategories';
import FeaturedProducts from '@/Components/Storefront/FeaturedProducts';
import PromotionSection from '@/Components/Storefront/PromotionSection';
import StoreHighlights from '@/Components/Storefront/StoreHighlights';
import BrandStorySection from '@/Components/Storefront/BrandStorySection';
import CtaSection from '@/Components/Storefront/CtaSection';
import VariantSelectModal from '@/Components/VariantSelectModal';
import { useCart } from '@/Hooks/useCart';

const hasText = (value) => typeof value === 'string' ? value.trim().length > 0 : Boolean(value);

export default function StoreIndex({ tenant, previewMode = null }) {
    const { website_info, storefront } = usePage().props;
    const { addToCart, addingId } = useCart();
    const [variableProduct, setVariableProduct] = useState(null);
    const sections = storefront?.homepage?.sections || [];

    const handleAddToCart = useCallback(async (productId) => {
        await addToCart(productId, 1);
    }, [addToCart]);

    const handleSelectVariant = useCallback((product) => {
        setVariableProduct(product);
    }, []);

    const handleModalAddToCart = useCallback(async (variantId, quantity) => {
        if (!variableProduct) return;
        await addToCart(variableProduct.id, quantity, variantId);
        setVariableProduct(null);
    }, [variableProduct, addToCart]);

    const renderSection = (section) => {
        if (section.enabled === false || section.enabled === 0) return null;

        const data = section.data || {};
        const config = section.configuration || {};
        const visibility = section.desktop_visible === false && section.mobile_visible === false
            ? 'hidden'
            : section.desktop_visible === false
                ? 'lg:hidden'
                : section.mobile_visible === false
                    ? 'max-sm:hidden'
                    : '';
        const wrap = (content) => content ? (
            <div key={section.id || section.type} className={visibility}>{content}</div>
        ) : null;

        switch (section.type) {
            case 'hero':
                return wrap(<StorefrontHero store={tenant} websiteInfo={website_info} storefront={storefront} />);
            case 'promotion':
                return data.promotions?.some((promotion) => hasText(promotion.title)) ? wrap(<PromotionSection promotions={data.promotions} />) : null;
            case 'featured_categories':
                return data.categories?.some((category) => hasText(category.name)) ? wrap(<FeaturedCategories categories={data.categories} variant={section.variant !== 'default' ? section.variant : null} title={config.title || 'Categories'} description={config.description || ''} />) : null;
            case 'featured_products':
                return data.products?.length ? wrap(<FeaturedProducts products={data.products} variant={section.variant !== 'default' ? section.variant : null} title={config.title || 'Featured Products'} subtitle={config.description || ''} onAddToCart={handleAddToCart} onSelectVariant={handleSelectVariant} addingId={addingId} />) : null;
            case 'product_showcase':
                return data.products?.length ? wrap(<FeaturedProducts products={data.products} variant={section.variant !== 'default' ? section.variant : 'image-focused'} title={config.title || 'Product Showcase'} subtitle={config.description || ''} onAddToCart={handleAddToCart} onSelectVariant={handleSelectVariant} addingId={addingId} />) : null;
            case 'store_highlights':
                return data.items?.some((item) => hasText(item.title) || hasText(item.description)) ? wrap(<StoreHighlights items={data.items} />) : null;
            case 'brand_story':
                return hasText(data.description) ? wrap(<BrandStorySection data={data} variant={section.variant !== 'default' ? section.variant : null} />) : null;
            case 'cta':
                return hasText(data.title) ? wrap(<CtaSection data={data} variant={section.variant !== 'default' ? section.variant : null} />) : null;
            default:
                return null;
        }
    };

    return (
        <ShopLayout previewMode={previewMode}>
            <Head title={storefront?.identity?.site_title || storefront?.identity?.name || tenant.name} />
            {sections.slice().sort((a, b) => a.position - b.position).map(renderSection)}
            {variableProduct && (
                <VariantSelectModal
                    product={variableProduct}
                    onClose={() => setVariableProduct(null)}
                    onAddToCart={handleModalAddToCart}
                />
            )}
            <BackToTopButton />
        </ShopLayout>
    );
}
