export default function FormTextarea({
    label,
    name,
    value,
    onChange,
    placeholder = '',
    error,
    required = false,
    helpText,
    rows = 4,
    disabled = false,
    className = '',
    ...props
}) {
    const textareaClasses = `
        w-full rounded-lg border px-3 py-2 text-sm transition-colors resize-y
        ${disabled ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : 'bg-white'}
        ${error
            ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
            : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500'
        }
        focus:outline-none focus:ring-1
        ${className}
    `.trim().replace(/\s+/g, ' ');

    return (
        <div>
            {label && (
                <label htmlFor={name} className="block text-sm font-medium text-gray-700 mb-1.5">
                    {label}
                    {required && <span className="text-red-500 ml-0.5">*</span>}
                </label>
            )}
            <textarea
                id={name}
                name={name}
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                rows={rows}
                disabled={disabled}
                className={textareaClasses}
                {...props}
            />
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
            {helpText && !error && <p className="mt-1 text-xs text-gray-500">{helpText}</p>}
        </div>
    );
}
