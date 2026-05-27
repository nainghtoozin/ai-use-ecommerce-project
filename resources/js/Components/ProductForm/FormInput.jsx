export default function FormInput({
    label,
    name,
    type = 'text',
    value,
    onChange,
    placeholder = '',
    error,
    required = false,
    helpText,
    min,
    max,
    step,
    disabled = false,
    prefix,
    suffix,
    className = '',
    ...props
}) {
    const inputClasses = `
        w-full rounded-lg border px-3 py-2 text-sm transition-colors
        ${prefix ? 'pl-8' : ''}
        ${suffix ? 'pr-10' : ''}
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
            <div className="relative">
                {prefix && (
                    <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <span className="text-gray-500 text-sm">{prefix}</span>
                    </div>
                )}
                <input
                    id={name}
                    name={name}
                    type={type}
                    value={value}
                    onChange={onChange}
                    placeholder={placeholder}
                    min={min}
                    max={max}
                    step={step}
                    disabled={disabled}
                    className={inputClasses}
                    {...props}
                />
                {suffix && (
                    <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                        <span className="text-gray-500 text-sm">{suffix}</span>
                    </div>
                )}
            </div>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
            {helpText && !error && <p className="mt-1 text-xs text-gray-500">{helpText}</p>}
        </div>
    );
}
