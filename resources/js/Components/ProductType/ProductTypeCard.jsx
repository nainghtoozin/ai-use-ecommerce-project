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
    compact = false,
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`
                group relative flex text-left w-full rounded-xl border-2 p-4 transition-all duration-200
                ${compact ? 'flex-row items-start gap-3' : 'flex-col rounded-2xl p-6'}
                ${locked
                    ? 'border-gray-200 bg-gray-50 cursor-pointer hover:border-amber-300 hover:bg-amber-50/50'
                    : selected
                        ? 'border-blue-500 bg-blue-50/50 shadow-lg shadow-blue-500/10 ring-1 ring-blue-500'
                        : 'border-gray-200 bg-white cursor-pointer hover:border-blue-300 hover:shadow-md hover:shadow-gray-200/50'
                }
            `}
        >
            {locked && (
                <div className={`absolute ${compact ? 'top-2 right-2' : 'top-4 right-4'}`}>
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-900 text-white shadow-sm">
                        <Lock className="w-3 h-3" />
                        {!compact && 'Locked'}
                    </span>
                </div>
            )}

            {!locked && (
                <div className={`
                    rounded-full border-2 flex items-center justify-center transition-all
                    ${compact ? 'w-8 h-8 flex-shrink-0 border-blue-500 bg-blue-500' : 'absolute top-4 right-4 w-5 h-5'}
                    ${!compact && (selected ? 'border-blue-500 bg-blue-500' : 'border-gray-300 group-hover:border-blue-400')}
                    ${compact && selected ? '' : ''}
                `}>
                    {selected && (
                        <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                        </svg>
                    )}
                    {!compact && selected && (
                        <svg className="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                        </svg>
                    )}
                </div>
            )}

            <div className={`
                rounded-xl flex items-center justify-center mb-0 transition-colors
                ${compact ? 'w-10 h-10 flex-shrink-0 mb-0' : 'w-12 h-12 mb-4'}
                ${locked
                    ? 'bg-gray-200 text-gray-400'
                    : selected
                        ? 'bg-blue-100 text-blue-600'
                        : 'bg-gray-100 text-gray-600 group-hover:bg-blue-50 group-hover:text-blue-600'
                }
            `}>
                {icon}
            </div>

            <div className={`${compact ? 'flex-1 min-w-0' : ''}`}>
                <h3 className={`font-semibold mb-0.5 ${locked ? 'text-gray-500' : 'text-gray-900 dark:text-gray-100'} ${compact ? 'text-sm' : 'text-lg mb-1'}`}>
                    {title}
                </h3>
                <p className={`text-sm ${locked ? 'text-gray-400' : 'text-gray-500 dark:text-gray-400'}`}>
                    {description}
                </p>
            </div>

            {!compact && !locked && features.length > 0 && (
                <ul className="space-y-1.5 mb-5 flex-1">
                    {features.map((feature, i) => (
                        <li key={i} className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <svg className="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                            {feature}
                        </li>
                    ))}
                </ul>
            )}

            {!compact && locked && (
                <div className="mt-auto pt-4 border-t border-gray-200 dark:border-gray-800">
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

            {!compact && !locked && (
                <div className="mt-auto pt-4 border-t border-gray-100 dark:border-gray-800 flex items-center gap-1 text-sm font-medium text-blue-600 group-hover:gap-2 transition-all">
                    <span>{selected ? 'Selected' : 'Select'}</span>
                    <ArrowRight className="w-4 h-4" />
                </div>
            )}
        </button>
    );
}
