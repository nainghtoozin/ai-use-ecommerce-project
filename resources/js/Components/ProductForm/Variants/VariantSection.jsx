import { useState, useEffect, useCallback } from 'react';
import AttributeBuilder from './AttributeBuilder';
import VariantTable from './VariantTable';

const MAX_COMBINATION_THRESHOLD = 100;

export default function VariantSection({ variants, setVariants }) {
    const [options, setOptions] = useState([]);
    const [showRegenerateConfirm, setShowRegenerateConfirm] = useState(false);
    const [existingVariantCount, setExistingVariantCount] = useState(0);

    useEffect(() => {
        if (variants && variants.length > 0 && variants[0].options) {
            const extractedOptions = [];
            const optionCount = variants[0].options.length;
            for (let i = 0; i < optionCount; i++) {
                const uniqueValues = [...new Set(variants.map((v) => v.options?.[i]).filter(Boolean))];
                if (uniqueValues.length > 0) {
                    extractedOptions.push({
                        name: `Option ${i + 1}`,
                        values: uniqueValues,
                    });
                }
            }
            if (extractedOptions.length > 0) {
                setOptions(extractedOptions);
            }
        }
        setExistingVariantCount(variants.length);
    }, []);

    const totalCombinations = options.reduce((acc, opt) => {
        return acc * opt.values.filter((v) => v.trim()).length;
    }, 1);

    const handleOptionsChange = (newOptions) => {
        setOptions(newOptions);
    };

    const handleVariantsChange = (newVariants) => {
        setVariants(newVariants);
        setExistingVariantCount(newVariants.length);
    };

    const handleGenerateRequest = () => {
        if (variants.length > 0 && totalCombinations > 0) {
            setShowRegenerateConfirm(true);
        } else {
            generateVariants();
        }
    };

    const generateVariants = useCallback(() => {
        if (options.length === 0) {
            setVariants([]);
            return;
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

        const newVariants = combos.map((combo) => {
            const existing = variants.find((v) => {
                return combo.every((val, i) => v[`option${i + 1}`] === val);
            });

            if (existing) {
                return existing;
            }

            return {
                id: `temp_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
                sku: '',
                price: '',
                compare_price: '',
                cost_price: '',
                stock: 0,
                options: combo,
                imageFile: null,
                existingImage: null,
                existingImageUrl: null,
                imageRemoved: false,
            };
        });

        setVariants(newVariants);
        setExistingVariantCount(newVariants.length);
        setShowRegenerateConfirm(false);
    }, [options, variants, setVariants]);

    const handleClearAll = () => {
        if (variants.length > 0) {
            if (window.confirm(`Are you sure you want to remove all ${variants.length} variants? This cannot be undone if the product is not saved.`)) {
                setVariants([]);
                setExistingVariantCount(0);
            }
        }
    };

    return (
        <div className="space-y-5">
            <AttributeBuilder
                options={options}
                setOptions={handleOptionsChange}
                totalCombinations={totalCombinations}
                maxCombinations={MAX_COMBINATION_THRESHOLD}
            />

            {options.length > 0 && (
                <VariantTable
                    options={options}
                    variants={variants}
                    setVariants={handleVariantsChange}
                    onGenerateRequest={handleGenerateRequest}
                    onClearAll={handleClearAll}
                    totalCombinations={totalCombinations}
                    maxCombinations={MAX_COMBINATION_THRESHOLD}
                    hasExistingVariants={variants.length > 0}
                />
            )}

            {showRegenerateConfirm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                            Regenerate Variants?
                        </h3>
                        <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            This will replace your existing {variants.length} variants with {totalCombinations} new combinations.
                            Existing variant data (SKU, price, stock) will be preserved for matching combinations.
                        </p>
                        <div className="flex gap-3 justify-end">
                            <button
                                type="button"
                                onClick={() => setShowRegenerateConfirm(false)}
                                className="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={generateVariants}
                                className="px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors"
                            >
                                Regenerate
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
