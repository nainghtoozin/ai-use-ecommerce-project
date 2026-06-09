import FormInput from '../FormInput';
import FormTextarea from '../FormTextarea';

export default function SEOSection({ data, setData, errors }) {
    return (
        <div className="space-y-4">
            <FormInput
                label="Page Title"
                name="meta_title"
                value={data.meta_title || ''}
                onChange={(e) => setData('meta_title', e.target.value)}
                placeholder="SEO title (defaults to product name)"
                error={errors.meta_title}
                helpText="Recommended: 50-60 characters"
            />
            <FormTextarea
                label="Meta Description"
                name="meta_description"
                value={data.meta_description || ''}
                onChange={(e) => setData('meta_description', e.target.value)}
                placeholder="Brief description for search engine results"
                error={errors.meta_description}
                rows={3}
                helpText="Recommended: 150-160 characters"
            />
        </div>
    );
}
