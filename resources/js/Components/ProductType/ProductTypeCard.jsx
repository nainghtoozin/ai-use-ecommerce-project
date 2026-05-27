import { Lock, ArrowRight } from 'lucide-react';

export default function ProductTypeCard({
    icon,
    title,
    description,
    features = [],
    locked = false,
    upgradeHint = null,
    selected = false,
    onClick,
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`
                group relative flex flex-col text-left w-full rounded-2xl border-2 p-6 transition-all duration-200
                ${locked
                    ? 'border-gray-200 bg-gray-50 cursor-pointer hover:border-amber-300 hover:bg-amber-50/50'
                    : selected
                        ? 'border-blue-500 bg-blue-50/50 shadow-lg shadow-blue-500/10 ring-1 ring-blue-500'
                        : 'border-gray-200 bg-white cursor-pointer hover:border-blue-300 hover:shadow-md hover:shadow-gray-200/50'
                }
            `}
        >
            {locked && (
                <div className="absolute top-4 right-4">
                    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-900 text-white shadow-sm">
                        <Lock className="w-3 h-3" />
                        Locked
                    </span>
                </div>
            )}

            {!locked && (
                <div className={`
                    absolute top-4 right-4 w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all
                    ${selected ? 'border-blue-500 bg-blue-500' : 'border-gray-300 group-hover:border-blue-400'}
                `}>
                    {selected && (
                        <svg className="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                        </svg>
                    )}
                </div>
            )}

            <div className={`
                w-12 h-12 rounded-xl flex items-center justify-center mb-4 transition-colors
                ${locked
                    ? 'bg-gray-200 text-gray-400'
                    : selected
                        ? 'bg-blue-100 text-blue-600'
                        : 'bg-gray-100 text-gray-600 group-hover:bg-blue-50 group-hover:text-blue-600'
                }
            `}>
                {icon}
            </div>

            <h3 className={`text-lg font-semibold mb-1 ${locked ? 'text-gray-500' : 'text-gray-900'}`}>
                {title}
            </h3>
            <p className={`text-sm mb-4 ${locked ? 'text-gray-400' : 'text-gray-500'}`}>
                {description}
            </p>

            {!locked && features.length > 0 && (
                <ul className="space-y-1.5 mb-5 flex-1">
                    {features.map((feature, i) => (
                        <li key={i} className="flex items-center gap-2 text-sm text-gray-600">
                            <svg className="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                            {feature}
                        </li>
                    ))}
                </ul>
            )}

            {locked && (
                <div className="mt-auto pt-4 border-t border-gray-200">
                    <div className="flex items-center gap-2 text-sm text-amber-600 font-medium">
                        <Lock className="w-3.5 h-3.5" />
                        <span>
                            {upgradeHint
                                ? `Upgrade to ${upgradeHint} plan`
                                : 'Upgrade to unlock'}
                        </span>
                    </div>
                </div>
            )}

            {!locked && (
                <div className="mt-auto pt-4 border-t border-gray-100 flex items-center gap-1 text-sm font-medium text-blue-600 group-hover:gap-2 transition-all">
                    <span>{selected ? 'Selected' : 'Select'}</span>
                    <ArrowRight className="w-4 h-4" />
                </div>
            )}
        </button>
    );
}
