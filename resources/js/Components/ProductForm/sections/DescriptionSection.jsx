import FormTextarea from '../FormTextarea';

export default function DescriptionSection({ data, setData, errors }) {
    const charCount = (data.description || '').length;

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div className="px-5 py-4 border-b border-gray-100">
                <div className="flex items-center gap-3">
                    <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                        <svg className="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                    </div>
                    <div>
                        <h3 className="text-base font-semibold text-gray-900">Description</h3>
                        <p className="text-xs text-gray-500 mt-0.5">Detailed product information</p>
                    </div>
                </div>
            </div>
            <div className="px-5 py-5">
                <FormTextarea
                    label="Full Description"
                    name="description"
                    value={data.description || ''}
                    onChange={(e) => setData('description', e.target.value)}
                    placeholder="Write a detailed product description..."
                    error={errors.description}
                    rows={8}
                    helpText={`${charCount} characters. HTML tags are supported.`}
                />
            </div>
        </div>
    );
}
