import FormInput from '../FormInput';
import FormTextarea from '../FormTextarea';

export default function SEOSection({ data, setData, errors }) {
    const titleLength = (data.meta_title || data.name || '').length;
    const descLength = (data.meta_description || '').length;

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div className="px-5 py-4 border-b border-gray-100">
                <div className="flex items-center gap-3">
                    <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-cyan-50 flex items-center justify-center">
                        <svg className="w-4 h-4 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 className="text-base font-semibold text-gray-900">SEO</h3>
                        <p className="text-xs text-gray-500 mt-0.5">Search engine optimization</p>
                    </div>
                </div>
            </div>
            <div className="px-5 py-5 space-y-4">
                <FormInput
                    label="Meta Title"
                    name="meta_title"
                    value={data.meta_title || ''}
                    onChange={(e) => setData('meta_title', e.target.value)}
                    placeholder={data.name || 'Product meta title'}
                    error={errors.meta_title}
                    helpText={
                        <span className={titleLength > 60 ? 'text-amber-600' : 'text-gray-500'}>
                            {titleLength}/60 characters
                        </span>
                    }
                />

                <FormTextarea
                    label="Meta Description"
                    name="meta_description"
                    value={data.meta_description || ''}
                    onChange={(e) => setData('meta_description', e.target.value)}
                    placeholder="Brief description for search engines..."
                    error={errors.meta_description}
                    rows={3}
                    helpText={
                        <span className={descLength > 160 ? 'text-amber-600' : 'text-gray-500'}>
                            {descLength}/160 characters
                        </span>
                    }
                />

                <FormInput
                    label="Tags"
                    name="tags"
                    value={data.tags || ''}
                    onChange={(e) => setData('tags', e.target.value)}
                    placeholder="electronics, gadgets, tech (comma separated)"
                    error={errors.tags}
                    helpText="Comma-separated tags for better discoverability"
                />

                <div className="rounded-lg bg-gray-50 border border-gray-200 p-4">
                    <p className="text-xs font-medium text-gray-700 mb-2">Preview</p>
                    <div className="space-y-1">
                        <p className="text-sm text-blue-700 truncate">
                            {data.meta_title || data.name || 'Product Title'}
                        </p>
                        <p className="text-xs text-green-700">
                            yourstore.com/products/{(data.slug || data.name || '').toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '') || 'product-slug'}
                        </p>
                        <p className="text-xs text-gray-600 line-clamp-2">
                            {data.meta_description || 'Product description will appear here...'}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
