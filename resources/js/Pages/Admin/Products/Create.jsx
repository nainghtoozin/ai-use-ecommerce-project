import { useState } from 'react';
import { useForm, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import ImageUpload from '@/Components/ImageUpload';

export default function ProductCreate({ categories }) {
    const { data, setData, post, processing, errors, progress } = useForm({
        name: '',
        description: '',
        price: '',
        base_price: '',
        stock: 0,
        category_id: '',
        photo1: null,
        photo2: null,
    });

    const [photo1File, setPhoto1File] = useState(null);
    const [photo2File, setPhoto2File] = useState(null);

    function handleSubmit(e) {
        e.preventDefault();

        const formData = new FormData();
        formData.append('name', data.name);
        formData.append('description', data.description || '');
        formData.append('price', data.price);
        formData.append('base_price', data.base_price);
        formData.append('stock', data.stock);
        formData.append('category_id', data.category_id);

        if (photo1File) formData.append('photo1', photo1File);
        if (photo2File) formData.append('photo2', photo2File);

        router.post('/admin/products', formData, {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold text-gray-800">Add New Product</h2>}>
            <Head title="Add Product" />

            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="bg-white rounded-lg shadow-sm border border-gray-200">
                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                    {/* Product Name */}
                    <div>
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                            Product Name
                        </label>
                        <input
                            id="name"
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter product name"
                        />
                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                    </div>

                    {/* Description */}
                    <div>
                        <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1">
                            Description
                        </label>
                        <textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            rows="3"
                            className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                            placeholder="Optional"
                        />
                        {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                    </div>

                    {/* Price & Base Price */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label htmlFor="price" className="block text-sm font-medium text-gray-700 mb-1">
                                Price
                            </label>
                            <input
                                id="price"
                                type="number"
                                value={data.price}
                                onChange={(e) => setData('price', e.target.value)}
                                step="0.01"
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="0.00"
                            />
                            {errors.price && <p className="mt-1 text-sm text-red-600">{errors.price}</p>}
                        </div>

                        <div>
                            <label htmlFor="base_price" className="block text-sm font-medium text-gray-700 mb-1">
                                Base Price
                            </label>
                            <input
                                id="base_price"
                                type="number"
                                value={data.base_price}
                                onChange={(e) => setData('base_price', e.target.value)}
                                step="0.01"
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="0.00"
                            />
                            {errors.base_price && <p className="mt-1 text-sm text-red-600">{errors.base_price}</p>}
                        </div>
                    </div>

                    {/* Stock & Category */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label htmlFor="stock" className="block text-sm font-medium text-gray-700 mb-1">
                                Stock
                            </label>
                            <input
                                id="stock"
                                type="number"
                                value={data.stock}
                                onChange={(e) => setData('stock', e.target.value)}
                                min="0"
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                            {errors.stock && <p className="mt-1 text-sm text-red-600">{errors.stock}</p>}
                        </div>

                        <div>
                            <label htmlFor="category_id" className="block text-sm font-medium text-gray-700 mb-1">
                                Category
                            </label>
                            <select
                                id="category_id"
                                value={data.category_id}
                                onChange={(e) => setData('category_id', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">Select Category</option>
                                {categories.map((cat) => (
                                    <option key={cat.id} value={cat.id}>
                                        {cat.name}
                                    </option>
                                ))}
                            </select>
                            {errors.category_id && <p className="mt-1 text-sm text-red-600">{errors.category_id}</p>}
                        </div>
                    </div>

                    {/* Image Uploads */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <ImageUpload
                            name="photo1"
                            label="Photo 1"
                            onChange={(file) => setPhoto1File(file)}
                            error={errors.photo1}
                            maxSize={2}
                            previewSize="lg"
                        />
                        <ImageUpload
                            name="photo2"
                            label="Photo 2 (Optional)"
                            onChange={(file) => setPhoto2File(file)}
                            error={errors.photo2}
                            maxSize={2}
                            previewSize="lg"
                        />
                    </div>

                    {/* Submit */}
                    <div className="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <button
                            type="button"
                            onClick={() => router.visit('/admin/products')}
                            className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
                        >
                            {processing ? (
                                <>
                                    <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Saving...
                                </>
                            ) : (
                                <>
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                    Save Product
                                </>
                            )}
                        </button>
                    </div>
                </form>
            </div>
            </div>
        </AdminLayout>
    );
}
