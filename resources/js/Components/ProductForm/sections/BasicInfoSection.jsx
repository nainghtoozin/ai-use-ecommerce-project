import FormInput from '../FormInput';
import FormTextarea from '../FormTextarea';

export default function BasicInfoSection({ data, setData, errors }) {
    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div className="px-5 py-4 border-b border-gray-100">
                <div className="flex items-center gap-3">
                    <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                        <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 className="text-base font-semibold text-gray-900">Basic Information</h3>
                        <p className="text-xs text-gray-500 mt-0.5">Product name, slug, and short description</p>
                    </div>
                </div>
            </div>
            <div className="px-5 py-5 space-y-4">
                <FormInput
                    label="Product Name"
                    name="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="Enter a descriptive product name"
                    error={errors.name}
                    required
                />

                <FormInput
                    label="SKU"
                    name="sku"
                    value={data.sku || ''}
                    onChange={(e) => setData('sku', e.target.value)}
                    placeholder="Leave empty to auto-generate"
                    error={errors.sku}
                    helpText="Leave empty to auto-generate SKU"
                />

                <FormInput
                    label="Slug"
                    name="slug"
                    value={data.slug || ''}
                    onChange={(e) => setData('slug', e.target.value)}
                    placeholder="auto-generated-from-name"
                    error={errors.slug}
                    helpText="URL-friendly version of the product name"
                />

                <FormTextarea
                    label="Short Description"
                    name="short_description"
                    value={data.short_description || ''}
                    onChange={(e) => setData('short_description', e.target.value)}
                    placeholder="Brief summary for listings and previews..."
                    error={errors.short_description}
                    rows={2}
                    helpText="Displayed in product listings and search results"
                />
            </div>
        </div>
    );
}
