import { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { Image, Upload, X } from 'lucide-react';

export default function BrandEdit({ brand }) {
    const { auth } = usePage().props;
    if (!auth?.user?.permissions?.includes('brands.update')) {
        return <AdminLayout><div className="text-center py-16"><p className="text-red-600 font-semibold">Unauthorized</p></div></AdminLayout>;
    }
    const { data, setData, put, processing, errors } = useForm({
        name: brand.name || '',
        slug: brand.slug || '',
        description: brand.description || '',
        logo: null,
        banner: null,
        featured: brand.featured ?? false,
        sort_order: brand.sort_order ?? 0,
        is_active: brand.is_active ?? true,
        remove_logo: false,
        remove_banner: false,
    });

    const [logoPreview, setLogoPreview] = useState(null);
    const [bannerPreview, setBannerPreview] = useState(null);
    const existingLogo = brand.logo_url;
    const existingBanner = brand.banner_url;

    function handleLogoChange(e) {
        const file = e.target.files[0];
        if (file) {
            setData('logo', file);
            setData('remove_logo', false);
            const reader = new FileReader();
            reader.onload = (ev) => setLogoPreview(ev.target.result);
            reader.readAsDataURL(file);
        }
    }

    function handleBannerChange(e) {
        const file = e.target.files[0];
        if (file) {
            setData('banner', file);
            setData('remove_banner', false);
            const reader = new FileReader();
            reader.onload = (ev) => setBannerPreview(ev.target.result);
            reader.readAsDataURL(file);
        }
    }

    function handleLogoRemove() {
        setData('logo', null);
        setData('remove_logo', true);
        setLogoPreview(null);
        const fileInput = document.getElementById('logo');
        if (fileInput) fileInput.value = '';
    }

    function handleBannerRemove() {
        setData('banner', null);
        setData('remove_banner', true);
        setBannerPreview(null);
        const fileInput = document.getElementById('banner');
        if (fileInput) fileInput.value = '';
    }

    function handleSubmit(e) {
        e.preventDefault();
        put(adminUrl(`/admin/brands/${brand.id}`), { preserveScroll: true });
    }

    return (
        <AdminLayout>
            <Head title={`Edit ${brand.name}`} />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <Link href={adminUrl('/admin/brands')} className="text-sm text-blue-600 hover:underline">&larr; Back to Brands</Link>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2">Edit Brand</h1>
                </div>

                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
                    <form onSubmit={handleSubmit} className="space-y-6">

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Logo</label>
                            <div className="flex items-center gap-4">
                                <div className="w-20 h-20 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700 flex items-center justify-center overflow-hidden bg-gray-50 dark:bg-gray-950">
                                    {logoPreview ? (
                                        <img src={logoPreview} alt="Preview" className="w-full h-full object-cover" />
                                    ) : existingLogo ? (
                                        <img src={existingLogo} alt={brand.name} className="w-full h-full object-cover" />
                                    ) : (
                                        <Image className="w-8 h-8 text-gray-400 dark:text-gray-500" />
                                    )}
                                </div>
                                <div className="flex flex-col gap-2">
                                    <label className="cursor-pointer">
                                        <span className="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 transition-colors">
                                            <Upload className="w-4 h-4" />
                                            {existingLogo ? 'Change Logo' : 'Upload Logo'}
                                        </span>
                                        <input id="logo" type="file" accept="image/jpeg,image/png,image/webp" onChange={handleLogoChange} className="hidden" />
                                    </label>
                                    {(existingLogo || logoPreview) && (
                                        <button type="button" onClick={handleLogoRemove} className="inline-flex items-center gap-1 px-3 py-1.5 text-sm text-red-600 hover:text-red-800">
                                            <X className="w-3 h-3" /> Remove
                                        </button>
                                    )}
                                </div>
                            </div>
                            <p className="mt-1 text-xs text-gray-400">PNG, JPG, or WebP, max 2MB.</p>
                            {errors.logo && <p className="mt-1 text-sm text-red-600">{errors.logo}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Banner / Cover</label>
                            <div className="flex items-center gap-4">
                                <div className="w-32 h-16 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700 flex items-center justify-center overflow-hidden bg-gray-50 dark:bg-gray-950">
                                    {bannerPreview ? (
                                        <img src={bannerPreview} alt="Preview" className="w-full h-full object-cover" />
                                    ) : existingBanner ? (
                                        <img src={existingBanner} alt={brand.name} className="w-full h-full object-cover" />
                                    ) : (
                                        <Image className="w-6 h-6 text-gray-400 dark:text-gray-500" />
                                    )}
                                </div>
                                <div className="flex flex-col gap-2">
                                    <label className="cursor-pointer">
                                        <span className="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 transition-colors">
                                            <Upload className="w-4 h-4" />
                                            {existingBanner ? 'Change Banner' : 'Upload Banner'}
                                        </span>
                                        <input id="banner" type="file" accept="image/jpeg,image/png,image/webp" onChange={handleBannerChange} className="hidden" />
                                    </label>
                                    {(existingBanner || bannerPreview) && (
                                        <button type="button" onClick={handleBannerRemove} className="inline-flex items-center gap-1 px-3 py-1.5 text-sm text-red-600 hover:text-red-800">
                                            <X className="w-3 h-3" /> Remove
                                        </button>
                                    )}
                                </div>
                            </div>
                            <p className="mt-1 text-xs text-gray-400">PNG, JPG, or WebP, max 5MB.</p>
                            {errors.banner && <p className="mt-1 text-sm text-red-600">{errors.banner}</p>}
                        </div>

                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                            <input id="name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                                className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required />
                            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                        </div>

                        <div>
                            <label htmlFor="slug" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Slug</label>
                            <input id="slug" type="text" value={data.slug} onChange={(e) => setData('slug', e.target.value)}
                                className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Auto-generated from name" />
                            <p className="mt-1 text-xs text-gray-400">Leave empty to auto-generate from name.</p>
                            {errors.slug && <p className="mt-1 text-sm text-red-600">{errors.slug}</p>}
                        </div>

                        <div>
                            <label htmlFor="description" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                            <textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} rows={3}
                                className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                            {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label htmlFor="sort_order" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sort Order</label>
                                <input id="sort_order" type="number" min="0" value={data.sort_order} onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                                    className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                                {errors.sort_order && <p className="mt-1 text-sm text-red-600">{errors.sort_order}</p>}
                            </div>

                            <div className="flex items-center gap-3 pt-6">
                                <input type="checkbox" id="featured" checked={data.featured}
                                    onChange={(e) => setData('featured', e.target.checked)}
                                    className="w-4 h-4 text-blue-600 border-gray-300 dark:border-gray-700 rounded focus:ring-blue-500" />
                                <label htmlFor="featured" className="text-sm font-medium text-gray-700 dark:text-gray-300">Featured</label>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <input type="checkbox" id="is_active" checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="w-4 h-4 text-blue-600 border-gray-300 dark:border-gray-700 rounded focus:ring-blue-500" />
                            <label htmlFor="is_active" className="text-sm font-medium text-gray-700 dark:text-gray-300">Active</label>
                        </div>

                        <div className="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-800">
                            <Link href={adminUrl('/admin/brands')} className="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-200 transition-colors">Cancel</Link>
                            <button type="submit" disabled={processing}
                                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                {processing ? 'Updating...' : 'Update Brand'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
