import { useState } from 'react';
import { Package, Layers, Gift } from 'lucide-react';
import ProductTypeCard from './ProductTypeCard';
import UpgradeModal from './UpgradeModal';

const PRODUCT_TYPE_CONFIG = [
    {
        id: 'single',
        icon: <Package className="w-6 h-6" />,
        title: 'Single Product',
        description: 'A standard product with one price, one SKU, and no variations.',
        features: [
            'Single price point',
            'Simple inventory tracking',
            'Ideal for most products',
        ],
        featureKey: 'single_products',
    },
    {
        id: 'variable',
        icon: <Layers className="w-6 h-6" />,
        title: 'Variable Product',
        description: 'A product with multiple options like size, color, or material.',
        features: [
            'Size, color, and custom options',
            'Individual pricing per variant',
            'Variant-level inventory',
        ],
        featureKey: 'variable_products',
    },
    {
        id: 'combo',
        icon: <Gift className="w-6 h-6" />,
        title: 'Combo / Bundle',
        description: 'Group multiple products together as a single purchasable bundle.',
        features: [
            'Bundle multiple items',
            'Discounted bundle pricing',
            'Inventory sync across products',
        ],
        featureKey: 'combo_products',
    },
];

export default function ProductTypeSelector({
    onSelect,
    onBack,
    availableTypes = ['single'],
    allTypes = ['single', 'variable', 'combo'],
    featureStatus = {},
    allPlans = [],
}) {
    const [selectedType, setSelectedType] = useState(null);
    const [showUpgradeModal, setShowUpgradeModal] = useState(false);
    const [lockedType, setLockedType] = useState(null);

    const types = PRODUCT_TYPE_CONFIG.filter((t) => allTypes.includes(t.id));

    const getFeatureInfo = (typeId) => {
        const config = PRODUCT_TYPE_CONFIG.find((t) => t.id === typeId);
        if (!config) return {};

        const status = featureStatus[config.featureKey] || {};
        return {
            isLocked: status.locked || !availableTypes.includes(typeId),
            upgradeHint: status.upgrade_hint || null,
            label: status.label || config.title,
        };
    };

    const handleTypeClick = (type) => {
        const info = getFeatureInfo(type.id);
        if (info.isLocked) {
            setLockedType({
                id: type.id,
                title: type.title,
                upgradeHint: info.upgradeHint,
            });
            setShowUpgradeModal(true);
            return;
        }
        setSelectedType(type.id);
    };

    const handleContinue = () => {
        if (selectedType) {
            onSelect(selectedType);
        }
    };

    return (
        <>
            <div className="min-h-[calc(100vh-8rem)] flex flex-col">
                <div className="mb-8">
                    <button
                        type="button"
                        onClick={onBack}
                        className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-4 transition-colors"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Products
                    </button>
                    <h1 className="text-2xl font-bold text-gray-900 mb-1">Choose Product Type</h1>
                    <p className="text-gray-500">Select the type of product you want to create.</p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-6 mb-8">
                    {types.map((type) => {
                        const info = getFeatureInfo(type.id);
                        return (
                            <ProductTypeCard
                                key={type.id}
                                icon={type.icon}
                                title={type.title}
                                description={type.description}
                                features={type.features}
                                locked={info.isLocked}
                                upgradeHint={info.upgradeHint}
                                selected={selectedType === type.id}
                                onClick={() => handleTypeClick(type)}
                            />
                        );
                    })}
                </div>

                <div className="mt-auto flex items-center justify-between pt-6 border-t border-gray-200">
                    <p className="text-sm text-gray-500">
                        {selectedType
                            ? `Selected: ${types.find((t) => t.id === selectedType)?.title}`
                            : 'Select a product type to continue'
                        }
                    </p>
                    <div className="flex gap-3">
                        <button
                            type="button"
                            onClick={onBack}
                            className="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={handleContinue}
                            disabled={!selectedType}
                            className="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors shadow-sm"
                        >
                            Continue
                        </button>
                    </div>
                </div>
            </div>

            <UpgradeModal
                isOpen={showUpgradeModal}
                onClose={() => setShowUpgradeModal(false)}
                featureName={lockedType?.title}
                upgradeHint={lockedType?.upgradeHint}
                plans={allPlans}
            />
        </>
    );
}
