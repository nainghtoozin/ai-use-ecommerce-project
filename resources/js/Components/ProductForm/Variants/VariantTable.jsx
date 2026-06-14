import { useState } from 'react';
import { Plus, Trash2, ChevronDown, ChevronUp, X, Camera } from 'lucide-react';

export default function VariantTable({ options, variants, setVariants, errors = {} }) {
    const [expandedId, setExpandedId] = useState(null);

    const generateCombinations = () => {
        if (options.length === 0) {
            return [];
        }

        const combos = [];
        const generate = (index, current) => {
            if (index === options.length) {
                combos.push([...current]);
                return;
            }
            for (const value of options[index].values) {
                current.push(value);
                generate(index + 1, current);
                current.pop();
            }
        };
        generate(0, []);

        return combos.map((combo) => {
            const existing = variants.find((v) => {
                return combo.every((val, i) => v[`option${i + 1}`] === val);
            });

            return {
                id: existing?.id || `temp_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
                sku: existing?.sku || '',
                price: existing?.price || '',
                compare_price: existing?.compare_price || '',
                stock: existing?.stock ?? 0,
                options: combo,
                imageFile: existing?.imageFile || null,
                existingImage: existing?.existingImage || null,
                existingImageUrl: existing?.existingImageUrl || null,
                imageRemoved: existing?.imageRemoved || false,
            };
        });
    };

    const handleGenerate = () => {
        const combos = generateCombinations();
        setVariants(combos);
    };

    const handleVariantChange = (index, field, value) => {
        const updated = [...variants];
        updated[index] = { ...updated[index], [field]: value };
        setVariants(updated);
    };

    const handleBulkFill = (field, value) => {
        const updated = variants.map((v) => ({ ...v, [field]: value }));
        setVariants(updated);
    };

    const handleRemoveVariant = (index) => {
        setVariants(variants.filter((_, i) => i !== index));
    };

    const totalStock = variants.reduce((sum, v) => sum + (parseInt(v.stock) || 0), 0);

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            {/* Header */}
            <div className="px-5 py-4 border-b border-gray-100">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                            <svg className="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                        </div>
                        <div>
                            <h3 className="text-base font-semibold text-gray-900">
                                Variants
                                {variants.length > 0 && (
                                    <span className="ml-1.5 text-xs font-normal text-gray-400">
                                        ({variants.length})
                                    </span>
                                )}
                            </h3>
                            <p className="text-xs text-gray-500 mt-0.5">
                                Total stock: {totalStock} units
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {options.length > 0 && variants.length === 0 && (
                            <button
                                type="button"
                                onClick={handleGenerate}
                                className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors"
                            >
                                <Plus className="w-3.5 h-3.5" />
                                Generate Variants
                            </button>
                        )}
                        {variants.length > 0 && (
                            <button
                                type="button"
                                onClick={() => setVariants([])}
                                className="text-xs text-red-600 hover:text-red-700 font-medium"
                            >
                                Clear all
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {variants.length === 0 ? (
                <div className="px-5 py-12 text-center">
                    <div className="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                        <svg className="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                                d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                    </div>
                    <p className="text-sm text-gray-600 font-medium">No variants yet</p>
                    <p className="text-xs text-gray-400 mt-1">
                        Add variant options above, then generate combinations
                    </p>
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Variant</th>
                                <th className="px-3 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-14">Image</th>
                                <th className="px-3 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-32">
                                    <div className="flex items-center gap-1">
                                        SKU
                                    </div>
                                </th>
                                <th className="px-3 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-28">
                                    <div className="flex items-center gap-1">
                                        Price
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const val = prompt('Set price for all variants:');
                                                if (val !== null) handleBulkFill('price', val);
                                            }}
                                            className="text-gray-400 hover:text-violet-500"
                                            title="Bulk fill"
                                        >
                                            <Plus className="w-3 h-3" />
                                        </button>
                                    </div>
                                </th>
                                <th className="px-3 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-28">
                                    <div className="flex items-center gap-1">
                                        Compare Price
                                    </div>
                                </th>
                                <th className="px-3 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-24">
                                    <div className="flex items-center gap-1">
                                        Stock
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const val = prompt('Set stock for all variants:');
                                                if (val !== null) handleBulkFill('stock', val);
                                            }}
                                            className="text-gray-400 hover:text-violet-500"
                                            title="Bulk fill"
                                        >
                                            <Plus className="w-3 h-3" />
                                        </button>
                                    </div>
                                </th>
                                <th className="px-3 py-3 text-right text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-16">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {variants.map((variant, index) => {
                                const isExpanded = expandedId === variant.id;
                                const stockVal = parseInt(variant.stock) || 0;
                                const stockColor = stockVal === 0
                                    ? 'text-red-600 bg-red-50'
                                    : stockVal <= 5
                                        ? 'text-amber-600 bg-amber-50'
                                        : 'text-green-600 bg-green-50';

                                return (
                                    <tr key={variant.id} className={`hover:bg-gray-50/50 ${isExpanded ? 'bg-violet-50/30' : ''}`}>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => setExpandedId(isExpanded ? null : variant.id)}
                                                    className="text-gray-400 hover:text-gray-600"
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
                                                            className="inline-flex px-1.5 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-700"
                                                        >
                                                            {opt}
                                                        </span>
                                                    ))}
                                                </div>
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
                                                        variant.imageFile || variant.existingImageUrl
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
                                                        <div className="w-full h-full flex items-center justify-center bg-gray-50 hover:bg-violet-50 transition-colors rounded-md">
                                                            <Camera className="w-4 h-4 text-gray-400" />
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
                                                onChange={(e) => handleVariantChange(index, 'sku', e.target.value)}
                                                placeholder="Auto-generated"
                                                className="w-full rounded border-gray-200 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500"
                                            />
                                        </td>
                                        <td className="px-3 py-3">
                                            <input
                                                type="number"
                                                value={variant.price}
                                                onChange={(e) => handleVariantChange(index, 'price', e.target.value)}
                                                placeholder="0.00"
                                                step="0.01"
                                                min="0"
                                                className="w-full rounded border-gray-200 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500"
                                            />
                                        </td>
                                        <td className="px-3 py-3">
                                            <input
                                                type="number"
                                                value={variant.compare_price}
                                                onChange={(e) => handleVariantChange(index, 'compare_price', e.target.value)}
                                                placeholder="0.00"
                                                step="0.01"
                                                min="0"
                                                className="w-full rounded border-gray-200 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500"
                                            />
                                        </td>
                                        <td className="px-3 py-3">
                                            <input
                                                type="number"
                                                value={variant.stock}
                                                onChange={(e) => handleVariantChange(index, 'stock', e.target.value)}
                                                placeholder="0"
                                                min="0"
                                                className={`w-full rounded border-gray-200 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 ${stockColor}`}
                                            />
                                        </td>
                                        <td className="px-3 py-3 text-right">
                                            <button
                                                type="button"
                                                onClick={() => handleRemoveVariant(index)}
                                                className="text-gray-400 hover:text-red-500 transition-colors p-1"
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
