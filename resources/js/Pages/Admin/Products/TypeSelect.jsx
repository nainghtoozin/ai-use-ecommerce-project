import { router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import ProductTypeSelector from '@/Components/ProductType/ProductTypeSelector';

export default function ProductTypeSelect({
    availableTypes = ['single'],
    allTypes = ['single', 'variable', 'combo'],
    featureStatus = {},
}) {
    const handleTypeSelect = (type) => {
        router.get(`/admin/products/create?type=${type}`);
    };

    const handleBack = () => {
        router.visit('/admin/products');
    };

    return (
        <AdminLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold text-gray-800">Add New Product</h2>
                </div>
            }
        >
            <Head title="Choose Product Type" />

            <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <ProductTypeSelector
                    onSelect={handleTypeSelect}
                    onBack={handleBack}
                    availableTypes={availableTypes}
                    allTypes={allTypes}
                    featureStatus={featureStatus}
                />
            </div>
        </AdminLayout>
    );
}
