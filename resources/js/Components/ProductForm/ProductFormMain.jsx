import { useState } from 'react';
import CollapseCard from '@/Components/CollapseCard';
import BasicInfoSection from './sections/BasicInfoSection';
import DescriptionSection from './sections/DescriptionSection';
import MediaSection from './sections/MediaSection';
import SEOSection from './sections/SEOSection';
import VariantSection from './Variants/VariantSection';
import ComboBuilder from './Combo/ComboBuilder';

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

    const isVariable = data.product_type === 'variable';
    const isCombo = data.product_type === 'combo';

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
