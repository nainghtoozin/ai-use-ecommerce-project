import { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { Image, Upload } from 'lucide-react';

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
        is_active: brand.is_active ?? true,
    });

    const [logoPreview, setLogoPreview] = useState(null);
    const existingLogo = brand.logo;

    function handleLogoChange(e) {
        const file = e.target.files[0];
        if (file) {
            setData('logo', file);
            const reader = new FileReader();
            reader.onload = (ev) => setLogoPreview(ev.target.result);
            reader.readAsDataURL(file);
        }
    }

    function handleSubmit(e) {
        e.preventDefault();
        put(adminUrl(`/admin/brands/${brand.id}`));
    }

    return (
        <AdminLayout>
            <Head title={`Edit ${brand.name}`} />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <Link href={adminUrl('/admin/brands')} className="text-sm text-blue-600 hover:underline">&larr; Back to Brands</Link>
                    <h1 className="text-2xl font-bold text-gray-900 mt-2">Edit Brand</h1>
                    <p className="text-sm text-gray-500 mt-1">Update brand details</p>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-6">
                    <form onSubmit={handleSubmit} className="space-y-6">

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Logo</label>
                            <div className="flex items-center gap-4">
                                <div className="w-20 h-20 rounded-xl border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden bg-gray-50">
                                    {logoPreview ? (
                                        <img src={logoPreview} alt="Preview" className="w-full h-full object-cover" />
                                    ) : existingLogo ? (
                                        <img src={existingLogo} alt={brand.name} className="w-full h-full object-cover" />
                                    ) : (
                                        <Image className="w-8 h-8 text-gray-400" />
                                    )}
                                </div>
                                <label className="cursor-pointer">
                                    <span className="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                        <Upload className="w-4 h-4" />
                                        Change Logo
                                    </span>
                                    <input type="file" accept="image/jpeg,image/png,image/webp" onChange={handleLogoChange} className="hidden" />
                                </label>
                            </div>
                            {errors.logo && <p className="mt-1 text-sm text-red-600">{errors.logo}</p>}
                        </div>

                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input id="name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required />
                            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                        </div>

                        <div>
                            <label htmlFor="slug" className="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                            <input id="slug" type="text" value={data.slug} onChange={(e) => setData('slug', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Auto-generated from name" />
                            <p className="mt-1 text-xs text-gray-400">Leave empty to auto-generate from name.</p>
                            {errors.slug && <p className="mt-1 text-sm text-red-600">{errors.slug}</p>}
                        </div>

                        <div>
                            <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} rows={3}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                            {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                        </div>

                        <div className="flex items-center gap-3">
                            <input type="checkbox" id="is_active" checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" />
                            <label htmlFor="is_active" className="text-sm font-medium text-gray-700">Active</label>
                        </div>

                        <div className="flex justify-end gap-3 pt-4 border-t border-gray-200">
                            <Link href={adminUrl('/admin/brands')} className="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">Cancel</Link>
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
