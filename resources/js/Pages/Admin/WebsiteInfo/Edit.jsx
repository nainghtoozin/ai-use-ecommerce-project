import { useState } from 'react';
import { useForm, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import ImageUpload from '@/Components/ImageUpload';
import { assetUrl } from '@/Utils/helpers';

export default function WebsiteInfoEdit({ info }) {
    const { data, setData, post, processing, errors } = useForm({
        name: info?.name || '',
        hero_title: info?.hero_title || '',
        hero_description: info?.hero_description || '',
        about_description: info?.about_description || '',
        phone: info?.phone || '',
        email: info?.email || '',
        address: info?.address || '',
        currency: info?.currency || 'MMK',
        shipping_fee: info?.shipping_fee || 0,
        free_shipping_threshhold: info?.free_shipping_threshhold || 0,
        shipping_info: info?.shipping_info || '',
        secure_payment_info: info?.secure_payment_info || '',
        easy_returns_info: info?.easy_returns_info || '',
        logo: null,
    });

    const [logoFile, setLogoFile] = useState(null);

    function handleSubmit(e) {
        e.preventDefault();

        const formData = new FormData();
        formData.append('name', data.name);
        formData.append('hero_title', data.hero_title);
        formData.append('hero_description', data.hero_description);
        formData.append('about_description', data.about_description);
        formData.append('phone', data.phone);
        formData.append('email', data.email);
        formData.append('address', data.address);
        formData.append('currency', data.currency);
        formData.append('shipping_fee', data.shipping_fee);
        formData.append('free_shipping_threshhold', data.free_shipping_threshhold);
        formData.append('shipping_info', data.shipping_info);
        formData.append('secure_payment_info', data.secure_payment_info);
        formData.append('easy_returns_info', data.easy_returns_info);

        if (logoFile) formData.append('logo', logoFile);

        router.post('/admin/website-info/update', formData, {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold text-gray-800">Website Information</h2>}>
            <Head title="Website Settings" />

            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div className="bg-white rounded-lg shadow-sm border border-gray-200">
                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                    {/* Logo */}
                    <ImageUpload
                        name="logo"
                        label="Site Logo"
                        value={assetUrl(info?.logo)}
                        onChange={(file) => setLogoFile(file)}
                        error={errors.logo}
                        maxSize={2}
                        previewSize="md"
                    />

                    {/* General Info */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Site Name</label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                            <input
                                type="text"
                                value={data.currency}
                                onChange={(e) => setData('currency', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>
                    </div>

                    {/* Hero Section */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-medium text-gray-900 border-b pb-2">Hero Section</h3>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Hero Title</label>
                            <input
                                type="text"
                                value={data.hero_title}
                                onChange={(e) => setData('hero_title', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Hero Description</label>
                            <textarea
                                value={data.hero_description}
                                onChange={(e) => setData('hero_description', e.target.value)}
                                rows="2"
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                            />
                        </div>
                    </div>

                    {/* Contact Info */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-medium text-gray-900 border-b pb-2">Contact Information</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input
                                    type="text"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                <input
                                    type="text"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Shipping */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-medium text-gray-900 border-b pb-2">Shipping</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Shipping Fee (MMK)</label>
                                <input
                                    type="number"
                                    value={data.shipping_fee}
                                    onChange={(e) => setData('shipping_fee', parseInt(e.target.value) || 0)}
                                    className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Free Shipping Threshold</label>
                                <input
                                    type="number"
                                    value={data.free_shipping_threshhold}
                                    onChange={(e) => setData('free_shipping_threshhold', parseInt(e.target.value) || 0)}
                                    className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Shipping Info Text</label>
                                <textarea
                                    value={data.shipping_info}
                                    onChange={(e) => setData('shipping_info', e.target.value)}
                                    rows="2"
                                    className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Submit */}
                    <div className="flex justify-end pt-4 border-t border-gray-200">
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
                                    Save Changes
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
