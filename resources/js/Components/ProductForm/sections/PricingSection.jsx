import FormInput from '../FormInput';

export default function PricingSection({ data, setData, errors }) {
    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div className="px-5 py-4 border-b border-gray-100">
                <div className="flex items-center gap-3">
                    <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                        <svg className="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 className="text-base font-semibold text-gray-900">Pricing</h3>
                        <p className="text-xs text-gray-500 mt-0.5">Set your product pricing</p>
                    </div>
                </div>
            </div>
            <div className="px-5 py-5 space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <FormInput
                        label="Sale Price"
                        name="price"
                        type="number"
                        value={data.price}
                        onChange={(e) => setData('price', e.target.value)}
                        placeholder="0.00"
                        error={errors.price}
                        required
                        step="0.01"
                        min="0"
                        prefix="$"
                    />
                    <FormInput
                        label="Compare at Price"
                        name="base_price"
                        type="number"
                        value={data.base_price}
                        onChange={(e) => setData('base_price', e.target.value)}
                        placeholder="0.00"
                        error={errors.base_price}
                        required
                        step="0.01"
                        min="0"
                        prefix="$"
                        helpText="Original price before discount"
                    />
                </div>

                <FormInput
                    label="Cost per Item"
                    name="cost_price"
                    type="number"
                    value={data.cost_price || ''}
                    onChange={(e) => setData('cost_price', e.target.value)}
                    placeholder="0.00"
                    error={errors.cost_price}
                    step="0.01"
                    min="0"
                    prefix="$"
                    helpText="Customers won't see this price"
                />

                {data.price && data.base_price && parseFloat(data.price) < parseFloat(data.base_price) && (
                    <div className="rounded-lg bg-green-50 border border-green-200 px-4 py-3">
                        <div className="flex items-center gap-2">
                            <svg className="w-4 h-4 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p className="text-sm text-green-700">
                                Customers save{' '}
                                <span className="font-semibold">
                                    {Math.round(((1 - parseFloat(data.price) / parseFloat(data.base_price)) * 100)).toFixed(0)}%
                                </span>
                                {' '}off the compare price
                            </p>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
