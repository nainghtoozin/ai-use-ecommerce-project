import { useState } from 'react';
import { Plus, X, ChevronDown, ChevronUp, Trash2 } from 'lucide-react';

const OPTION_COLORS = [
    'bg-blue-100 text-blue-700 border-blue-200',
    'bg-purple-100 text-purple-700 border-purple-200',
    'bg-green-100 text-green-700 border-green-200',
    'bg-amber-100 text-amber-700 border-amber-200',
    'bg-pink-100 text-pink-700 border-pink-200',
    'bg-cyan-100 text-cyan-700 border-cyan-200',
];

export default function AttributeBuilder({ options, setOptions }) {
    const [isExpanded, setIsExpanded] = useState(true);
    const [newOptionName, setNewOptionName] = useState('');
    const [newOptionValues, setNewOptionValues] = useState('');

    const totalVariants = options.reduce((acc, opt) => {
        return acc * opt.values.filter((v) => v.trim()).length;
    }, 1);

    const handleAddOption = () => {
        if (!newOptionName.trim() || options.find((o) => o.name.toLowerCase() === newOptionName.trim().toLowerCase())) {
            return;
        }

        const values = newOptionValues
            .split('/')
            .map((v) => v.trim())
            .filter((v) => v);

        if (values.length === 0) return;

        setOptions([
            ...options,
            {
                name: newOptionName.trim(),
                values,
            },
        ]);

        setNewOptionName('');
        setNewOptionValues('');
    };

    const handleRemoveOption = (index) => {
        setOptions(options.filter((_, i) => i !== index));
    };

    const handleAddValue = (optionIndex, value) => {
        if (!value.trim()) return;
        const updated = [...options];
        if (!updated[optionIndex].values.includes(value.trim())) {
            updated[optionIndex].values.push(value.trim());
            setOptions(updated);
        }
    };

    const handleRemoveValue = (optionIndex, valueIndex) => {
        const updated = [...options];
        updated[optionIndex].values.splice(valueIndex, 1);
        setOptions(updated);
    };

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            {/* Header */}
            <button
                type="button"
                onClick={() => setIsExpanded(!isExpanded)}
                className="w-full px-5 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors"
            >
                <div className="flex items-center gap-3">
                    <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                        <svg className="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <div className="text-left">
                        <h3 className="text-base font-semibold text-gray-900">Variant Options</h3>
                        <p className="text-xs text-gray-500 mt-0.5">
                            {options.length} option{options.length !== 1 ? 's' : ''} · {totalVariants > 0 ? `${totalVariants} variant${totalVariants !== 1 ? 's' : ''}` : 'No variants yet'}
                        </p>
                    </div>
                </div>
                {isExpanded ? (
                    <ChevronUp className="w-5 h-5 text-gray-400" />
                ) : (
                    <ChevronDown className="w-5 h-5 text-gray-400" />
                )}
            </button>

            {isExpanded && (
                <div className="px-5 pb-5 space-y-4">
                    {/* Existing options */}
                    {options.length > 0 && (
                        <div className="space-y-3">
                            {options.map((option, index) => (
                                <div
                                    key={index}
                                    className={`rounded-lg border ${OPTION_COLORS[index % OPTION_COLORS.length]} p-4`}
                                >
                                    <div className="flex items-center justify-between mb-2">
                                        <span className="font-semibold text-sm">{option.name}</span>
                                        <button
                                            type="button"
                                            onClick={() => handleRemoveOption(index)}
                                            className="text-gray-400 hover:text-red-500 transition-colors"
                                        >
                                            <X className="w-4 h-4" />
                                        </button>
                                    </div>

                                    <div className="flex flex-wrap gap-1.5">
                                        {option.values.map((value, vIndex) => (
                                            <span
                                                key={vIndex}
                                                className="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-white/80 text-xs font-medium"
                                            >
                                                {value}
                                                <button
                                                    type="button"
                                                    onClick={() => handleRemoveValue(index, vIndex)}
                                                    className="text-gray-400 hover:text-red-500 transition-colors"
                                                >
                                                    <X className="w-3 h-3" />
                                                </button>
                                            </span>
                                        ))}
                                    </div>

                                    {/* Add value inline */}
                                    <div className="mt-2 flex gap-2">
                                        <input
                                            type="text"
                                            placeholder={`Add ${option.name} value...`}
                                            className="flex-1 rounded-md border-0 bg-white/50 px-2.5 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-current"
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    handleAddValue(index, e.target.value);
                                                    e.target.value = '';
                                                }
                                            }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Add new option form */}
                    <div className="rounded-lg border border-gray-200 p-4 space-y-3">
                        <h4 className="text-sm font-medium text-gray-700">Add Option</h4>

                        <div>
                            <label className="block text-xs text-gray-500 mb-1">Option name (e.g., Size, Color)</label>
                            <input
                                type="text"
                                value={newOptionName}
                                onChange={(e) => setNewOptionName(e.target.value)}
                                placeholder="e.g., Size"
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500"
                            />
                        </div>

                        <div>
                            <label className="block text-xs text-gray-500 mb-1">Values (separated by /)</label>
                            <input
                                type="text"
                                value={newOptionValues}
                                onChange={(e) => setNewOptionValues(e.target.value)}
                                placeholder="e.g., S / M / L / XL"
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500"
                            />
                        </div>

                        <button
                            type="button"
                            onClick={handleAddOption}
                            disabled={!newOptionName.trim() || !newOptionValues.trim()}
                            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                        >
                            <Plus className="w-3.5 h-3.5" />
                            Add Option
                        </button>
                    </div>

                    {/* Quick presets */}
                    {options.length === 0 && (
                        <div className="rounded-lg bg-gray-50 border border-gray-200 p-4">
                            <p className="text-xs text-gray-500 mb-2 font-medium">Quick presets:</p>
                            <div className="flex flex-wrap gap-2">
                                {[
                                    { name: 'Size', values: 'XS / S / M / L / XL / XXL' },
                                    { name: 'Color', values: 'Red / Blue / Black / White / Green' },
                                    { name: 'Material', values: 'Cotton / Polyester / Silk / Wool' },
                                ].map((preset) => (
                                    <button
                                        key={preset.name}
                                        type="button"
                                        onClick={() => {
                                            setNewOptionName(preset.name);
                                            setNewOptionValues(preset.values);
                                        }}
                                        className="px-2.5 py-1 rounded-md bg-white border border-gray-200 text-xs text-gray-600 hover:border-violet-300 hover:text-violet-600 transition-colors"
                                    >
                                        {preset.name} ({preset.values.split('/').length})
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
