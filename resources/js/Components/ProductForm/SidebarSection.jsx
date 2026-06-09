import { Trash2, Package } from 'lucide-react';
import { usePage } from '@inertiajs/react';

const TYPE_LABELS = {
    single: '📦 Single',
    variable: '⚙️ Variable',
    combo: '🎁 Bundle',
};

const TYPE_STYLES = {
    single: 'bg-blue-100 text-blue-700',
    variable: 'bg-purple-100 text-purple-700',
    combo: 'bg-orange-100 text-orange-700',
};

export default function SidebarSection({
    processing,
    onSubmit,
    onCancel,
    isEdit = false,
    onDeleteUrl = null,
    data = {},
    photo1File = null,
    existingPhoto1Url = null,
}) {
    const { units = [], categories = [], brands = [] } = usePage().props;

    const category = categories.find(c => c.id == data.category_id);
    const unit = units.find(u => u.id == data.unit_id);
    const brand = brands.find(b => b.id == data.brand_id);
    const photoPreview = photo1File
        ? URL.createObjectURL(photo1File)
        : existingPhoto1Url
            ? existingPhoto1Url
            : null;

    return (
        <div className="space-y-4 sticky top-4">
            <div className="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <div className="px-4 py-3 border-b border-gray-100 bg-gray-50/50">
                    <h3 className="text-sm font-semibold text-gray-900">Product Summary</h3>
                </div>
                <div className="p-4 space-y-3">
                    <div className="w-full h-32 rounded-lg bg-gray-100 overflow-hidden mb-3 flex items-center justify-center">
                        {photoPreview ? (
                            <img
                                src={photoPreview}
                                alt="Product preview"
                                className="w-full h-full object-cover"
                            />
                        ) : (
                            <Package className="w-10 h-10 text-gray-300" />
                        )}
                    </div>

                    <div>
                        <p className="text-xs text-gray-500 mb-0.5">Name</p>
                        <p className="text-sm font-medium text-gray-900 truncate">
                            {data.name || (
                                <span className="text-gray-400 italic">Not set</span>
                            )}
                        </p>
                    </div>

                    <div>
                        <p className="text-xs text-gray-500 mb-0.5">Type</p>
                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${TYPE_STYLES[data.product_type] || TYPE_STYLES.single}`}>
                            {TYPE_LABELS[data.product_type] || 'Single Product'}
                        </span>
                    </div>

                    <div>
                        <p className="text-xs text-gray-500 mb-0.5">Category</p>
                        <p className="text-sm text-gray-700">
                            {category?.name || (
                                <span className="text-gray-400 italic">Not set</span>
                            )}
                        </p>
                    </div>

                    <div>
                        <p className="text-xs text-gray-500 mb-0.5">Brand</p>
                        <p className="text-sm text-gray-700">
                            {brand?.name || (
                                <span className="text-gray-400 italic">No brand</span>
                            )}
                        </p>
                    </div>

                    <div>
                        <p className="text-xs text-gray-500 mb-0.5">Stock</p>
                        <p className="text-sm font-medium text-gray-900">{data.stock ?? 0}</p>
                    </div>

                    <div>
                        <p className="text-xs text-gray-500 mb-0.5">Unit</p>
                        <p className="text-sm text-gray-700">
                            {unit ? `${unit.name} (${unit.short_name})` : (
                                <span className="text-gray-400 italic">Not set</span>
                            )}
                        </p>
                    </div>

                    <div>
                        <p className="text-xs text-gray-500 mb-0.5">Short Description</p>
                        <p className="text-sm text-gray-700 line-clamp-2">
                            {data.short_description || (
                                <span className="text-gray-400 italic">Not set</span>
                            )}
                        </p>
                    </div>
                </div>
            </div>

            <div className="bg-white rounded-xl shadow-lg border border-gray-200 p-4 space-y-3">
                <button
                    type="button"
                    onClick={onSubmit}
                    disabled={processing}
                    className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm"
                >
                    {processing ? (
                        <>
                            <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {isEdit ? 'Updating...' : 'Saving...'}
                        </>
                    ) : (
                        <>
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                            {isEdit ? 'Update Product' : 'Save Product'}
                        </>
                    )}
                </button>

                {isEdit && onDeleteUrl && (
                    <a
                        href={onDeleteUrl}
                        data-Delete
                        method="delete"
                        as="button"
                        type="button"
                        className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors"
                    >
                        <Trash2 className="w-4 h-4" />
                        Delete Product
                    </a>
                )}

                <button
                    type="button"
                    onClick={onCancel}
                    className="w-full px-4 py-2.5 text-gray-700 bg-white border border-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors"
                >
                    Cancel
                </button>
            </div>
        </div>
    );
}
