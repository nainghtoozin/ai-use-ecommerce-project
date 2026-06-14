import { useEffect, useRef, useState } from 'react';
import FormInput from '../FormInput';
import FormTextarea from '../FormTextarea';
import ImageUpload from '@/Components/ImageUpload';
import { usePage } from '@inertiajs/react';

function slugify(text) {
    return text
        .toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function BasicInfoSection({ data, setData, errors, photo1File, setPhoto1File, existingPhoto1Url }) {
    const { units = [], categories = [], brands = [] } = usePage().props;
    const [status, setStatus] = useState(data.status || 'active');
    const isGeneratingSlug = useRef(true);

    useEffect(() => {
        if (isGeneratingSlug.current && data.name) {
            const generated = slugify(data.name);
            if (generated && !data.slug) {
                setData('slug', generated);
            }
        }
    }, [data.name]);

    useEffect(() => {
        setData('status', status);
    }, [status]);

    const isVariable = data.product_type === 'variable';
    const isSingle = data.product_type === 'single';

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-100">
                <h3 className="text-base font-semibold text-gray-900">Basic Information</h3>
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
                                if (!data.slug || isGeneratingSlug.current) {
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
                        <label className="block text-sm font-medium text-gray-700 mb-1.5">
                            Category <span className="text-red-500">*</span>
                        </label>
                        <select
                            name="category_id"
                            value={data.category_id}
                            onChange={(e) => setData('category_id', e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white"
                        >
                            <option value="">Select category</option>
                            {categories.map((cat) => (
                                <option key={cat.id} value={cat.id}>{cat.name}</option>
                            ))}
                        </select>
                        {errors.category_id && <p className="mt-1 text-xs text-red-600">{errors.category_id}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1.5">Brand</label>
                        <select
                            name="brand_id"
                            value={data.brand_id}
                            onChange={(e) => setData('brand_id', e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white"
                        >
                            <option value="">No brand</option>
                            {brands.map((brand) => (
                                <option key={brand.id} value={brand.id}>{brand.name}</option>
                            ))}
                        </select>
                        {errors.brand_id && <p className="mt-1 text-xs text-red-600">{errors.brand_id}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1.5">Unit</label>
                        <select
                            name="unit_id"
                            value={data.unit_id}
                            onChange={(e) => setData('unit_id', e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white"
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
                <FormTextarea
                    label="Short Description"
                    name="short_description"
                    value={data.short_description || ''}
                    onChange={(e) => setData('short_description', e.target.value)}
                    placeholder="Brief summary for listings and search results..."
                    error={errors.short_description}
                    rows={2}
                />

                {/* Combo: inline Description */}
                {data.product_type === 'combo' && (
                    <>
                        {/* Divider */}
                        <div className="border-t border-gray-100" />

                        <FormTextarea
                            label="Description"
                            name="description"
                            value={data.description || ''}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Detailed description of the bundle..."
                            error={errors.description}
                            rows={4}
                        />
                    </>
                )}

                {/* Divider */}
                <div className="border-t border-gray-100" />

                {/* Primary Product Image (all product types) */}
                <div>
                    <h4 className="text-sm font-semibold text-gray-900 mb-4">Primary Product Image <span className="text-red-500">*</span></h4>
                    <ImageUpload
                        name="photo1"
                        label=""
                        value={photo1File || existingPhoto1Url}
                        onChange={setPhoto1File}
                        error={errors.photo1}
                    />
                </div>

                {isSingle && (
                    <>
                        {/* Divider */}
                        <div className="border-t border-gray-100" />

                        {/* Pricing row */}
                        <div>
                            <h4 className="text-sm font-semibold text-gray-900 mb-4">Pricing</h4>
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
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
                                <FormInput
                                    label="Cost Per Item"
                                    name="cost_price"
                                    type="number"
                                    value={data.cost_price || ''}
                                    onChange={(e) => setData('cost_price', e.target.value)}
                                    placeholder="0.00"
                                    error={errors.cost_price}
                                    step="0.01"
                                    min="0"
                                    helpText="Customers won't see this price"
                                />
                            </div>
                        </div>

                        {/* Divider */}
                        <div className="border-t border-gray-100" />

                        {/* Inventory */}
                        <div>
                            <h4 className="text-sm font-semibold text-gray-900 mb-4">Inventory</h4>
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
                    </>
                )}

                {/* Divider */}
                <div className="border-t border-gray-100" />

                {/* Status */}
                <div>
                    <h4 className="text-sm font-semibold text-gray-900 mb-3">Status</h4>
                    <div className="flex items-center gap-4">
                        {['active', 'draft', 'inactive'].map((option) => (
                            <label key={option} className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    name="status"
                                    value={option}
                                    checked={status === option}
                                    onChange={(e) => setStatus(e.target.value)}
                                    className="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                />
                                <span className="text-sm text-gray-700 capitalize">{option}</span>
                            </label>
                        ))}
                    </div>
                    {errors.status && <p className="mt-1 text-xs text-red-600">{errors.status}</p>}
                </div>
            </div>
        </div>
    );
}
