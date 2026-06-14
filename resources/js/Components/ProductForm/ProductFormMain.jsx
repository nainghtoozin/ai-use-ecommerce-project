import { useState } from 'react';
import CollapseCard from '@/Components/CollapseCard';
import BasicInfoSection from './sections/BasicInfoSection';
import PricingSection from './sections/PricingSection';
import InventorySection from './sections/InventorySection';
import DescriptionSection from './sections/DescriptionSection';
import MediaSection from './sections/MediaSection';
import SEOSection from './sections/SEOSection';
import VariantSection from './Variants/VariantSection';
import ComboBuilder from './Combo/ComboBuilder';
import ComboSummary from './Combo/ComboSummary';
import ComboPricingCard from './Combo/ComboPricingCard';

export default function ProductFormMain({
    data,
    setData,
    errors,
    photo1File,
    setPhoto1File,
    photo2File,
    setPhoto2File,
    variants,
    setVariants,
    existingPhoto1Url,
    existingPhoto2Url,
    comboItems = [],
    setComboItems,
    selectableProducts = [],
}) {
    const [mediaOpen, setMediaOpen] = useState(false);
    const [descOpen, setDescOpen] = useState(false);
    const [seoOpen, setSeoOpen] = useState(false);

    const isSingle = data.product_type === 'single';
    const isVariable = data.product_type === 'variable';
    const isCombo = data.product_type === 'combo';

    const comboItemsWithSubtotals = comboItems.map((item) => ({
        ...item,
        subtotal: (item.unit_price || 0) * (item.quantity || 1),
    }));

    const estimatedCost = comboItemsWithSubtotals.reduce((sum, item) => sum + (item.subtotal || 0), 0);
    const descCharCount = (data.description || '').length;
    const hasSeoData = data.meta_title || data.meta_description;

    return (
        <div className="space-y-6">
            <BasicInfoSection
                data={data}
                setData={setData}
                errors={errors}
                photo1File={photo1File}
                setPhoto1File={setPhoto1File}
                existingPhoto1Url={existingPhoto1Url}
            />

            {isSingle && (
                <>
                    <PricingSection
                        data={data}
                        setData={setData}
                        errors={errors}
                    />
                    <InventorySection
                        data={data}
                        setData={setData}
                        errors={errors}
                    />
                </>
            )}

            {isVariable && (
                <VariantSection
                    variants={variants}
                    setVariants={setVariants}
                />
            )}

            {isCombo && (
                <ComboBuilder
                    items={comboItems}
                    setItems={setComboItems}
                    selectableProducts={selectableProducts}
                    comboPrice={parseFloat(data.price) || 0}
                    existingComboItems={data.existing_combo_items || []}
                    hideSummary={true}
                />
            )}

            {isCombo && comboItemsWithSubtotals.length > 0 && (
                <ComboSummary
                    items={comboItemsWithSubtotals}
                    comboPrice={parseFloat(data.price) || 0}
                />
            )}

            {isCombo && (
                <ComboPricingCard
                    price={data.price}
                    onPriceChange={(val) => setData('price', val)}
                    estimatedCost={estimatedCost}
                    error={errors.price}
                />
            )}

            <CollapseCard
                title="Additional Media"
                isOpen={mediaOpen}
                onToggle={() => setMediaOpen(!mediaOpen)}
            >
                <MediaSection
                    errors={errors}
                    photo1File={photo1File}
                    setPhoto1File={setPhoto1File}
                    photo2File={photo2File}
                    setPhoto2File={setPhoto2File}
                    existingPhoto1Url={existingPhoto1Url}
                    existingPhoto2Url={existingPhoto2Url}
                />
            </CollapseCard>

            {!isCombo && (
                <CollapseCard
                    title="Description"
                    subtitle={descCharCount > 0 ? `${descCharCount} characters` : 'No description added yet'}
                    isOpen={descOpen}
                    onToggle={() => setDescOpen(!descOpen)}
                >
                    <DescriptionSection
                        data={data}
                        setData={setData}
                        errors={errors}
                    />
                </CollapseCard>
            )}

            <CollapseCard
                title="Search Engine Listing"
                subtitle={hasSeoData ? 'SEO data added' : 'No SEO data'}
                isOpen={seoOpen}
                onToggle={() => setSeoOpen(!seoOpen)}
            >
                <SEOSection
                    data={data}
                    setData={setData}
                    errors={errors}
                />
            </CollapseCard>
        </div>
    );
}
