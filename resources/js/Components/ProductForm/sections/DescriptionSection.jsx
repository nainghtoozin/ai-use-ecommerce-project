import RichTextEditor from '@/Components/editor/RichTextEditor';

export default function DescriptionSection({ data, setData, errors }) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                Full Description
            </label>
            <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">Detailed product information shown on the product detail page.</p>
            <RichTextEditor
                value={data.description || ''}
                onChange={(v) => setData('description', v)}
                placeholder="Write a detailed product description..."
                minHeight="200px"
            />
            {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description}</p>}
        </div>
    );
}
