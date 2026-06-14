import FormInput from '../FormInput';
import FormTextarea from '../FormTextarea';
import getImagePreviewUrl from '@/Utils/getImagePreviewUrl';

export default function SEOSection({
    data,
    setData,
    errors,
    seoImageFile,
    setSeoImageFile,
    removeSeoImage,
    setRemoveSeoImage,
    existingSeoImageUrl = null,
}) {
    const previewUrl = seoImageFile
        ? URL.createObjectURL(seoImageFile)
        : (existingSeoImageUrl && !removeSeoImage ? getImagePreviewUrl(existingSeoImageUrl) : null);

    return (
        <div className="space-y-4">
            <FormInput
                label="SEO Title"
                name="seo_title"
                value={data.seo_title || ''}
                onChange={(e) => setData('seo_title', e.target.value)}
                placeholder="SEO title (defaults to product name)"
                error={errors.seo_title}
                helpText="Recommended: 50-60 characters"
            />
            <FormTextarea
                label="SEO Description"
                name="seo_description"
                value={data.seo_description || ''}
                onChange={(e) => setData('seo_description', e.target.value)}
                placeholder="Brief description for search engine results"
                error={errors.seo_description}
                rows={3}
                helpText="Recommended: 150-160 characters"
            />
            <FormInput
                label="SEO Keywords"
                name="seo_keywords"
                value={data.seo_keywords || ''}
                onChange={(e) => setData('seo_keywords', e.target.value)}
                placeholder="keyword1, keyword2, keyword3"
                error={errors.seo_keywords}
                helpText="Comma-separated list of keywords"
            />
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1.5">SEO Image</label>
                {previewUrl ? (
                    <div className="flex items-center gap-3">
                        <img
                            src={previewUrl}
                            alt="SEO preview"
                            className="w-24 h-24 rounded-lg object-cover border border-gray-200"
                        />
                        <div className="space-y-2">
                            <button
                                type="button"
                                onClick={() => {
                                    setSeoImageFile(null);
                                    setRemoveSeoImage(true);
                                }}
                                className="text-xs text-red-600 hover:text-red-700 font-medium"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => {
                                const input = document.createElement('input');
                                input.type = 'file';
                                input.accept = 'image/jpeg,image/png,image/webp';
                                input.onchange = (e) => {
                                    const file = e.target.files?.[0];
                                    if (file) {
                                        setSeoImageFile(file);
                                        setRemoveSeoImage(false);
                                    }
                                };
                                input.click();
                            }}
                            className="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 font-medium"
                        >
                            Choose Image
                        </button>
                        <span className="text-xs text-gray-400">JPG, PNG, WEBP (max 2MB)</span>
                    </div>
                )}
                {errors.seo_image && <p className="mt-1 text-xs text-red-600">{errors.seo_image}</p>}
            </div>
        </div>
    );
}
