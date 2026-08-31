import { useState } from 'react';
import { Plus, X, ChevronDown, ChevronUp, Trash2, Edit2, Check } from 'lucide-react';

const OPTION_COLORS = [
    'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800',
    'bg-purple-100 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:border-purple-800',
    'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800',
    'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800',
    'bg-pink-100 text-pink-700 border-pink-200 dark:bg-pink-900/30 dark:text-pink-400 dark:border-pink-800',
    'bg-cyan-100 text-cyan-700 border-cyan-200 dark:bg-cyan-900/30 dark:text-cyan-400 dark:border-cyan-800',
];

const PRESETS = [
    { name: 'Size', values: ['XS', 'S', 'M', 'L', 'XL', 'XXL'] },
    { name: 'Color', values: ['Black', 'White', 'Red', 'Blue', 'Green', 'Navy'] },
    { name: 'Material', values: ['Cotton', 'Polyester', 'Silk', 'Wool', 'Linen'] },
    { name: 'Shoe Size', values: ['36', '37', '38', '39', '40', '41', '42', '43', '44'] },
];

export default function AttributeBuilder({
    options,
    setOptions,
    totalCombinations = 0,
    maxCombinations = 100,
}) {
    const [isExpanded, setIsExpanded] = useState(true);
    const [newOptionName, setNewOptionName] = useState('');
    const [newOptionValues, setNewOptionValues] = useState('');
    const [editingOptionIndex, setEditingOptionIndex] = useState(null);
    const [editingOptionName, setEditingOptionName] = useState('');
    const [editingValueIndex, setEditingValueIndex] = useState(null);
    const [editingValue, setEditingValue] = useState('');

    const handleAddOption = () => {
        if (!newOptionName.trim()) return;

        const nameExists = options.some(
            (o) => o.name.toLowerCase() === newOptionName.trim().toLowerCase()
        );
        if (nameExists) return;

        const values = newOptionValues
            .split('/')
            .map((v) => v.trim())
            .filter((v) => v);

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
        if (options.length === 1) {
            if (!window.confirm('Removing the last option will delete all variants. Continue?')) {
                return;
            }
        }
        setOptions(options.filter((_, i) => i !== index));
    };

    const handleStartEditOption = (index) => {
        setEditingOptionIndex(index);
        setEditingOptionName(options[index].name);
    };

    const handleSaveOptionName = (index) => {
        if (!editingOptionName.trim()) return;

        const nameExists = options.some(
            (o, i) => i !== index && o.name.toLowerCase() === editingOptionName.trim().toLowerCase()
        );
        if (nameExists) return;

        const updated = [...options];
        updated[index] = { ...updated[index], name: editingOptionName.trim() };
        setOptions(updated);
        setEditingOptionIndex(null);
        setEditingOptionName('');
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

    const handleStartEditValue = (optionIndex, valueIndex, currentValue) => {
        setEditingOptionIndex(optionIndex);
        setEditingValueIndex(valueIndex);
        setEditingValue(currentValue);
    };

    const handleSaveEditValue = () => {
        if (editingOptionIndex === null || editingValueIndex === null) return;
        if (!editingValue.trim()) return;

        const updated = [...options];
        updated[editingOptionIndex].values[editingValueIndex] = editingValue.trim();
        setOptions(updated);
        setEditingOptionIndex(null);
        setEditingValueIndex(null);
        setEditingValue('');
    };

    const handleApplyPreset = (preset) => {
        setNewOptionName(preset.name);
        setNewOptionValues(preset.values.join(' / '));
    };

    const handleKeyDown = (e, callback) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            callback();
        }
    };

    return (
        <div className="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
            {/* Header */}
            <button
                type="button"
                onClick={() => setIsExpanded(!isExpanded)}
                className="w-full px-5 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
            >
                <div className="flex items-center gap-3">
                    <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center">
                        <svg className="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <div className="text-left">
                        <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">Variant Options</h3>
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {options.length} option{options.length !== 1 ? 's' : ''}
                            {totalCombinations > 0 && (
                                <span className="ml-2">
                                    · <span className={totalCombinations > maxCombinations ? 'text-amber-600 dark:text-amber-400' : 'text-gray-600 dark:text-gray-400'}>
                                        {totalCombinations} combination{totalCombinations !== 1 ? 's' : ''}
                                    </span>
                                    {totalCombinations > maxCombinations && ' (exceeds limit)'}
                                </span>
                            )}
                        </p>
                    </div>
                </div>
                {isExpanded ? (
                    <ChevronUp className="w-5 h-5 text-gray-400 dark:text-gray-500" />
                ) : (
                    <ChevronDown className="w-5 h-5 text-gray-400 dark:text-gray-500" />
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
                                        {editingOptionIndex === index ? (
                                            <div className="flex items-center gap-2 flex-1">
                                                <input
                                                    type="text"
                                                    value={editingOptionName}
                                                    onChange={(e) => setEditingOptionName(e.target.value)}
                                                    onKeyDown={(e) => handleKeyDown(e, () => handleSaveOptionName(index))}
                                                    className="flex-1 rounded-md border-0 bg-white dark:bg-gray-900 px-2.5 py-1 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-current"
                                                    autoFocus
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => handleSaveOptionName(index)}
                                                    className="text-green-600 hover:text-green-700 dark:text-green-400"
                                                >
                                                    <Check className="w-4 h-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setEditingOptionIndex(null)}
                                                    className="text-gray-400 hover:text-gray-600"
                                                >
                                                    <X className="w-4 h-4" />
                                                </button>
                                            </div>
                                        ) : (
                                            <>
                                                <span className="font-semibold text-sm">{option.name}</span>
                                                <div className="flex items-center gap-1">
                                                    <button
                                                        type="button"
                                                        onClick={() => handleStartEditOption(index)}
                                                        className="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors p-1"
                                                        title="Edit option name"
                                                    >
                                                        <Edit2 className="w-3.5 h-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => handleRemoveOption(index)}
                                                        className="text-gray-400 dark:text-gray-500 hover:text-red-500 transition-colors p-1"
                                                        title="Remove option"
                                                    >
                                                        <Trash2 className="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                            </>
                                        )}
                                    </div>

                                    <div className="flex flex-wrap gap-1.5">
                                        {option.values.map((value, vIndex) => (
                                            <span
                                                key={vIndex}
                                                className="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-white dark:bg-gray-900/80 text-xs font-medium"
                                            >
                                                {editingValueIndex === vIndex && editingOptionIndex === index ? (
                                                    <>
                                                        <input
                                                            type="text"
                                                            value={editingValue}
                                                            onChange={(e) => setEditingValue(e.target.value)}
                                                            onKeyDown={(e) => handleKeyDown(e, handleSaveEditValue)}
                                                            className="w-20 rounded border-0 bg-white dark:bg-gray-900 px-1.5 py-0.5 text-xs focus:outline-none focus:ring-1 focus:ring-current"
                                                            autoFocus
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={handleSaveEditValue}
                                                            className="text-green-600 hover:text-green-700"
                                                        >
                                                            <Check className="w-3 h-3" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setEditingOptionIndex(null);
                                                                setEditingValueIndex(null);
                                                                setEditingValue('');
                                                            }}
                                                            className="text-gray-400 hover:text-gray-600"
                                                        >
                                                            <X className="w-3 h-3" />
                                                        </button>
                                                    </>
                                                ) : (
                                                    <>
                                                        <span>{value}</span>
                                                        <button
                                                            type="button"
                                                            onClick={() => handleStartEditValue(index, vIndex, value)}
                                                            className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                                        >
                                                            <Edit2 className="w-3 h-3" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => handleRemoveValue(index, vIndex)}
                                                            className="text-gray-400 hover:text-red-500 transition-colors"
                                                        >
                                                            <X className="w-3 h-3" />
                                                        </button>
                                                    </>
                                                )}
                                            </span>
                                        ))}
                                    </div>

                                    {/* Add value inline */}
                                    <div className="mt-2 flex gap-2">
                                        <input
                                            type="text"
                                            placeholder={`Add ${option.name} value...`}
                                            className="flex-1 rounded-md border-0 bg-white dark:bg-gray-900/50 px-2.5 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-current"
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    handleAddValue(index, e.target.value);
                                                    e.target.value = '';
                                                }
                                            }}
                                        />
                                        <button
                                            type="button"
                                            onClick={(e) => {
                                                const input = e.target.closest('.flex').querySelector('input');
                                                if (input.value.trim()) {
                                                    handleAddValue(index, input.value);
                                                    input.value = '';
                                                }
                                            }}
                                            className="px-2.5 py-1.5 rounded-md bg-white dark:bg-gray-900 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                                        >
                                            Add
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Add new option form */}
                    <div className="rounded-lg border border-gray-200 dark:border-gray-800 p-4 space-y-3">
                        <h4 className="text-sm font-medium text-gray-700 dark:text-gray-300">Add Option</h4>

                        <div>
                            <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Option name (e.g., Size, Color)</label>
                            <input
                                type="text"
                                value={newOptionName}
                                onChange={(e) => setNewOptionName(e.target.value)}
                                onKeyDown={(e) => handleKeyDown(e, handleAddOption)}
                                placeholder="e.g., Size"
                                className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500"
                            />
                        </div>

                        <div>
                            <label className="block text-xs text-gray-500 dark:text-gray-400 mb-1">Values (separated by /)</label>
                            <input
                                type="text"
                                value={newOptionValues}
                                onChange={(e) => setNewOptionValues(e.target.value)}
                                onKeyDown={(e) => handleKeyDown(e, handleAddOption)}
                                placeholder="e.g., S / M / L / XL"
                                className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500"
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
                        <div className="rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 p-4">
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-2 font-medium">Quick presets:</p>
                            <div className="flex flex-wrap gap-2">
                                {PRESETS.map((preset) => (
                                    <button
                                        key={preset.name}
                                        type="button"
                                        onClick={() => handleApplyPreset(preset)}
                                        className="px-2.5 py-1 rounded-md bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-xs text-gray-600 dark:text-gray-400 hover:border-violet-300 hover:text-violet-600 dark:hover:border-violet-600 dark:hover:text-violet-400 transition-colors"
                                    >
                                        {preset.name} ({preset.values.length})
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
