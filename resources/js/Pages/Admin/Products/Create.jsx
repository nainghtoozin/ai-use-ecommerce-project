import { useEffect } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import ProductFormMain from '@/Components/ProductForm/ProductFormMain';
import SidebarSection from '@/Components/ProductForm/SidebarSection';
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

export default function ProductCreate({ categories, units = [], brands = [], productType = 'single', selectableProducts = [] }) {
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

    useEffect(() => {
        setData('product_type', productType);
    }, [productType]);

    return (
        <AdminLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h2 className="text-xl font-semibold text-gray-800">Add New Product</h2>
                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${TYPE_STYLES[productType] || TYPE_STYLES.single}`}>
                                {TYPE_LABELS[productType] || 'Single Product'}
                            </span>
                        </div>
                        <p className="text-sm text-gray-500 mt-0.5">Create a new product for your store</p>
                    </div>
                </div>
            }
        >
            <Head title="Add Product" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
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
            </div>
        </AdminLayout>
    );
}
