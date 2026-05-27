import { useState } from 'react';

export default function FormSection({
    title,
    description,
    icon,
    children,
    defaultOpen = true,
    collapsible = false,
    className = '',
}) {
    const [isOpen, setIsOpen] = useState(defaultOpen);

    return (
        <div className={`bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden ${className}`}>
            <button
                type="button"
                onClick={() => collapsible && setIsOpen(!isOpen)}
                className={`w-full px-5 py-4 flex items-center justify-between ${collapsible ? 'cursor-pointer hover:bg-gray-50' : 'cursor-default'}`}
            >
                <div className="flex items-center gap-3">
                    {icon && (
                        <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600">
                            {icon}
                        </div>
                    )}
                    <div className="text-left">
                        <h3 className="text-base font-semibold text-gray-900">{title}</h3>
                        {description && (
                            <p className="text-xs text-gray-500 mt-0.5">{description}</p>
                        )}
                    </div>
                </div>
                {collapsible && (
                    <svg
                        className={`w-5 h-5 text-gray-400 transition-transform ${isOpen ? 'rotate-180' : ''}`}
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                    </svg>
                )}
            </button>
            {(!collapsible || isOpen) && (
                <div className="px-5 pb-5">
                    {collapsible && <div className="border-t border-gray-100 mb-4" />}
                    <div className="space-y-4">
                        {children}
                    </div>
                </div>
            )}
        </div>
    );
}
