import { useEffect } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import ProductFormMain from '@/Components/ProductForm/ProductFormMain';
import SidebarSection from '@/Components/ProductForm/SidebarSection';
import ProductTypeSelector from '@/Components/ProductType/ProductTypeSelector';
import useProductForm from '@/Components/ProductForm/useProductForm';

const TYPE_LABELS = {
    single: 'Single Product',
    variable: 'Variable Product',
    combo: 'Combo Product',
};

const TYPE_STYLES = {
    single: 'bg-blue-100 text-blue-700',
    variable: 'bg-purple-100 text-purple-700',
    combo: 'bg-orange-100 text-orange-700',
};

export default function ProductCreate({
    categories,
    units = [],
    brands = [],
    productType = null,
    selectableProducts = [],
    availableTypes = ['single'],
    allTypes = ['single', 'variable', 'combo'],
    featureStatus = {},
    warehouses = [],
}) {
    const { auth } = usePage().props;
    if (!auth?.user?.permissions?.includes('products.create')) {
        return <AdminLayout><div className="text-center py-16"><p className="text-red-600 font-semibold">Unauthorized</p></div></AdminLayout>;
    }

    const {
        formData,
        setData,
        variants,
        setVariants,
        comboItems,
        setComboItems,
        photo1File,
        setPhoto1File,
        photo2File,
        setPhoto2File,
        galleryFiles,
        setGalleryFiles,
        removedGalleryImages,
        setRemovedGalleryImages,
        seoImageFile,
        setSeoImageFile,
        removeSeoImage,
        setRemoveSeoImage,
        errors,
        processing,
        submit,
        cancel,
    } = useProductForm({ productType });

    const effectiveType = formData.product_type || productType || 'single';
    const typeLabel = TYPE_LABELS[effectiveType] || 'Single Product';
    const typeStyle = TYPE_STYLES[effectiveType] || TYPE_STYLES.single;

    const handleTypeSelect = (type) => {
        setData('product_type', type);
    };

    const showInlineTypeSelector = !productType && !formData.product_type;

    return (
        <AdminLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                                {showInlineTypeSelector ? 'Add New Product' : 'Add New Product'}
                            </h2>
                            {!showInlineTypeSelector && (
                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${typeStyle}`}>
                                    {typeLabel}
                                </span>
                            )}
                        </div>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            {showInlineTypeSelector
                                ? 'Select a product type to get started'
                                : 'Create a new product for your store'}
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Add Product" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                {showInlineTypeSelector ? (
                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden mb-6">
                        <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                            <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">Choose Product Type</h3>
                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Select the type that best describes your product.</p>
                        </div>
                        <div className="p-6">
                            <ProductTypeSelector
                                onSelect={handleTypeSelect}
                                availableTypes={availableTypes}
                                allTypes={allTypes}
                                featureStatus={featureStatus}
                                inline
                                initialType={null}
                            />
                        </div>
                    </div>
                ) : (
                    <form onSubmit={(e) => { e.preventDefault(); submit(); }}>
                        <div className="flex flex-col lg:flex-row gap-6">
                            <div className="flex-1 min-w-0">
                                <ProductFormMain
                                    data={formData}
                                    setData={setData}
                                    errors={errors}
                                    photo1File={photo1File}
                                    setPhoto1File={setPhoto1File}
                                    photo2File={photo2File}
                                    setPhoto2File={setPhoto2File}
                                    galleryFiles={galleryFiles}
                                    setGalleryFiles={setGalleryFiles}
                                    removedGalleryImages={removedGalleryImages}
                                    setRemovedGalleryImages={setRemovedGalleryImages}
                                    seoImageFile={seoImageFile}
                                    setSeoImageFile={setSeoImageFile}
                                    removeSeoImage={removeSeoImage}
                                    setRemoveSeoImage={setRemoveSeoImage}
                                    variants={variants}
                                    setVariants={setVariants}
                                    comboItems={comboItems}
                                    setComboItems={setComboItems}
                                    selectableProducts={selectableProducts}
                                    isEdit={false}
                                />
                            </div>

                            <div className="w-full lg:w-64 flex-shrink-0">
                                <SidebarSection
                                    processing={processing}
                                    onSubmit={(e) => { e?.preventDefault?.(); submit(); }}
                                    onCancel={cancel}
                                    data={formData}
                                    photo1File={photo1File}
                                    variants={variants}
                                />
                            </div>
                        </div>
                    </form>
                )}
            </div>
        </AdminLayout>
    );
}
