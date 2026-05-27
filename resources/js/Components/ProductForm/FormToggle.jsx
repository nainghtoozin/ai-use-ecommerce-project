export default function FormToggle({
    label,
    description,
    checked,
    onChange,
    name,
    disabled = false,
}) {
    return (
        <div className={`flex items-center justify-between gap-4 py-3 ${disabled ? 'opacity-50' : ''}`}>
            <div className="flex-1 min-w-0">
                <label
                    htmlFor={name}
                    className={`text-sm font-medium ${disabled ? 'text-gray-400' : 'text-gray-700'}`}
                >
                    {label}
                </label>
                {description && (
                    <p className={`text-xs mt-0.5 ${disabled ? 'text-gray-400' : 'text-gray-500'}`}>
                        {description}
                    </p>
                )}
            </div>
            <button
                type="button"
                id={name}
                role="switch"
                aria-checked={checked}
                disabled={disabled}
                onClick={() => onChange(!checked)}
                className={`
                    relative inline-flex h-5 w-9 flex-shrink-0 rounded-full border-2 border-transparent
                    transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2
                    ${checked ? 'bg-blue-600' : 'bg-gray-300'}
                    ${disabled ? 'cursor-not-allowed' : 'cursor-pointer'}
                `}
            >
                <span
                    className={`
                        pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow ring-0
                        transition-transform duration-200 ease-in-out
                        ${checked ? 'translate-x-4' : 'translate-x-0'}
                    `}
                />
            </button>
        </div>
    );
}
