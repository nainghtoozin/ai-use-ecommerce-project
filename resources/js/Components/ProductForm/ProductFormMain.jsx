import { useState } from 'react';
import BasicInfoSection from './sections/BasicInfoSection';
import DescriptionSection from './sections/DescriptionSection';
import PricingSection from './sections/PricingSection';
import MediaSection from './sections/MediaSection';
import InventorySection from './sections/InventorySection';
import SEOSection from './sections/SEOSection';
import VariantSection from './Variants/VariantSection';
import ComboBuilder from './Combo/ComboBuilder';

export default function ProductFormMain({
    data,
    setData,
    errors,
    photo1File,
    setPhoto1File,
    photo2File,
    setPhoto2File,
    variants,
    setVariants,
    existingPhoto1Url,
    existingPhoto2Url,
    comboItems = [],
    setComboItems,
    selectableProducts = [],
}) {
    const isVariable = data.product_type === 'variable';
    const isCombo = data.product_type === 'combo';

    return (
        <div className="space-y-5">
            <BasicInfoSection
                data={data}
                setData={setData}
                errors={errors}
            />

            <DescriptionSection
                data={data}
                setData={setData}
                errors={errors}
            />

            {isVariable && (
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div className="px-5 py-4 border-b border-gray-100">
                        <div className="flex items-center gap-3">
                            <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                                <svg className="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 className="text-base font-semibold text-gray-900">Variant Pricing</h3>
                                <p className="text-xs text-gray-500 mt-0.5">Base price for this product (individual variant prices set below)</p>
                            </div>
                        </div>
                    </div>
                    <div className="px-5 py-5">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                    Base Price
                                </label>
                                <div className="relative">
                                    <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 text-sm">$</span>
                                    <input
                                        type="number"
                                        value={data.price}
                                        onChange={(e) => setData('price', e.target.value)}
                                        placeholder="0.00"
                                        step="0.01"
                                        min="0"
                                        className="w-full rounded-lg border border-gray-300 pl-8 pr-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                                {errors.price && <p className="mt-1 text-xs text-red-600">{errors.price}</p>}
                                <p className="mt-1 text-xs text-gray-500">Fallback price if variant has no price set</p>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                    Compare at Price
                                </label>
                                <div className="relative">
                                    <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 text-sm">$</span>
                                    <input
                                        type="number"
                                        value={data.base_price}
                                        onChange={(e) => setData('base_price', e.target.value)}
                                        placeholder="0.00"
                                        step="0.01"
                                        min="0"
                                        className="w-full rounded-lg border border-gray-300 pl-8 pr-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                                {errors.base_price && <p className="mt-1 text-xs text-red-600">{errors.base_price}</p>}
                                <p className="mt-1 text-xs text-gray-500">Original price before discount</p>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {!isVariable && (
                <PricingSection
                    data={data}
                    setData={setData}
                    errors={errors}
                />
            )}

            <MediaSection
                data={data}
                setData={setData}
                errors={errors}
                photo1File={photo1File}
                setPhoto1File={setPhoto1File}
                photo2File={photo2File}
                setPhoto2File={setPhoto2File}
            />

            {isVariable && (
                <VariantSection
                    variants={variants}
                    setVariants={setVariants}
                />
            )}

            {isCombo && (
                <ComboBuilder
                    items={comboItems}
                    setItems={setComboItems}
                    selectableProducts={selectableProducts}
                    comboPrice={parseFloat(data.price) || 0}
                    existingComboItems={data.existing_combo_items || []}
                />
            )}

            {!isVariable && !isCombo && (
                <InventorySection
                    data={data}
                    setData={setData}
                    errors={errors}
                />
            )}

            <SEOSection
                data={data}
                setData={setData}
                errors={errors}
            />
        </div>
    );
}
