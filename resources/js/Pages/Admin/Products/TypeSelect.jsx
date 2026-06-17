import { router, Head, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import ProductTypeSelector from '@/Components/ProductType/ProductTypeSelector';

export default function ProductTypeSelect({
    availableTypes = ['single'],
    allTypes = ['single', 'variable', 'combo'],
    featureStatus = {},
}) {
    const { auth } = usePage().props;
    if (!auth?.user?.permissions?.includes('products.create')) {
        return <AdminLayout><div className="text-center py-16"><p className="text-red-600 font-semibold">Unauthorized</p></div></AdminLayout>;
    }
    const handleTypeSelect = (type) => {
        router.get(adminUrl(`/admin/products/create?type=${type}`));
    };

    const handleBack = () => {
        router.visit(adminUrl('/admin/products'));
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
