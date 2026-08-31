import { useState } from 'react';
import { Plus, Trash2, ChevronDown, ChevronUp, X, Camera, AlertTriangle } from 'lucide-react';

export default function VariantTable({
    options,
    variants,
    setVariants,
    errors = {},
    onGenerateRequest,
    onClearAll,
    totalCombinations = 0,
    maxCombinations = 100,
    hasExistingVariants = false,
}) {
    const [expandedId, setExpandedId] = useState(null);
    const [showWarning, setShowWarning] = useState(false);

    const handleGenerate = () => {
        if (totalCombinations > maxCombinations) {
            setShowWarning(true);
            return;
        }
        onGenerateRequest?.();
    };

    const handleBulkFill = (field, value) => {
        const updated = variants.map((v) => ({ ...v, [field]: value }));
        setVariants(updated);
    };

    const handleRemoveVariant = (index) => {
        const variant = variants[index];
        if (variant.id && !String(variant.id).startsWith('temp_')) {
            if (!window.confirm('This variant already exists. Are you sure you want to remove it?')) {
                return;
            }
        }
        setVariants(variants.filter((_, i) => i !== index));
    };

    const totalStock = variants.reduce((sum, v) => sum + (parseInt(v.stock) || 0), 0);
    const totalValue = variants.reduce((sum, v) => {
        const price = parseFloat(v.price) || 0;
        const stock = parseInt(v.stock) || 0;
        return sum + (price * stock);
    }, 0);

    return (
        <div className="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
            {/* Header */}
            <div className="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                            <svg className="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                        </div>
                        <div>
                            <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                                Variants
                                {variants.length > 0 && (
                                    <span className="ml-1.5 text-xs font-normal text-gray-400 dark:text-gray-500">
                                        ({variants.length})
                                    </span>
                                )}
                            </h3>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {totalCombinations > 0 && totalCombinations <= maxCombinations && (
                                    <>Will generate {totalCombinations} combination{totalCombinations !== 1 ? 's' : ''}</>
                                )}
                                {totalCombinations > maxCombinations && (
                                    <span className="text-amber-600 dark:text-amber-400">
                                        {totalCombinations} combinations exceeds limit of {maxCombinations}
                                    </span>
                                )}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {options.length > 0 && (
                            <>
                                <button
                                    type="button"
                                    onClick={handleGenerate}
                                    className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    disabled={totalCombinations === 0}
                                >
                                    <Plus className="w-3.5 h-3.5" />
                                    {hasExistingVariants ? 'Regenerate' : 'Generate'} Variants
                                </button>
                                {variants.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={onClearAll}
                                        className="text-xs text-red-600 hover:text-red-700 font-medium"
                                    >
                                        Clear all
                                    </button>
                                )}
                            </>
                        )}
                    </div>
                </div>
            </div>

            {/* Warning Banner */}
            {showWarning && (
                <div className="mx-5 mt-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 flex items-start gap-3">
                    <AlertTriangle className="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                    <div className="flex-1">
                        <p className="text-sm font-medium text-amber-800 dark:text-amber-200">
                            Too Many Combinations
                        </p>
                        <p className="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
                            Generating {totalCombinations} variants at once may cause performance issues.
                            Consider using fewer options or values.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setShowWarning(false)}
                        className="text-amber-400 hover:text-amber-600"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>
            )}

            {/* Summary Stats */}
            {variants.length > 0 && (
                <div className="px-5 py-3 bg-gray-50 dark:bg-gray-950 border-b border-gray-100 dark:border-gray-800">
                    <div className="flex flex-wrap gap-6 text-xs">
                        <div>
                            <span className="text-gray-500 dark:text-gray-400">Total Stock:</span>
                            <span className="ml-1.5 font-medium text-gray-900 dark:text-gray-200">{totalStock.toLocaleString()} units</span>
                        </div>
                        <div>
                            <span className="text-gray-500 dark:text-gray-400">Total Value:</span>
                            <span className="ml-1.5 font-medium text-gray-900 dark:text-gray-200">${totalValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                        </div>
                        <div>
                            <span className="text-gray-500 dark:text-gray-400">Active:</span>
                            <span className="ml-1.5 font-medium text-green-600 dark:text-green-400">
                                {variants.filter(v => v.status === 'active').length}
                            </span>
                        </div>
                        {variants.some(v => v.stock === 0) && (
                            <div>
                                <span className="text-gray-500 dark:text-gray-400">Out of Stock:</span>
                                <span className="ml-1.5 font-medium text-red-600 dark:text-red-400">
                                    {variants.filter(v => v.stock === 0).length}
                                </span>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {variants.length === 0 ? (
                <div className="px-5 py-12 text-center">
                    <div className="w-12 h-12 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-3">
                        <svg className="w-6 h-6 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                                d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                    </div>
                    <p className="text-sm text-gray-600 dark:text-gray-400 font-medium">No variants yet</p>
                    <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        Add variant options above, then generate combinations
                    </p>
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead className="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Variant</th>
                                <th className="px-3 py-3 text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-14">Image</th>
                                <th className="px-3 py-3 text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">
                                    <div className="flex items-center gap-1">
                                        SKU
                                    </div>
                                </th>
                                <th className="px-3 py-3 text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">
                                    <div className="flex items-center gap-1">
                                        Price
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const val = prompt('Set price for all variants:');
                                                if (val !== null && val !== '') handleBulkFill('price', val);
                                            }}
                                            className="text-gray-400 dark:text-gray-500 hover:text-violet-500"
                                            title="Bulk fill price"
                                        >
                                            <Plus className="w-3 h-3" />
                                        </button>
                                    </div>
                                </th>
                                <th className="px-3 py-3 text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">
                                    <div className="flex items-center gap-1">
                                        Cost
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const val = prompt('Set cost for all variants:');
                                                if (val !== null && val !== '') handleBulkFill('cost_price', val);
                                            }}
                                            className="text-gray-400 dark:text-gray-500 hover:text-violet-500"
                                            title="Bulk fill cost"
                                        >
                                            <Plus className="w-3 h-3" />
                                        </button>
                                    </div>
                                </th>
                                <th className="px-3 py-3 text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">
                                    <div className="flex items-center gap-1">
                                        Stock
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const val = prompt('Set stock for all variants:');
                                                if (val !== null && val !== '') handleBulkFill('stock', val);
                                            }}
                                            className="text-gray-400 dark:text-gray-500 hover:text-violet-500"
                                            title="Bulk fill stock"
                                        >
                                            <Plus className="w-3 h-3" />
                                        </button>
                                    </div>
                                </th>
                                <th className="px-3 py-3 text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Status</th>
                                <th className="px-3 py-3 text-right text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {variants.map((variant, index) => {
                                const isExpanded = expandedId === variant.id;
                                const stockVal = parseInt(variant.stock) || 0;
                                const stockColor = stockVal === 0
                                    ? 'text-red-600 bg-red-50 dark:bg-red-900/20'
                                    : stockVal <= 5
                                        ? 'text-amber-600 bg-amber-50 dark:bg-amber-900/20'
                                        : 'text-green-600 bg-green-50 dark:bg-green-900/20';
                                const isExisting = variant.id && !String(variant.id).startsWith('temp_');

                                return (
                                    <tr key={variant.id} className={`hover:bg-gray-50 dark:hover:bg-gray-800/50 ${isExpanded ? 'bg-violet-50/30 dark:bg-violet-900/10' : ''}`}>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => setExpandedId(isExpanded ? null : variant.id)}
                                                    className="text-gray-400 hover:text-gray-600 dark:text-gray-400"
                                                >
                                                    {isExpanded ? (
                                                        <ChevronUp className="w-4 h-4" />
                                                    ) : (
                                                        <ChevronDown className="w-4 h-4" />
                                                    )}
                                                </button>
                                                <div className="flex flex-wrap gap-1">
                                                    {variant.options.map((opt, i) => (
                                                        <span
                                                            key={i}
                                                            className="inline-flex px-1.5 py-0.5 rounded text-[11px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300"
                                                        >
                                                            {opt}
                                                        </span>
                                                    ))}
                                                </div>
                                                {isExisting && (
                                                    <span className="inline-flex items-center px-1 py-0.5 rounded text-[10px] font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                                                        Saved
                                                    </span>
                                                )}
                                            </div>
                                        </td>

                                        <td className="px-3 py-3 align-middle">
                                            <div className="relative w-10 h-10">
                                                <input
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/webp"
                                                    className="hidden"
                                                    id={`variant-image-${variant.id}`}
                                                    onChange={(e) => {
                                                        const file = e.target.files?.[0];
                                                        if (file) {
                                                            const updated = [...variants];
                                                            updated[index] = {
                                                                ...updated[index],
                                                                imageFile: file,
                                                                imageRemoved: false,
                                                            };
                                                            setVariants(updated);
                                                        }
                                                        e.target.value = '';
                                                    }}
                                                />
                                                <label
                                                    htmlFor={`variant-image-${variant.id}`}
                                                    className={`block w-10 h-10 rounded-md border-2 border-dashed cursor-pointer overflow-hidden ${
                                                        variant.imageFile || (variant.existingImageUrl && !variant.imageRemoved)
                                                            ? 'border-transparent'
                                                            : 'border-gray-300 hover:border-violet-400'
                                                    }`}
                                                >
                                                    {variant.imageFile ? (
                                                        <img
                                                            src={URL.createObjectURL(variant.imageFile)}
                                                            alt="Preview"
                                                            className="w-full h-full object-cover rounded-md"
                                                        />
                                                    ) : variant.existingImageUrl && !variant.imageRemoved ? (
                                                        <img
                                                            src={variant.existingImageUrl}
                                                            alt="Variant"
                                                            className="w-full h-full object-cover rounded-md"
                                                        />
                                                    ) : (
                                                        <div className="w-full h-full flex items-center justify-center bg-gray-50 dark:bg-gray-950 hover:bg-violet-50 transition-colors rounded-md">
                                                            <Camera className="w-4 h-4 text-gray-400 dark:text-gray-500" />
                                                        </div>
                                                    )}
                                                </label>
                                                {(variant.imageFile || (variant.existingImageUrl && !variant.imageRemoved)) && (
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            const updated = [...variants];
                                                            updated[index] = {
                                                                ...updated[index],
                                                                imageFile: null,
                                                                imageRemoved: updated[index].existingImageUrl ? true : false,
                                                            };
                                                            setVariants(updated);
                                                        }}
                                                        className="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-colors"
                                                    >
                                                        <X className="w-2.5 h-2.5" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>

                                        <td className="px-3 py-3">
                                            <input
                                                type="text"
                                                value={variant.sku || ''}
                                                onChange={(e) => {
                                                    const updated = [...variants];
                                                    updated[index] = { ...updated[index], sku: e.target.value };
                                                    setVariants(updated);
                                                }}
                                                placeholder="Auto"
                                                className="w-full rounded border-gray-200 dark:border-gray-800 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500"
                                            />
                                        </td>
                                        <td className="px-3 py-3">
                                            <input
                                                type="number"
                                                value={variant.price}
                                                onChange={(e) => {
                                                    const updated = [...variants];
                                                    updated[index] = { ...updated[index], price: e.target.value };
                                                    setVariants(updated);
                                                }}
                                                placeholder="0.00"
                                                step="0.01"
                                                min="0"
                                                className="w-full rounded border-gray-200 dark:border-gray-800 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500"
                                            />
                                        </td>
                                        <td className="px-3 py-3">
                                            <input
                                                type="number"
                                                value={variant.cost_price || ''}
                                                onChange={(e) => {
                                                    const updated = [...variants];
                                                    updated[index] = { ...updated[index], cost_price: e.target.value };
                                                    setVariants(updated);
                                                }}
                                                placeholder="0.00"
                                                step="0.01"
                                                min="0"
                                                className="w-full rounded border-gray-200 dark:border-gray-800 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500"
                                            />
                                        </td>
                                        <td className="px-3 py-3">
                                            <input
                                                type="number"
                                                value={variant.stock}
                                                onChange={(e) => {
                                                    const updated = [...variants];
                                                    updated[index] = { ...updated[index], stock: e.target.value };
                                                    setVariants(updated);
                                                }}
                                                placeholder="0"
                                                min="0"
                                                className={`w-full rounded border-gray-200 dark:border-gray-800 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 ${stockColor}`}
                                            />
                                        </td>
                                        <td className="px-3 py-3">
                                            <select
                                                value={variant.status || 'active'}
                                                onChange={(e) => {
                                                    const updated = [...variants];
                                                    updated[index] = { ...updated[index], status: e.target.value };
                                                    setVariants(updated);
                                                }}
                                                className="w-full rounded border-gray-200 dark:border-gray-800 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500"
                                            >
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                                <option value="draft">Draft</option>
                                            </select>
                                        </td>
                                        <td className="px-3 py-3 text-right">
                                            <button
                                                type="button"
                                                onClick={() => handleRemoveVariant(index)}
                                                className="text-gray-400 dark:text-gray-500 hover:text-red-500 transition-colors p-1"
                                                title="Remove variant"
                                            >
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
