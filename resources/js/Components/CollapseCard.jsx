import { ChevronDown } from 'lucide-react';

export default function CollapseCard({ title, subtitle, isOpen, onToggle, children }) {
    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <button
                type="button"
                onClick={onToggle}
                className="w-full px-6 py-4 flex items-center justify-between cursor-pointer hover:bg-gray-50 transition-colors"
            >
                <div className="text-left">
                    <h3 className="text-base font-semibold text-gray-900">{title}</h3>
                    {subtitle && (
                        <p className="text-xs text-gray-500 mt-0.5">{subtitle}</p>
                    )}
                </div>
                <div className="flex items-center gap-2 ml-4 flex-shrink-0">
                    <span className="text-xs text-gray-400 font-medium">
                        {isOpen ? 'Hide' : 'Show'}
                    </span>
                    <ChevronDown
                        className={`w-5 h-5 text-gray-400 transition-transform duration-200 ${
                            isOpen ? 'rotate-180' : ''
                        }`}
                    />
                </div>
            </button>

            <div
                className={`transition-all duration-300 ease-in-out overflow-hidden ${
                    isOpen ? 'max-h-[2000px] opacity-100' : 'max-h-0 opacity-0'
                }`}
            >
                <div className="border-t border-gray-100" />
                <div className="px-6 py-5">
                    {children}
                </div>
            </div>
        </div>
    );
}
