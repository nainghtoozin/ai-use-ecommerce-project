import { useEffect, useRef, useState } from 'react';
import FormInput from '../FormInput';
import RichTextEditor from '@/Components/editor/RichTextEditor';
import { usePage } from '@inertiajs/react';
import { Image, Upload, X } from 'lucide-react';
import getImagePreviewUrl from '@/Utils/getImagePreviewUrl';

function slugify(text) {
    return text
        .toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function CompactImageUpload({ name, value, onChange, error, accept = 'image/jpeg,image/png,image/webp', maxSize = 2 }) {
    const [preview, setPreview] = useState(null);
    const inputRef = useRef(null);

    const existingUrl = typeof value === 'string' && value ? value : null;
    const previewUrl = preview || getImagePreviewUrl(existingUrl);

    const handleFile = (file) => {
        if (!file) return;
        if (!file.type.startsWith('image/')) return;
        const maxBytes = maxSize * 1024 * 1024;
        if (file.size > maxBytes) return;
        const reader = new FileReader();
        reader.onload = (ev) => setPreview(ev.target.result);
        reader.readAsDataURL(file);
        if (onChange) onChange(file);
    };

    const handleInputChange = (e) => {
        const file = e.target.files?.[0];
        handleFile(file);
    };

    const handleRemove = () => {
        if (inputRef.current) inputRef.current.value = '';
        setPreview(null);
        if (onChange) onChange(null);
    };

    return (
        <div className="flex items-center gap-4">
            <div className="w-[120px] h-[120px] rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700 flex items-center justify-center overflow-hidden bg-gray-50 dark:bg-gray-950 flex-shrink-0">
                {previewUrl ? (
                    <img src={previewUrl} alt="Preview" className="w-full h-full object-cover" />
                ) : (
                    <Image className="w-10 h-10 text-gray-400 dark:text-gray-500" />
                )}
            </div>
            <div className="flex flex-col gap-2">
                <label className="cursor-pointer">
                    <span className="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 transition-colors">
                        <Upload className="w-4 h-4" />
                        {existingUrl || preview ? 'Change Image' : 'Upload Image'}
                    </span>
                    <input ref={inputRef} type="file" name={name} accept={accept} onChange={handleInputChange} className="hidden" />
                </label>
                {(existingUrl || preview) && (
                    <button type="button" onClick={handleRemove} className="inline-flex items-center gap-1 px-3 py-1.5 text-sm text-red-600 hover:text-red-800">
                        <X className="w-3 h-3" /> Remove
                    </button>
                )}
                <p className="text-xs text-gray-400">PNG, JPG, WebP, max {maxSize}MB</p>
                {error && <p className="text-xs text-red-600">{error}</p>}
            </div>
        </div>
    );
}

export default function BasicInfoSection({ data, setData, errors, photo1File, setPhoto1File, existingPhoto1Url, photo2File, setPhoto2File, existingPhoto2Url }) {
    const { units = [], categories = [], brands = [], featureStatus = {} } = usePage().props;
    const inventoryEnabled = featureStatus.inventory_management?.enabled !== false;
    const [status, setStatus] = useState(data.status || 'active');
    const isGeneratingSlug = useRef(true);

    useEffect(() => {
        if (isGeneratingSlug.current && data.name) {
            const generated = slugify(data.name);
            if (generated && !data.slug) {
                setData('slug', generated);
            }
        }
        isGeneratingSlug.current = false;
    }, []);

    useEffect(() => {
        setData('status', status);
    }, [status]);

    const isVariable = data.product_type === 'variable';
    const isSingle = data.product_type === 'single';

    return (
        <div className="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">Basic Information</h3>
            </div>

            <div className="px-6 py-6 space-y-6">
                {/* Name + SKU row */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div className="lg:col-span-2">
                        <FormInput
                            label="Product Name"
                            name="name"
                            value={data.name}
                            onChange={(e) => {
                                setData('name', e.target.value);
                                if (!data.slug) {
                                    setData('slug', slugify(e.target.value));
                                }
                            }}
                            placeholder="Enter product name"
                            error={errors.name}
                            required
                        />
                    </div>
                    <FormInput
                        label="SKU"
                        name="sku"
                        value={data.sku || ''}
                        onChange={(e) => setData('sku', e.target.value)}
                        placeholder="Auto-generated"
                        error={errors.sku}
                    />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <FormInput
                        label="Barcode (ISBN, UPC, GTIN)"
                        name="barcode"
                        value={data.barcode || ''}
                        onChange={(e) => setData('barcode', e.target.value)}
                        placeholder="Optional"
                        error={errors.barcode}
                    />
                </div>

                {/* Hidden slug field */}
                <input type="hidden" name="slug" value={data.slug || ''} />

                {/* Category + Brand + Unit row */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Category <span className="text-red-500">*</span>
                        </label>
                        <select
                            name="category_id"
                            value={data.category_id}
                            onChange={(e) => setData('category_id', e.target.value)}
                            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-900"
                        >
                            <option value="">Select category</option>
                            {categories.map((cat) => (
                                <option key={cat.id} value={cat.id}>{cat.name}</option>
                            ))}
                        </select>
                        {errors.category_id && <p className="mt-1 text-xs text-red-600">{errors.category_id}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Brand</label>
                        <select
                            name="brand_id"
                            value={data.brand_id}
                            onChange={(e) => setData('brand_id', e.target.value)}
                            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-900"
                        >
                            <option value="">No brand</option>
                            {brands.map((brand) => (
                                <option key={brand.id} value={brand.id}>{brand.name}</option>
                            ))}
                        </select>
                        {errors.brand_id && <p className="mt-1 text-xs text-red-600">{errors.brand_id}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Unit</label>
                        <select
                            name="unit_id"
                            value={data.unit_id}
                            onChange={(e) => setData('unit_id', e.target.value)}
                            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-900"
                        >
                            <option value="">No unit</option>
                            {units.map((unit) => (
                                <option key={unit.id} value={unit.id}>
                                    {unit.name} ({unit.short_name})
                                </option>
                            ))}
                        </select>
                        {errors.unit_id && <p className="mt-1 text-xs text-red-600">{errors.unit_id}</p>}
                    </div>
                </div>

                {/* Short Description */}
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Short Description
                    </label>
                    <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">Brief summary shown in product cards and previews.</p>
                    <RichTextEditor
                        value={data.short_description || ''}
                        onChange={(v) => setData('short_description', v)}
                        placeholder="Brief summary for listings and search results..."
                        minHeight="100px"
                    />
                    {errors.short_description && <p className="mt-1 text-xs text-red-600">{errors.short_description}</p>}
                </div>

                {/* Combo: inline Description */}
                {data.product_type === 'combo' && (
                    <>
                        {/* Divider */}
                        <div className="border-t border-gray-100 dark:border-gray-800" />

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Description
                            </label>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">Detailed description of the bundle.</p>
                            <RichTextEditor
                                value={data.description || ''}
                                onChange={(v) => setData('description', v)}
                                placeholder="Detailed description of the bundle..."
                                minHeight="120px"
                            />
                            {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description}</p>}
                        </div>
                    </>
                )}

                {/* Divider */}
                <div className="border-t border-gray-100 dark:border-gray-800" />

                {/* Primary Product Image (all product types) */}
                <div>
                    <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Primary Product Image <span className="text-red-500">*</span></h4>
                    <CompactImageUpload
                        name="photo1"
                        value={photo1File || existingPhoto1Url}
                        onChange={setPhoto1File}
                        error={errors.photo1}
                    />
                </div>

                {/* Secondary Product Image */}
                <div>
                    <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Secondary Product Image</h4>
                    <CompactImageUpload
                        name="photo2"
                        value={photo2File || existingPhoto2Url}
                        onChange={setPhoto2File}
                        error={errors.photo2}
                    />
                </div>

                {isSingle && (
                    <>
                        {/* Divider */}
                        <div className="border-t border-gray-100 dark:border-gray-800" />

                        {/* Pricing row */}
                        <div>
                            <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Pricing</h4>
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <FormInput
                                    label="Sales Price"
                                    name="price"
                                    type="number"
                                    value={data.price}
                                    onChange={(e) => setData('price', e.target.value)}
                                    placeholder="0.00"
                                    error={errors.price}
                                    required
                                    step="0.01"
                                    min="0"
                                />
                                <FormInput
                                    label="Compare at Price"
                                    name="base_price"
                                    type="number"
                                    value={data.base_price}
                                    onChange={(e) => setData('base_price', e.target.value)}
                                    placeholder="0.00"
                                    error={errors.base_price}
                                    step="0.01"
                                    min="0"
                                    helpText="Original price before discount"
                                />
                            </div>
                        </div>

                        {/* Divider */}
                        <div className="border-t border-gray-100 dark:border-gray-800" />

                        {/* Inventory — only when inventory feature is disabled (backward compat) */}
                        {!inventoryEnabled && (
                            <div>
                                <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Inventory</h4>
                                <div className="space-y-4 max-w-md">
                                    <FormInput
                                        label="Quantity In Stock"
                                        name="stock"
                                        type="number"
                                        value={data.stock}
                                        onChange={(e) => setData('stock', e.target.value)}
                                        placeholder="0"
                                        error={errors.stock}
                                        min="0"
                                    />
                                    <FormInput
                                        label="Low Stock Alert Threshold"
                                        name="low_stock_alert"
                                        type="number"
                                        value={data.low_stock_alert ?? 5}
                                        onChange={(e) => setData('low_stock_alert', e.target.value)}
                                        placeholder="5"
                                        error={errors.low_stock_alert}
                                        min="0"
                                        helpText="Receive alert when stock drops below this number"
                                    />
                                </div>
                            </div>
                        )}
                    </>
                )}

                {/* Divider */}
                <div className="border-t border-gray-100 dark:border-gray-800" />

                {/* Status */}
                <div>
                    <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Status</h4>
                    <div className="flex items-center gap-4">
                        {['active', 'draft', 'inactive'].map((option) => (
                            <label key={option} className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    name="status"
                                    value={option}
                                    checked={status === option}
                                    onChange={(e) => setStatus(e.target.value)}
                                    className="w-4 h-4 text-blue-600 border-gray-300 dark:border-gray-700 focus:ring-blue-500"
                                />
                                <span className="text-sm text-gray-700 dark:text-gray-300 capitalize">{option}</span>
                            </label>
                        ))}
                    </div>
                    {errors.status && <p className="mt-1 text-xs text-red-600">{errors.status}</p>}
                </div>

                {/* Featured */}
                <div className="border-t border-gray-100 dark:border-gray-800 pt-4">
                    <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Featured & Visibility</h4>
                    <div className="space-y-3">
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.featured ?? false}
                                onChange={(e) => setData('featured', e.target.checked)}
                                className="w-4 h-4 rounded border-gray-300 dark:border-gray-700 text-blue-600 focus:ring-blue-500"
                            />
                            <span className="text-sm text-gray-700 dark:text-gray-300">Featured product</span>
                        </label>
                        <div>
                            <label className="block text-sm text-gray-600 dark:text-gray-400 mb-1">Sort Order</label>
                            <input
                                type="number"
                                min="0"
                                value={data.sort_order ?? 0}
                                onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                                className="w-24 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm"
                            />
                        </div>
                    </div>
                    {errors.featured && <p className="mt-1 text-xs text-red-600">{errors.featured}</p>}
                    {errors.sort_order && <p className="mt-1 text-xs text-red-600">{errors.sort_order}</p>}
                </div>
            </div>
        </div>
    );
}
